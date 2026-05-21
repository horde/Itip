<?php

declare(strict_types=1);

namespace Horde\Itip\Conflict;

/**
 * A required property is missing from the iTIP message.
 */
final readonly class MissingRequiredProperty implements Conflict
{
    public function __construct(
        public string $propertyName,
        public string $context,
    ) {}
}
