<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip;

use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\ParticipationStatus;

/**
 * Null implementation of CalendarState — reports no existing events.
 *
 * Useful for testing and for scenarios where only outbound generation
 * is needed (no existing calendar to query).
 */
final class NullCalendarState implements CalendarState
{
    public function findEventByUid(string $uid): ?Vevent
    {
        return null;
    }

    public function getAttendeeStatus(string $uid, string $email): ?ParticipationStatus
    {
        return null;
    }

    public function getEventSequence(string $uid): ?int
    {
        return null;
    }
}
