<?php

declare(strict_types=1);

namespace Horde\Itip\Test\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\Enum\EventStatus;
use Horde\Icalendar\Enum\ParticipationStatus;
use Horde\Icalendar\Value\Attendee;
use Horde\Icalendar\Value\Organizer;
use Horde\Itip\CalendarState;
use Horde\Itip\Change\AddInstances;
use Horde\Itip\Change\CancelEvent;
use Horde\Itip\Change\CancelInstance;
use Horde\Itip\Change\CreateEvent;
use Horde\Itip\Change\PublishEvent;
use Horde\Itip\Change\RefreshRequested;
use Horde\Itip\Change\UpdateAttendeeStatus;
use Horde\Itip\Change\UpdateEvent;
use Horde\Itip\Conflict\CounterDeclined;
use Horde\Itip\Conflict\MissingRequiredProperty;
use Horde\Itip\Conflict\OutdatedSequence;
use Horde\Itip\DefaultSchedulingPolicy;
use Horde\Itip\ItipMessage;
use Horde\Itip\ItipProcessor;
use Horde\Itip\NullCalendarState;
use Horde\Itip\SchedulingPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests iTIP processing using scenarios derived from RFC 5546 §4 canonical examples.
 */
#[CoversClass(ItipProcessor::class)]
final class Rfc5546ProcessingTest extends TestCase
{
    private function createProcessor(
        ?CalendarState $state = null,
        ?SchedulingPolicy $policy = null,
    ): ItipProcessor {
        return new ItipProcessor(
            $state ?? new NullCalendarState(),
            $policy ?? new DefaultSchedulingPolicy(),
        );
    }

    private function utc(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
    }

    /**
     * RFC 5546 §4.1.1: Minimal PUBLISH creates a new published event.
     */
    #[Test]
    public function processPublishCreatesNewEvent(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('PUBLISH'));

        $event = new Vevent();
        $event->setUid('calsrv.example.com-873970198738777@example.com');
        $event->setSequence(0);
        $event->setSummary('Bastille Day Party');
        $event->setDtstart($this->utc('1997-07-14 17:00'));
        $event->setDtend($this->utc('1997-07-15 04:00'));
        $event->setStatus(EventStatus::from('CONFIRMED'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'subscriber@example.com');
        $result = $this->createProcessor()->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(PublishEvent::class, $result->changes[0]);
    }

    /**
     * RFC 5546 §4.1.2: Updated PUBLISH with SEQUENCE bump updates existing.
     */
    #[Test]
    public function processPublishUpdatesExistingEvent(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(0);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('PUBLISH'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(1);
        $event->setSummary('Bastille Day Party - Updated');
        $event->setDtstart($this->utc('1997-07-14 17:00'));
        $event->setDtend($this->utc('1997-07-15 05:00'));
        $event->setStatus(EventStatus::from('CONFIRMED'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'subscriber@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(PublishEvent::class, $result->changes[0]);
    }

    /**
     * RFC 5546 §4.1.3: CANCEL method removes a previously published event.
     */
    #[Test]
    public function processPublishCancel(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(1);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(1);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(2);
        $event->setStatus(EventStatus::from('CANCELLED'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'subscriber@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelEvent::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
    }

    /**
     * RFC 5546 §4.2.1: Group REQUEST creates a new event with multiple attendees.
     */
    #[Test]
    public function processGroupRequestCreatesEvent(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $event = new Vevent();
        $event->setUid('calsrv.example.com-873970198738777@example.com');
        $event->setSequence(0);
        $event->setSummary('Phone Conference');
        $event->setDtstart($this->utc('1997-07-01 18:00'));
        $event->setDtend($this->utc('1997-07-01 19:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('a@example.com', null, ParticipationStatus::from('ACCEPTED')));
        $event->addAttendee(Attendee::create('b@example.com'));
        $event->addAttendee(Attendee::create('c@example.com'));
        $event->addAttendee(Attendee::create('d@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor()->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CreateEvent::class, $result->changes[0]);
        $this->assertSame('a@example.com', $result->changes[0]->organizerEmail);
    }

    /**
     * RFC 5546 §4.2.2: REPLY with PARTSTAT=ACCEPTED updates attendee status.
     */
    #[Test]
    public function processReplyAccepted(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);
        $existing->setOrganizer(Organizer::create('a@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(0);
        $state->method('getAttendeeStatus')->willReturn(ParticipationStatus::from('NEEDS-ACTION'));

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REPLY'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(0);
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com', null, ParticipationStatus::from('ACCEPTED')));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateAttendeeStatus::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
        $this->assertSame('b@example.com', $result->changes[0]->attendeeEmail);
    }

    /**
     * RFC 5546 §4.2.3: Updated REQUEST with SEQUENCE bump updates existing event.
     */
    #[Test]
    public function processUpdatedRequestUpdatesEvent(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(0);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(1);
        $event->setSummary('Phone Conference - new time');
        $event->setDtstart($this->utc('1997-07-01 19:00'));
        $event->setDtend($this->utc('1997-07-01 20:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateEvent::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
        $this->assertSame(1, $result->changes[0]->newSequence);
    }

    /**
     * RFC 5546 §4.2.4: COUNTER proposal (manual review mode).
     */
    #[Test]
    public function processCounterProposal(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);
        $existing->setOrganizer(Organizer::create('a@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(0);

        // Default policy returns null for shouldAcceptCounter = manual review
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('COUNTER'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(0);
        $event->setSummary('Phone Conference');
        $event->setDtstart($this->utc('1997-07-02 19:00'));
        $event->setDtend($this->utc('1997-07-02 20:00'));
        $event->setLocation('New Location');
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateEvent::class, $result->changes[0]);
    }

    /**
     * RFC 5546 §4.2.4: DECLINECOUNTER rejects a counter-proposal.
     */
    #[Test]
    public function processDeclineCounter(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);
        $existing->setOrganizer(Organizer::create('a@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('DECLINECOUNTER'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(0);
        $event->setSummary('Phone Conference');
        $event->setDtstart($this->utc('1997-07-01 18:00'));
        $event->setDtend($this->utc('1997-07-01 19:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'a@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertCount(1, $result->conflicts);
        $this->assertInstanceOf(CounterDeclined::class, $result->conflicts[0]);
        $this->assertSame($uid, $result->conflicts[0]->uid);
    }

    /**
     * RFC 5546 §4.2.9: CANCEL group event.
     */
    #[Test]
    public function processCancelGroupEvent(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(0);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(1);
        $event->setStatus(EventStatus::from('CANCELLED'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com'));
        $event->addAttendee(Attendee::create('c@example.com'));
        $event->addAttendee(Attendee::create('d@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelEvent::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
    }

    /**
     * RFC 5546 §4.4.3: CANCEL a single instance using RECURRENCE-ID.
     */
    #[Test]
    public function processCancelSingleInstance(): void
    {
        $uid = 'guid-1@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(1);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(1);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(2);
        $event->setProperty('RECURRENCE-ID', '19970801T210000Z');
        $event->setStatus(EventStatus::from('CANCELLED'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelInstance::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
        $this->assertSame('19970801T210000Z', $result->changes[0]->recurrenceId);
    }

    /**
     * RFC 5546 §4.4.4: CANCEL entire recurring series (no RECURRENCE-ID).
     */
    #[Test]
    public function processCancelEntireSeries(): void
    {
        $uid = 'guid-1@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(2);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(2);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(3);
        $event->setStatus(EventStatus::from('CANCELLED'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelEvent::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
    }

    /**
     * RFC 5546 §4.4.6: ADD a new instance to an existing recurring event.
     */
    #[Test]
    public function processAddNewInstance(): void
    {
        $uid = '123456789@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(3);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(3);
        $state->method('findEventInstances')->willReturn([]);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('ADD'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(4);
        $event->setSummary('IETF Calendaring Working Group Meeting');
        $event->setDtstart($this->utc('1997-07-15 21:00'));
        $event->setDtend($this->utc('1997-07-15 22:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(AddInstances::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
        $this->assertCount(1, $result->changes[0]->instances);
    }

    /**
     * RFC 5546 §4.7.1: REFRESH requests a fresh copy of the event.
     */
    #[Test]
    public function processRefreshRequestsUpdate(): void
    {
        $uid = 'guid-1-12345@example.com';

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REFRESH'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setDtstamp($this->utc('1997-06-03 09:40'));
        $event->addAttendee(Attendee::create('b@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor()->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(RefreshRequested::class, $result->changes[0]);
        $this->assertSame($uid, $result->changes[0]->uid);
        $this->assertSame('b@example.com', $result->changes[0]->attendeeEmail);
    }

    /**
     * Outdated SEQUENCE is rejected with OutdatedSequence conflict.
     */
    #[Test]
    public function processOutdatedSequenceRejected(): void
    {
        $uid = 'calsrv.example.com-873970198738777@example.com';

        $existing = new Vevent();
        $existing->setUid($uid);
        $existing->setSequence(5);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existing);
        $state->method('getEventSequence')->willReturn(5);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence(3);
        $event->setSummary('Old update');
        $event->setDtstart($this->utc('1997-07-01 18:00'));
        $event->setDtend($this->utc('1997-07-01 19:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor($state)->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(OutdatedSequence::class, $result->conflicts[0]);
        $this->assertSame(3, $result->conflicts[0]->incomingSequence);
        $this->assertSame(5, $result->conflicts[0]->existingSequence);
    }

    /**
     * REQUEST without VEVENT component produces MissingRequiredProperty.
     */
    #[Test]
    public function processRequestMissingVeventConflicts(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor()->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
        $this->assertSame('VEVENT', $result->conflicts[0]->propertyName);
    }

    /**
     * REQUEST with VEVENT but no UID produces MissingRequiredProperty.
     */
    #[Test]
    public function processRequestMissingUidConflicts(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $event = new Vevent();
        $event->setSummary('No UID event');
        $event->setDtstart($this->utc('1997-07-01 18:00'));
        $event->setOrganizer(Organizer::create('a@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'b@example.com');
        $result = $this->createProcessor()->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
        $this->assertSame('UID', $result->conflicts[0]->propertyName);
    }
}
