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

use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\ParticipationStatus;
use Horde\Itip\Action\RequiredAction;

/**
 * Default scheduling policy — accepts all updates, never auto-responds,
 * and allows all notifications.
 *
 * This permissive default is appropriate for applications that want the
 * engine to produce full results and handle policy decisions themselves.
 */
final class DefaultSchedulingPolicy implements SchedulingPolicy
{
    /** {@inheritdoc} */
    public function shouldAcceptUpdate(ItipMessage $message, ?Vevent $existing): bool
    {
        return true;
    }

    /** {@inheritdoc} */
    public function shouldAutoRespond(ItipMessage $message): ?ParticipationStatus
    {
        return null;
    }

    /** {@inheritdoc} */
    public function shouldSendNotification(RequiredAction $action): bool
    {
        return true;
    }

    /** {@inheritdoc} */
    public function shouldAcceptPublish(ItipMessage $message, ?Vevent $existing): bool
    {
        return true;
    }

    /** {@inheritdoc} */
    public function shouldAcceptCounter(ItipMessage $message, Vevent $existing): ?bool
    {
        return null;
    }
}
