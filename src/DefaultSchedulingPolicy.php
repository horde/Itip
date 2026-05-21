<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
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
    public function shouldAcceptUpdate(ItipMessage $message, ?Vevent $existing): bool
    {
        return true;
    }

    public function shouldAutoRespond(ItipMessage $message): ?ParticipationStatus
    {
        return null;
    }

    public function shouldSendNotification(RequiredAction $action): bool
    {
        return true;
    }
}
