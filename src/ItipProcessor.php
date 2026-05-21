<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip;

use DateTimeImmutable;
use DateTimeZone;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\Enum\EventStatus;
use Horde\Icalendar\Enum\ParticipationStatus;
use Horde\Icalendar\Value\Attendee;
use Horde\Icalendar\Value\Organizer;
use Horde\Itip\Action\SendCancel;
use Horde\Itip\Action\SendReply;
use Horde\Itip\Action\SendRequest;
use Horde\Itip\Change\CancelEvent;
use Horde\Itip\Change\CancelInstance;
use Horde\Itip\Change\CreateEvent;
use Horde\Itip\Change\UpdateAttendeeStatus;
use Horde\Itip\Change\UpdateEvent;
use Horde\Itip\Conflict\MissingRequiredProperty;
use Horde\Itip\Conflict\OutdatedSequence;
use Horde\Itip\Conflict\UnknownAttendee;
use Horde\Itip\Exception\ItipException;

/**
 * The iTIP decision engine.
 *
 * Processes incoming iTIP messages (REQUEST, REPLY, CANCEL) and returns
 * an ItipResult describing proposed calendar changes, required outbound
 * actions, and any conflicts detected. The engine is pure logic — it
 * performs no I/O and dispatches no events itself.
 */
final class ItipProcessor
{
    public function __construct(
        private CalendarState $state,
        private SchedulingPolicy $policy,
    ) {}

    /**
     * Process an incoming iTIP message and return the decision result.
     */
    public function process(ItipMessage $message): ItipResult
    {
        return match ($message->method->value) {
            CalendarMethod::REQUEST => $this->processRequest($message),
            CalendarMethod::REPLY => $this->processReply($message),
            CalendarMethod::CANCEL => $this->processCancel($message),
            default => new ItipResult(),
        };
    }

    /**
     * Generate a METHOD=REQUEST VCalendar for sending to attendees.
     */
    public function generateRequest(Vevent $event, string $organizerEmail): VCalendar
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setProdid('-//Horde//Horde iTIP Engine//EN');
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $clone = clone $event;
        $clone->setDtstamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $cal->addChild($clone);

        return $cal;
    }

    /**
     * Generate a METHOD=REPLY VCalendar for sending to an organizer.
     */
    public function generateReply(
        Vevent $event,
        string $attendeeEmail,
        ParticipationStatus $status,
    ): VCalendar {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setProdid('-//Horde//Horde iTIP Engine//EN');
        $cal->setMethod(CalendarMethod::from('REPLY'));

        $reply = new Vevent();
        $uid = $event->getUid();
        if ($uid !== null) {
            $reply->setUid($uid);
        }
        $reply->setSequence($event->getSequence());
        $reply->setDtstamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        $organizer = $event->getOrganizer();
        if ($organizer !== null) {
            $reply->setOrganizer(Organizer::create($organizer->getEmail()));
        }

        $reply->addAttendee(Attendee::create($attendeeEmail, null, $status));
        $cal->addChild($reply);

        return $cal;
    }

    /**
     * Generate a METHOD=CANCEL VCalendar for sending to attendees.
     */
    public function generateCancel(Vevent $event, string $organizerEmail): VCalendar
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setProdid('-//Horde//Horde iTIP Engine//EN');
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $clone = clone $event;
        $clone->setStatus(EventStatus::from('CANCELLED'));
        $clone->setSequence($event->getSequence() + 1);
        $clone->setDtstamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $cal->addChild($clone);

        return $cal;
    }

    /**
     * Process an incoming REQUEST: create or update an event.
     */
    private function processRequest(ItipMessage $message): ItipResult
    {
        $event = $message->getFirstEvent();
        if ($event === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('VEVENT', 'REQUEST requires a VEVENT component'),
            ]);
        }

        $uid = $event->getUid();
        if ($uid === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('UID', 'VEVENT must have a UID'),
            ]);
        }

        $existing = $this->state->findEventByUid($uid);
        $incomingSequence = $event->getSequence();

        if ($existing !== null) {
            $existingSequence = $this->state->getEventSequence($uid) ?? 0;
            if ($incomingSequence < $existingSequence) {
                return new ItipResult(conflicts: [
                    new OutdatedSequence($incomingSequence, $existingSequence),
                ]);
            }
        }

        if (!$this->policy->shouldAcceptUpdate($message, $existing)) {
            return new ItipResult();
        }

        $changes = [];
        $actions = [];

        $organizerObj = $event->getOrganizer();
        $organizerEmail = $organizerObj !== null ? strtolower($organizerObj->getEmail()) : '';

        if ($existing === null) {
            $changes[] = new CreateEvent($event, $organizerEmail);
        } else {
            $changes[] = new UpdateEvent($uid, $event, $incomingSequence);
        }

        $autoResponse = $this->policy->shouldAutoRespond($message);
        if ($autoResponse !== null) {
            $replyCal = $this->generateReply($event, $message->actorEmail, $autoResponse);
            $action = new SendReply($organizerEmail, $message->actorEmail, $replyCal);
            if ($this->policy->shouldSendNotification($action)) {
                $actions[] = $action;
            }
        }

        return new ItipResult(changes: $changes, actions: $actions);
    }

    /**
     * Process an incoming REPLY: update attendee participation status.
     */
    private function processReply(ItipMessage $message): ItipResult
    {
        $event = $message->getFirstEvent();
        if ($event === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('VEVENT', 'REPLY requires a VEVENT component'),
            ]);
        }

        $uid = $event->getUid();
        if ($uid === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('UID', 'VEVENT must have a UID'),
            ]);
        }

        $existing = $this->state->findEventByUid($uid);
        if ($existing === null) {
            return new ItipResult(conflicts: [
                new UnknownAttendee($message->actorEmail, $uid),
            ]);
        }

        $incomingSequence = $event->getSequence();
        $existingSequence = $this->state->getEventSequence($uid) ?? 0;
        if ($incomingSequence < $existingSequence) {
            return new ItipResult(conflicts: [
                new OutdatedSequence($incomingSequence, $existingSequence),
            ]);
        }

        $attendees = $event->getAttendees();
        $replyAttendee = $this->findAttendeeByEmail($attendees, $message->actorEmail);

        if ($replyAttendee === null) {
            return new ItipResult(conflicts: [
                new UnknownAttendee($message->actorEmail, $uid),
            ]);
        }

        $newStatus = $replyAttendee->getParticipationStatus();

        return new ItipResult(changes: [
            new UpdateAttendeeStatus($uid, $message->actorEmail, $newStatus),
        ]);
    }

    /**
     * Process an incoming CANCEL: cancel event or instance.
     */
    private function processCancel(ItipMessage $message): ItipResult
    {
        $event = $message->getFirstEvent();
        if ($event === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('VEVENT', 'CANCEL requires a VEVENT component'),
            ]);
        }

        $uid = $event->getUid();
        if ($uid === null) {
            return new ItipResult(conflicts: [
                new MissingRequiredProperty('UID', 'VEVENT must have a UID'),
            ]);
        }

        $existing = $this->state->findEventByUid($uid);
        $incomingSequence = $event->getSequence();

        if ($existing !== null) {
            $existingSequence = $this->state->getEventSequence($uid) ?? 0;
            if ($incomingSequence < $existingSequence) {
                return new ItipResult(conflicts: [
                    new OutdatedSequence($incomingSequence, $existingSequence),
                ]);
            }
        }

        if (!$this->policy->shouldAcceptUpdate($message, $existing)) {
            return new ItipResult();
        }

        $recurrenceId = $event->getPropertyValue('RECURRENCE-ID');

        if ($recurrenceId !== null) {
            return new ItipResult(changes: [
                new CancelInstance($uid, $recurrenceId, $incomingSequence),
            ]);
        }

        return new ItipResult(changes: [
            new CancelEvent($uid, $incomingSequence),
        ]);
    }

    /**
     * @param list<Attendee> $attendees
     */
    private function findAttendeeByEmail(array $attendees, string $email): ?Attendee
    {
        $normalized = strtolower($email);
        foreach ($attendees as $attendee) {
            if (strtolower($attendee->getEmail()) === $normalized) {
                return $attendee;
            }
        }
        return null;
    }
}
