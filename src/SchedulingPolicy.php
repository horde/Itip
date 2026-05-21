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
 * Interface for application-injected scheduling rules.
 *
 * The iTIP processor consults this to determine whether to accept
 * incoming updates, whether to auto-respond, and whether outbound
 * notifications should be sent. Applications implement this to
 * encode their specific business rules.
 */
interface SchedulingPolicy
{
    /**
     * Whether an incoming update should be accepted.
     *
     * Called for REQUEST and CANCEL methods after SEQUENCE validation passes.
     */
    public function shouldAcceptUpdate(ItipMessage $message, ?Vevent $existing): bool;

    /**
     * Whether to automatically respond and with what status.
     *
     * Called for incoming REQUEST. If this returns a ParticipationStatus,
     * the engine will add a SendReply action to the result.
     * Return null to indicate no auto-response.
     */
    public function shouldAutoRespond(ItipMessage $message): ?ParticipationStatus;

    /**
     * Whether a required outbound action should actually be sent.
     *
     * Called before adding actions like SendRequest, SendReply, SendCancel
     * to the result. Return false to suppress the notification.
     */
    public function shouldSendNotification(RequiredAction $action): bool;
}
