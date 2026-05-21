<?php

declare(strict_types=1);

namespace Horde\Itip\Action;

use Horde\Icalendar\Calendar\VCalendar;

/**
 * A CANCEL should be sent to attendees.
 */
final readonly class SendCancel implements RequiredAction
{
    /**
     * @param list<string> $attendeeEmails
     */
    public function __construct(
        public string $organizerEmail,
        public array $attendeeEmails,
        public VCalendar $cancelCalendar,
    ) {}
}
