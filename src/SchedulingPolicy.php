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

    /**
     * Whether an incoming PUBLISH should be accepted.
     *
     * PUBLISH has no scheduling relationship — this controls whether
     * the published event should be stored at all.
     */
    public function shouldAcceptPublish(ItipMessage $message, ?Vevent $existing): bool;

    /**
     * Whether an incoming COUNTER should be accepted, declined, or left for manual review.
     *
     * @return bool|null  true = accept counter-proposal, false = decline, null = manual review
     */
    public function shouldAcceptCounter(ItipMessage $message, Vevent $existing): ?bool;
}
