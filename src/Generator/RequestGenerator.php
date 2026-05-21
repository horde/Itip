<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip\Generator;

use DateTimeImmutable;
use DateTimeZone;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\CalendarMethod;

/**
 * Generates a METHOD=REQUEST VCalendar for sending invitations.
 */
final class RequestGenerator implements MessageGenerator
{
    public function __construct(
        private string $organizerEmail,
    ) {}

    public function generate(Vevent $event): VCalendar
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setProdid('-//Horde//Horde iTIP Engine//EN');
        $cal->setMethod(CalendarMethod::from('REQUEST'));

        $clone = clone $event;
        $clone->setDtstamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $cal->addChild($clone);

        return $cal;
    }
}
