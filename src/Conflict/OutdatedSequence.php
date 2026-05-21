<?php

declare(strict_types=1);

namespace Horde\Itip\Conflict;

/**
 * The incoming message has an outdated SEQUENCE number.
 */
final readonly class OutdatedSequence implements Conflict
{
    public function __construct(
        public int $incomingSequence,
        public int $existingSequence,
    ) {}
}
