<?php

declare(strict_types=1);

namespace Horde\Itip\Test\Unit;

use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\ParticipationStatus;
use Horde\Icalendar\Value\Attendee;
use Horde\Icalendar\Value\Organizer;
use Horde\Itip\Generator\CancelGenerator;
use Horde\Itip\Generator\ReplyGenerator;
use Horde\Itip\Generator\RequestGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestGenerator::class)]
#[CoversClass(ReplyGenerator::class)]
#[CoversClass(CancelGenerator::class)]
final class GeneratorTest extends TestCase
{
    private function createSampleEvent(): Vevent
    {
        $event = new Vevent();
        $event->setUid('gen-test-uid');
        $event->setSequence(1);
        $event->setSummary('Generator Test');
        $event->setOrganizer(Organizer::create('org@example.com'));
        $event->addAttendee(Attendee::create('a@example.com'));
        $event->addAttendee(Attendee::create('b@example.com'));
        return $event;
    }

    #[Test]
    public function requestGeneratorSetsMethodRequest(): void
    {
        $gen = new RequestGenerator('org@example.com');
        $cal = $gen->generate($this->createSampleEvent());

        $this->assertSame('REQUEST', $cal->getMethod()->value);
        $this->assertSame('2.0', $cal->getVersion());
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('gen-test-uid', $events[0]->getUid());
        $this->assertNotNull($events[0]->getDtstamp());
    }

    #[Test]
    public function replyGeneratorCreatesMinimalReply(): void
    {
        $gen = new ReplyGenerator('a@example.com', ParticipationStatus::from('DECLINED'));
        $cal = $gen->generate($this->createSampleEvent());

        $this->assertSame('REPLY', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('gen-test-uid', $events[0]->getUid());
        $this->assertSame(1, $events[0]->getSequence());

        $attendees = $events[0]->getAttendees();
        $this->assertCount(1, $attendees);
        $this->assertSame('a@example.com', $attendees[0]->getEmail());
        $this->assertTrue($attendees[0]->getParticipationStatus()->equals(ParticipationStatus::from('DECLINED')));

        $organizer = $events[0]->getOrganizer();
        $this->assertNotNull($organizer);
        $this->assertSame('org@example.com', $organizer->getEmail());
    }

    #[Test]
    public function cancelGeneratorSetsStatusAndIncrementsSequence(): void
    {
        $gen = new CancelGenerator('org@example.com');
        $cal = $gen->generate($this->createSampleEvent());

        $this->assertSame('CANCEL', $cal->getMethod()->value);
        $events = $cal->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]->getSequence());
        $this->assertNotNull($events[0]->getStatus());
        $this->assertSame('CANCELLED', $events[0]->getStatus()->value);
        $this->assertNotNull($events[0]->getDtstamp());
    }
}
