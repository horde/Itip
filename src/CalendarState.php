<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @author    Jan Schneider <jan@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2003-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Itip
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

    /**
     * Find all recurrence instances of an event by its UID.
     *
     * Used by the ADD method to validate new instances against existing ones.
     *
     * @return list<Vevent>
     */
    public function findEventInstances(string $uid): array;
}
