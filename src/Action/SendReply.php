<?php

declare(strict_types=1);

namespace Horde\Itip\Action;

use Horde\Icalendar\Calendar\VCalendar;

/**
 * A REPLY should be sent to the organizer.
 */
final readonly class SendReply implements RequiredAction
{
    public function __construct(
        public string $organizerEmail,
        public string $attendeeEmail,
        public VCalendar $replyCalendar,
    ) {}
}
