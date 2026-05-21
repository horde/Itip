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
 * Interface for querying existing calendar state.
 *
 * The iTIP processor consults this to determine whether an event
 * already exists, what its current SEQUENCE is, and what an attendee's
 * current participation status is.
 *
 * Implementations are provided by the application layer (e.g. Kronolith).
 */
interface CalendarState
{
    /**
     * Find an existing event by its UID.
     */
    public function findEventByUid(string $uid): ?Vevent;

    /**
     * Get the current participation status of an attendee for an event.
     */
    public function getAttendeeStatus(string $uid, string $email): ?ParticipationStatus;

    /**
     * Get the current SEQUENCE number of an existing event.
     */
    public function getEventSequence(string $uid): ?int;
}
