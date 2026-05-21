<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip\Generator;

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;

/**
 * Interface for building outgoing iTIP VCalendar messages.
 */
interface MessageGenerator
{
    public function generate(Vevent $event): VCalendar;
}
