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

use Horde\Itip\Action\RequiredAction;
use Horde\Itip\Change\ProposedChange;
use Horde\Itip\Conflict\Conflict;

/**
 * The output of iTIP processing — a pure value object describing
 * what should happen, without performing any mutations.
 */
final readonly class ItipResult
{
    /**
     * @param list<ProposedChange> $changes
     * @param list<RequiredAction> $actions
     * @param list<Conflict> $conflicts
     */
    public function __construct(
        public array $changes = [],
        public array $actions = [],
        public array $conflicts = [],
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    public function isAccepted(): bool
    {
        return !$this->hasConflicts();
    }
}
