<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Itip
 */

namespace Horde\Itip\Generator;

use Horde_Mime_Part;

/**
 * Builds a canonical multipart/alternative iTIP invitation envelope.
 */
final class MimeEnvelopeBuilder
{
    /**
     * @param string $plainBody  text/plain body.
     * @param string $htmlBody   text/html body.
     * @param string $icsData    iCalendar payload.
     * @param string $method     iTIP METHOD value (REQUEST, CANCEL, …).
     */
    public function build(
        string $plainBody,
        string $htmlBody,
        string $icsData,
        string $method,
    ): Horde_Mime_Part {
        $multipart = new Horde_Mime_Part();
        $multipart->setType('multipart/alternative');

        $bodyText = new Horde_Mime_Part();
        $bodyText->setType('text/plain');
        $bodyText->setCharset('UTF-8');
        $bodyText->setContents($plainBody);
        $bodyText->setDisposition('inline');
        $multipart->addPart($bodyText);

        $bodyHtml = new Horde_Mime_Part();
        $bodyHtml->setType('text/html');
        $bodyHtml->setCharset('UTF-8');
        $bodyHtml->setContents($htmlBody);
        $bodyHtml->setDisposition('inline');
        $multipart->addPart($bodyHtml);

        $icsInline = new Horde_Mime_Part();
        $icsInline->setType('text/calendar');
        $icsInline->setContents($icsData);
        $icsInline->setContentTypeParameter('method', $method);
        $icsInline->setCharset('UTF-8');
        $icsInline->setDisposition('inline');
        $icsInline->setEOL("\r\n");
        $multipart->addPart($icsInline);

        return $multipart;
    }
}
