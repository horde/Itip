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

namespace Horde\Itip\Event;

use Horde\Itip\ItipMessage;
use Horde\Itip\ItipResult;

/**
 * Base class for iTIP domain events dispatched via PSR-14.
 *
 * These events are dispatched by the application layer after it has applied
 * the ItipResult changes. Listeners handle transport (iMIP email, CalDAV
 * push, logging, etc).
 */
abstract class ItipEvent
{
    public function __construct(
        public readonly ItipMessage $message,
        public readonly ItipResult $result,
    ) {}
}
