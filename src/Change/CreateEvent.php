<?php

declare(strict_types=1);

namespace Horde\Itip\Change;

use Horde\Icalendar\Calendar\Vevent;

/**
 * A new event should be created from an incoming REQUEST.
 */
final readonly class CreateEvent implements ProposedChange
{
    public function __construct(
        public Vevent $event,
        public string $organizerEmail,
    ) {}
}
