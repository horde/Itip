<?php

declare(strict_types=1);

namespace Horde\Itip\Action;

use Horde\Icalendar\Calendar\VCalendar;

/**
 * A REQUEST should be sent to attendees.
 */
final readonly class SendRequest implements RequiredAction
{
    /**
     * @param list<string> $attendeeEmails
     */
    public function __construct(
        public string $organizerEmail,
        public array $attendeeEmails,
        public VCalendar $requestCalendar,
    ) {}
}
