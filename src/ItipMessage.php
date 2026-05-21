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

use Horde\Icalendar\Calendar\VCalendar;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Calendar\Vtodo;
use Horde\Icalendar\Enum\CalendarMethod;

/**
 * Typed wrapper for an incoming iTIP message (VCalendar with METHOD).
 */
final readonly class ItipMessage
{
    public function __construct(
        public VCalendar $calendar,
        public CalendarMethod $method,
        public string $actorEmail,
    ) {}

    public static function fromCalendar(VCalendar $cal, string $actorEmail): self
    {
        $method = $cal->getMethod();
        if ($method === null) {
            throw new Exception\ItipException('VCalendar has no METHOD property');
        }

        return new self($cal, $method, strtolower($actorEmail));
    }

    public function getFirstEvent(): ?Vevent
    {
        $events = $this->calendar->getEvents();
        return $events[0] ?? null;
    }

    public function getFirstTodo(): ?Vtodo
    {
        $todos = $this->calendar->getTodos();
        return $todos[0] ?? null;
    }
}
