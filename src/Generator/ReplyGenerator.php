<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip\Generator;

use DateTimeImmutable;
use DateTimeZone;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\Enum\ParticipationStatus;
use Horde\Icalendar\Value\Attendee;
use Horde\Icalendar\Value\Organizer;

/**
 * Generates a METHOD=REPLY VCalendar for responding to an invitation.
 */
final class ReplyGenerator implements MessageGenerator
{
    public function __construct(
        private string $attendeeEmail,
        private ParticipationStatus $status,
    ) {}

    public function generate(Vevent $event): VCalendar
    {
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

        $reply->addAttendee(Attendee::create($this->attendeeEmail, null, $this->status));
        $cal->addChild($reply);

        return $cal;
    }
}
