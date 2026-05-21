<?php

declare(strict_types=1);

namespace Horde\Itip\Change;

use Horde\Icalendar\Enum\ParticipationStatus;

/**
 * An attendee's participation status should be updated (from incoming REPLY).
 */
final readonly class UpdateAttendeeStatus implements ProposedChange
{
    public function __construct(
        public string $uid,
        public string $attendeeEmail,
        public ParticipationStatus $newStatus,
    ) {}
}
