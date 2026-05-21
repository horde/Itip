<?php

declare(strict_types=1);

namespace Horde\Itip\Change;

use DateTimeImmutable;

/**
 * A single recurrence instance should be cancelled.
 */
final readonly class CancelInstance implements ProposedChange
{
    public function __construct(
        public string $uid,
        public string $recurrenceId,
        public int $sequence,
    ) {}
}
