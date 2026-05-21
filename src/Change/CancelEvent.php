<?php

declare(strict_types=1);

namespace Horde\Itip\Change;

/**
 * An event should be cancelled (entire event deleted/marked cancelled).
 */
final readonly class CancelEvent implements ProposedChange
{
    public function __construct(
        public string $uid,
        public int $sequence,
    ) {}
}
