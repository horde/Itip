<?php

declare(strict_types=1);

namespace Horde\Itip\Conflict;

/**
 * The actor is not found in the attendee list of the event.
 */
final readonly class UnknownAttendee implements Conflict
{
    public function __construct(
        public string $email,
        public string $uid,
    ) {}
}
