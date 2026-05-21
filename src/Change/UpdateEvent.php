<?php

declare(strict_types=1);

namespace Horde\Itip\Change;

use Horde\Icalendar\Calendar\Vevent;

/**
 * An existing event should be updated from an incoming REQUEST.
 */
final readonly class UpdateEvent implements ProposedChange
{
    public function __construct(
        public string $uid,
        public Vevent $updatedEvent,
        public int $newSequence,
    ) {}
}
