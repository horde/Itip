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
use Horde\Itip\Action\SendDeclineCounter;
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
use Horde\Itip\Conflict\UnknownAttendee;
use Horde\Itip\DefaultSchedulingPolicy;
use Horde\Itip\ItipMessage;
use Horde\Itip\ItipProcessor;
use Horde\Itip\NullCalendarState;
use Horde\Itip\SchedulingPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItipProcessor::class)]
final class ItipProcessorTest extends TestCase
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

    private function createRequestCalendar(string $uid, int $sequence = 0): VCalendar
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence($sequence);
        $event->setSummary('Test Meeting');
        $event->setDtstart(new DateTimeImmutable('2026-06-01 10:00', new DateTimeZone('UTC')));
        $event->setDtend(new DateTimeImmutable('2026-06-01 11:00', new DateTimeZone('UTC')));
        $event->setOrganizer(Organizer::create('organizer@example.com'));
        $event->addAttendee(Attendee::create('attendee@example.com', 'Attendee'));
        $cal->addChild($event);

        return $cal;
    }

    private function createReplyCalendar(
        string $uid,
        string $attendeeEmail,
        ParticipationStatus $status,
        int $sequence = 0,
    ): VCalendar {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REPLY'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence($sequence);
        $event->setOrganizer(Organizer::create('organizer@example.com'));
        $event->addAttendee(Attendee::create($attendeeEmail, null, $status));
        $cal->addChild($event);

        return $cal;
    }

    private function createCancelCalendar(
        string $uid,
        int $sequence = 1,
        ?string $recurrenceId = null,
    ): VCalendar {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $event = new Vevent();
        $event->setUid($uid);
        $event->setSequence($sequence);
        $event->setStatus(EventStatus::from('CANCELLED'));
        if ($recurrenceId !== null) {
            $event->setProperty('RECURRENCE-ID', $recurrenceId);
        }
        $cal->addChild($event);

        return $cal;
    }

    // REQUEST processing

    #[Test]
    public function requestCreatesNewEvent(): void
    {
        $processor = $this->createProcessor();
        $cal = $this->createRequestCalendar('uid-123');
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CreateEvent::class, $result->changes[0]);
        $this->assertSame('uid-123', $result->changes[0]->event->getUid());
        $this->assertSame('organizer@example.com', $result->changes[0]->organizerEmail);
    }

    #[Test]
    public function requestUpdatesExistingEvent(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-123');
        $existingEvent->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(0);

        $processor = $this->createProcessor($state);
        $cal = $this->createRequestCalendar('uid-123', 1);
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateEvent::class, $result->changes[0]);
        $this->assertSame('uid-123', $result->changes[0]->uid);
        $this->assertSame(1, $result->changes[0]->newSequence);
    }

    #[Test]
    public function requestRejectsOutdatedSequence(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-123');
        $existingEvent->setSequence(5);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(5);

        $processor = $this->createProcessor($state);
        $cal = $this->createRequestCalendar('uid-123', 3);
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertCount(1, $result->conflicts);
        $this->assertInstanceOf(OutdatedSequence::class, $result->conflicts[0]);
        $this->assertSame(3, $result->conflicts[0]->incomingSequence);
        $this->assertSame(5, $result->conflicts[0]->existingSequence);
    }

    #[Test]
    public function requestConflictsWhenNoVevent(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $processor = $this->createProcessor();
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
        $this->assertSame('VEVENT', $result->conflicts[0]->propertyName);
    }

    #[Test]
    public function requestConflictsWhenNoUid(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));
        $event = new Vevent();
        $cal->addChild($event);

        $processor = $this->createProcessor();
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
        $this->assertSame('UID', $result->conflicts[0]->propertyName);
    }

    #[Test]
    public function requestWithAutoRespondGeneratesSendReply(): void
    {
        $policy = $this->createMock(SchedulingPolicy::class);
        $policy->method('shouldAcceptUpdate')->willReturn(true);
        $policy->method('shouldAutoRespond')->willReturn(ParticipationStatus::from('ACCEPTED'));
        $policy->method('shouldSendNotification')->willReturn(true);

        $processor = $this->createProcessor(policy: $policy);
        $cal = $this->createRequestCalendar('uid-123');
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->actions);
        $this->assertSame('organizer@example.com', $result->actions[0]->organizerEmail);
        $this->assertSame('attendee@example.com', $result->actions[0]->attendeeEmail);
    }

    #[Test]
    public function requestPolicyRejectsUpdate(): void
    {
        $policy = $this->createMock(SchedulingPolicy::class);
        $policy->method('shouldAcceptUpdate')->willReturn(false);

        $processor = $this->createProcessor(policy: $policy);
        $cal = $this->createRequestCalendar('uid-123');
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertFalse($result->hasConflicts());
        $this->assertEmpty($result->changes);
        $this->assertEmpty($result->actions);
    }

    // REPLY processing

    #[Test]
    public function replyUpdatesAttendeeStatus(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-456');
        $existingEvent->setSequence(1);
        $existingEvent->addAttendee(Attendee::create('bob@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(1);

        $processor = $this->createProcessor($state);
        $cal = $this->createReplyCalendar(
            'uid-456',
            'bob@example.com',
            ParticipationStatus::from('ACCEPTED'),
            1,
        );
        $message = ItipMessage::fromCalendar($cal, 'bob@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateAttendeeStatus::class, $result->changes[0]);
        $this->assertSame('uid-456', $result->changes[0]->uid);
        $this->assertSame('bob@example.com', $result->changes[0]->attendeeEmail);
        $this->assertTrue($result->changes[0]->newStatus->equals(ParticipationStatus::from('ACCEPTED')));
    }

    #[Test]
    public function replyConflictsWhenEventNotFound(): void
    {
        $processor = $this->createProcessor();
        $cal = $this->createReplyCalendar(
            'uid-unknown',
            'bob@example.com',
            ParticipationStatus::from('ACCEPTED'),
        );
        $message = ItipMessage::fromCalendar($cal, 'bob@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(UnknownAttendee::class, $result->conflicts[0]);
        $this->assertSame('bob@example.com', $result->conflicts[0]->email);
        $this->assertSame('uid-unknown', $result->conflicts[0]->uid);
    }

    #[Test]
    public function replyRejectsOutdatedSequence(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-456');
        $existingEvent->setSequence(3);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(3);

        $processor = $this->createProcessor($state);
        $cal = $this->createReplyCalendar(
            'uid-456',
            'bob@example.com',
            ParticipationStatus::from('ACCEPTED'),
            1,
        );
        $message = ItipMessage::fromCalendar($cal, 'bob@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(OutdatedSequence::class, $result->conflicts[0]);
    }

    #[Test]
    public function replyConflictsWhenAttendeeNotInMessage(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-456');
        $existingEvent->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(0);

        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REPLY'));
        $event = new Vevent();
        $event->setUid('uid-456');
        $event->setSequence(0);
        $event->addAttendee(Attendee::create('other@example.com', null, ParticipationStatus::from('ACCEPTED')));
        $cal->addChild($event);

        $processor = $this->createProcessor($state);
        $message = ItipMessage::fromCalendar($cal, 'bob@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(UnknownAttendee::class, $result->conflicts[0]);
    }

    // CANCEL processing

    #[Test]
    public function cancelRemovesEvent(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-789');
        $existingEvent->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(0);

        $processor = $this->createProcessor($state);
        $cal = $this->createCancelCalendar('uid-789', 1);
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelEvent::class, $result->changes[0]);
        $this->assertSame('uid-789', $result->changes[0]->uid);
        $this->assertSame(1, $result->changes[0]->sequence);
    }

    #[Test]
    public function cancelRemovesInstance(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-789');
        $existingEvent->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(0);

        $processor = $this->createProcessor($state);
        $cal = $this->createCancelCalendar('uid-789', 1, '20260601T100000Z');
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelInstance::class, $result->changes[0]);
        $this->assertSame('uid-789', $result->changes[0]->uid);
        $this->assertSame('20260601T100000Z', $result->changes[0]->recurrenceId);
    }

    #[Test]
    public function cancelRejectsOutdatedSequence(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-789');
        $existingEvent->setSequence(5);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(5);

        $processor = $this->createProcessor($state);
        $cal = $this->createCancelCalendar('uid-789', 2);
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(OutdatedSequence::class, $result->conflicts[0]);
    }

    #[Test]
    public function cancelForUnknownEventStillProposesChange(): void
    {
        $processor = $this->createProcessor();
        $cal = $this->createCancelCalendar('uid-new', 1);
        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');

        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(CancelEvent::class, $result->changes[0]);
    }

    // Generator methods on ItipProcessor

    #[Test]
    public function generateRequestProducesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $event = new Vevent();
        $event->setUid('gen-uid-1');
        $event->setSequence(0);
        $event->setSummary('Generated Meeting');
        $event->setOrganizer(Organizer::create('org@example.com'));

        $cal = $processor->generateRequest($event, 'org@example.com');

        $this->assertNotNull($cal->getMethod());
        $this->assertSame('REQUEST', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('gen-uid-1', $events[0]->getUid());
        $this->assertNotNull($events[0]->getDtstamp());
    }

    #[Test]
    public function generateReplyProducesMinimalCalendar(): void
    {
        $processor = $this->createProcessor();
        $event = new Vevent();
        $event->setUid('gen-uid-2');
        $event->setSequence(1);
        $event->setOrganizer(Organizer::create('org@example.com'));
        $event->addAttendee(Attendee::create('bob@example.com'));

        $cal = $processor->generateReply(
            $event,
            'bob@example.com',
            ParticipationStatus::from('ACCEPTED'),
        );

        $this->assertSame('REPLY', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('gen-uid-2', $events[0]->getUid());
        $this->assertSame(1, $events[0]->getSequence());

        $attendees = $events[0]->getAttendees();
        $this->assertCount(1, $attendees);
        $this->assertSame('bob@example.com', $attendees[0]->getEmail());
        $this->assertTrue($attendees[0]->getParticipationStatus()->equals(ParticipationStatus::from('ACCEPTED')));
    }

    #[Test]
    public function generateCancelSetsStatusAndIncrementsSequence(): void
    {
        $processor = $this->createProcessor();
        $event = new Vevent();
        $event->setUid('gen-uid-3');
        $event->setSequence(2);
        $event->setSummary('To Cancel');
        $event->setOrganizer(Organizer::create('org@example.com'));

        $cal = $processor->generateCancel($event, 'org@example.com');

        $this->assertSame('CANCEL', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(3, $events[0]->getSequence());
        $this->assertNotNull($events[0]->getStatus());
        $this->assertSame('CANCELLED', $events[0]->getStatus()->value);
    }

    // Unsupported methods

    #[Test]
    public function unsupportedMethodReturnsEmptyResult(): void
    {
        $processor = $this->createProcessor();
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('X-CUSTOM'));
        $event = new Vevent();
        $event->setUid('uid-custom');
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'user@example.com');
        $result = $processor->process($message);

        $this->assertFalse($result->hasConflicts());
        $this->assertEmpty($result->changes);
        $this->assertEmpty($result->actions);
    }

    // PUBLISH processing

    #[Test]
    public function publishCreatesEvent(): void
    {
        $processor = $this->createProcessor();
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('PUBLISH'));

        $event = new Vevent();
        $event->setUid('uid-pub');
        $event->setSummary('Published Event');
        $event->setDtstart(new DateTimeImmutable('2026-06-01 10:00', new DateTimeZone('UTC')));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'publisher@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(PublishEvent::class, $result->changes[0]);
        $this->assertSame('uid-pub', $result->changes[0]->event->getUid());
    }

    #[Test]
    public function publishUpdatesExistingEvent(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-pub');
        $existingEvent->setSequence(0);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('findEventInstances')->willReturn([]);

        $processor = $this->createProcessor($state);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('PUBLISH'));

        $event = new Vevent();
        $event->setUid('uid-pub');
        $event->setSummary('Updated Published Event');
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'publisher@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(PublishEvent::class, $result->changes[0]);
    }

    #[Test]
    public function publishRejectedByPolicy(): void
    {
        $policy = $this->createMock(SchedulingPolicy::class);
        $policy->method('shouldAcceptPublish')->willReturn(false);
        $policy->method('shouldAcceptUpdate')->willReturn(true);
        $policy->method('shouldAutoRespond')->willReturn(null);
        $policy->method('shouldSendNotification')->willReturn(true);
        $policy->method('shouldAcceptCounter')->willReturn(null);

        $processor = $this->createProcessor(policy: $policy);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('PUBLISH'));

        $event = new Vevent();
        $event->setUid('uid-pub');
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'publisher@example.com');
        $result = $processor->process($message);

        $this->assertFalse($result->hasConflicts());
        $this->assertEmpty($result->changes);
    }

    // ADD processing

    #[Test]
    public function addAppendsInstances(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-recur');
        $existingEvent->setSequence(1);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(1);
        $state->method('findEventInstances')->willReturn([]);

        $processor = $this->createProcessor($state);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('ADD'));

        $instance = new Vevent();
        $instance->setUid('uid-recur');
        $instance->setSequence(1);
        $instance->setProperty('RECURRENCE-ID', '20260615T100000Z');
        $instance->setDtstart(new DateTimeImmutable('2026-06-15 10:00', new DateTimeZone('UTC')));
        $cal->addChild($instance);

        $message = ItipMessage::fromCalendar($cal, 'organizer@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(AddInstances::class, $result->changes[0]);
        $this->assertSame('uid-recur', $result->changes[0]->uid);
        $this->assertSame(1, $result->changes[0]->sequence);
    }

    #[Test]
    public function addRejectsOutdatedSequence(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-recur');
        $existingEvent->setSequence(3);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('getEventSequence')->willReturn(3);
        $state->method('findEventInstances')->willReturn([]);

        $processor = $this->createProcessor($state);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('ADD'));

        $instance = new Vevent();
        $instance->setUid('uid-recur');
        $instance->setSequence(1);
        $cal->addChild($instance);

        $message = ItipMessage::fromCalendar($cal, 'organizer@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(OutdatedSequence::class, $result->conflicts[0]);
    }

    #[Test]
    public function addRequiresExistingEvent(): void
    {
        $processor = $this->createProcessor();
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('ADD'));

        $instance = new Vevent();
        $instance->setUid('uid-nonexist');
        $instance->setSequence(1);
        $cal->addChild($instance);

        $message = ItipMessage::fromCalendar($cal, 'organizer@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
    }

    // REFRESH processing

    #[Test]
    public function refreshProducesRefreshRequested(): void
    {
        $processor = $this->createProcessor();
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REFRESH'));

        $event = new Vevent();
        $event->setUid('uid-refresh');
        $event->addAttendee(Attendee::create('attendee@example.com'));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(RefreshRequested::class, $result->changes[0]);
        $this->assertSame('uid-refresh', $result->changes[0]->uid);
        $this->assertSame('attendee@example.com', $result->changes[0]->attendeeEmail);
    }

    // COUNTER processing

    #[Test]
    public function counterWithManualReviewProducesUpdateEvent(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-counter');
        $existingEvent->setSequence(1);
        $existingEvent->setOrganizer(Organizer::create('organizer@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('findEventInstances')->willReturn([]);

        $processor = $this->createProcessor($state);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('COUNTER'));

        $event = new Vevent();
        $event->setUid('uid-counter');
        $event->setSequence(1);
        $event->setDtstart(new DateTimeImmutable('2026-06-01 14:00', new DateTimeZone('UTC')));
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
        $this->assertInstanceOf(UpdateEvent::class, $result->changes[0]);
    }

    #[Test]
    public function counterDeclinedByPolicy(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-counter');
        $existingEvent->setSequence(1);
        $existingEvent->setOrganizer(Organizer::create('organizer@example.com'));

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('findEventInstances')->willReturn([]);

        $policy = $this->createMock(SchedulingPolicy::class);
        $policy->method('shouldAcceptCounter')->willReturn(false);
        $policy->method('shouldSendNotification')->willReturn(true);
        $policy->method('shouldAcceptUpdate')->willReturn(true);
        $policy->method('shouldAutoRespond')->willReturn(null);
        $policy->method('shouldAcceptPublish')->willReturn(true);

        $processor = $this->createProcessor($state, $policy);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('COUNTER'));

        $event = new Vevent();
        $event->setUid('uid-counter');
        $event->setSequence(1);
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(CounterDeclined::class, $result->conflicts[0]);
        $this->assertCount(1, $result->actions);
        $this->assertInstanceOf(SendDeclineCounter::class, $result->actions[0]);
    }

    #[Test]
    public function counterRequiresExistingEvent(): void
    {
        $processor = $this->createProcessor();
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('COUNTER'));

        $event = new Vevent();
        $event->setUid('uid-nonexist');
        $event->setSequence(1);
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'attendee@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(MissingRequiredProperty::class, $result->conflicts[0]);
    }

    // DECLINECOUNTER processing

    #[Test]
    public function declineCounterProducesConflict(): void
    {
        $existingEvent = new Vevent();
        $existingEvent->setUid('uid-dc');
        $existingEvent->setSequence(1);

        $state = $this->createMock(CalendarState::class);
        $state->method('findEventByUid')->willReturn($existingEvent);
        $state->method('findEventInstances')->willReturn([]);

        $processor = $this->createProcessor($state);
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('DECLINECOUNTER'));

        $event = new Vevent();
        $event->setUid('uid-dc');
        $event->setSequence(1);
        $cal->addChild($event);

        $message = ItipMessage::fromCalendar($cal, 'organizer@example.com');
        $result = $processor->process($message);

        $this->assertTrue($result->hasConflicts());
        $this->assertInstanceOf(CounterDeclined::class, $result->conflicts[0]);
        $this->assertSame('uid-dc', $result->conflicts[0]->uid);
        $this->assertSame('organizer@example.com', $result->conflicts[0]->attendeeEmail);
    }

    // Generator tests for new methods

    #[Test]
    public function generatePublishCreatesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $event = new Vevent();
        $event->setUid('uid-gen-pub');
        $event->setSummary('Published');
        $event->setDtstart(new DateTimeImmutable('2026-06-01 10:00', new DateTimeZone('UTC')));

        $cal = $processor->generatePublish($event);

        $this->assertSame('PUBLISH', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('uid-gen-pub', $events[0]->getUid());
    }

    #[Test]
    public function generateAddCreatesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $instance = new Vevent();
        $instance->setDtstart(new DateTimeImmutable('2026-06-15 10:00', new DateTimeZone('UTC')));
        $instance->setProperty('RECURRENCE-ID', '20260615T100000Z');

        $cal = $processor->generateAdd([$instance], 'organizer@example.com', 'uid-gen-add', 2);

        $this->assertSame('ADD', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('uid-gen-add', $events[0]->getUid());
        $this->assertSame(2, $events[0]->getSequence());
    }

    #[Test]
    public function generateRefreshCreatesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $cal = $processor->generateRefresh('uid-gen-ref', 'attendee@example.com');

        $this->assertSame('REFRESH', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('uid-gen-ref', $events[0]->getUid());
    }

    #[Test]
    public function generateCounterCreatesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $proposal = new Vevent();
        $proposal->setUid('uid-gen-ctr');
        $proposal->setDtstart(new DateTimeImmutable('2026-06-01 14:00', new DateTimeZone('UTC')));

        $cal = $processor->generateCounter($proposal, 'attendee@example.com');

        $this->assertSame('COUNTER', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('uid-gen-ctr', $events[0]->getUid());
    }

    #[Test]
    public function generateDeclineCounterCreatesValidCalendar(): void
    {
        $processor = $this->createProcessor();
        $original = new Vevent();
        $original->setUid('uid-gen-dc');
        $original->setDtstart(new DateTimeImmutable('2026-06-01 10:00', new DateTimeZone('UTC')));

        $cal = $processor->generateDeclineCounter($original, 'organizer@example.com');

        $this->assertSame('DECLINECOUNTER', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('uid-gen-dc', $events[0]->getUid());
    }
}
