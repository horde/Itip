<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @author    Jan Schneider <jan@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2010-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Itip
 */

namespace Horde\Itip\Action;

use Horde\Icalendar\Calendar\VCalendar;

/**
 * An ADD should be sent to attendees (new instances of a recurring event).
 */
final readonly class SendAdd implements RequiredAction
{
    /**
     * @param list<string> $attendeeEmails
     */
    public function __construct(
        public string $organizerEmail,
        public array $attendeeEmails,
        public VCalendar $addCalendar,
    ) {}
}
