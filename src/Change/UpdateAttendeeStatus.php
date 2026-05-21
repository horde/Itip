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

namespace Horde\Itip\Change;

use Horde\Icalendar\Enum\ParticipationStatus;

/**
 * An attendee's participation status should be updated (from incoming REPLY).
 */
final readonly class UpdateAttendeeStatus implements ProposedChange
{
    public function __construct(
        public string $uid,
        public string $attendeeEmail,
        public ParticipationStatus $newStatus,
    ) {}
}
