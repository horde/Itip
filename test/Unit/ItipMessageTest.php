<?php

declare(strict_types=1);

namespace Horde\Itip\Test\Unit;

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Calendar\Vtodo;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Itip\Exception\ItipException;
use Horde\Itip\ItipMessage;
use Horde\Itip\ItipResult;
use Horde\Itip\Change\CreateEvent;
use Horde\Itip\Conflict\MissingRequiredProperty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItipMessage::class)]
#[CoversClass(ItipResult::class)]
final class ItipMessageTest extends TestCase
{
    #[Test]
    public function fromCalendarExtractsMethod(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $msg = ItipMessage::fromCalendar($cal, 'user@example.com');

        $this->assertSame('REQUEST', $msg->method->value);
        $this->assertSame('user@example.com', $msg->actorEmail);
    }

    #[Test]
    public function fromCalendarNormalizesEmail(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REPLY'));

        $msg = ItipMessage::fromCalendar($cal, 'User@Example.COM');

        $this->assertSame('user@example.com', $msg->actorEmail);
    }

    #[Test]
    public function fromCalendarThrowsWhenNoMethod(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();

        $this->expectException(ItipException::class);
        ItipMessage::fromCalendar($cal, 'user@example.com');
    }

    #[Test]
    public function getFirstEventReturnsNullWhenEmpty(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $msg = ItipMessage::fromCalendar($cal, 'user@example.com');
        $this->assertNull($msg->getFirstEvent());
    }

    #[Test]
    public function getFirstEventReturnsFirstVevent(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));
        $event = new Vevent();
        $event->setUid('evt-1');
        $cal->addChild($event);

        $msg = ItipMessage::fromCalendar($cal, 'user@example.com');
        $first = $msg->getFirstEvent();

        $this->assertNotNull($first);
        $this->assertSame('evt-1', $first->getUid());
    }

    #[Test]
    public function getFirstTodoReturnsTodo(): void
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setMethod(CalendarMethod::from('REQUEST'));
        $todo = new Vtodo();
        $todo->setUid('todo-1');
        $cal->addChild($todo);

        $msg = ItipMessage::fromCalendar($cal, 'user@example.com');
        $first = $msg->getFirstTodo();

        $this->assertNotNull($first);
        $this->assertSame('todo-1', $first->getUid());
    }

    // =========================================================================
    // ItipResult
    // =========================================================================

    #[Test]
    public function emptyResultIsAccepted(): void
    {
        $result = new ItipResult();

        $this->assertTrue($result->isAccepted());
        $this->assertFalse($result->hasConflicts());
        $this->assertEmpty($result->changes);
        $this->assertEmpty($result->actions);
    }

    #[Test]
    public function resultWithConflictsIsNotAccepted(): void
    {
        $result = new ItipResult(conflicts: [
            new MissingRequiredProperty('UID', 'test'),
        ]);

        $this->assertFalse($result->isAccepted());
        $this->assertTrue($result->hasConflicts());
    }

    #[Test]
    public function resultWithChangesOnlyIsAccepted(): void
    {
        $event = new Vevent();
        $result = new ItipResult(changes: [
            new CreateEvent($event, 'org@example.com'),
        ]);

        $this->assertTrue($result->isAccepted());
        $this->assertCount(1, $result->changes);
    }
}
