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

namespace Horde\Itip\Generator;

use DateTimeImmutable;
use DateTimeZone;
use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\CalendarMethod;
use Horde\Icalendar\Enum\EventStatus;
use Horde\Icalendar\Value\Organizer;

/**
 * Generates a METHOD=CANCEL VCalendar for cancelling an event.
 */
final class CancelGenerator implements MessageGenerator
{
    public function __construct(
        private string $organizerEmail,
    ) {}

    public function generate(Vevent $event): VCalendar
    {
        $cal = new VCalendar();
        $cal->setVersion();
        $cal->setProdid('-//Horde//Horde iTIP Engine//EN');
        $cal->setMethod(CalendarMethod::from('CANCEL'));

        $clone = clone $event;
        $clone->setOrganizer(Organizer::create($this->organizerEmail));
        $clone->setStatus(EventStatus::from('CANCELLED'));
        $clone->setSequence($event->getSequence() + 1);
        $clone->setDtstamp(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $cal->addChild($clone);

        return $cal;
    }
}
