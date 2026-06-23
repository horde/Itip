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

namespace Horde\Itip\Test\Unit;

use Horde\Itip\Generator\MimeEnvelopeBuilder;
use Horde_Mime_Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeEnvelopeBuilder::class)]
class MimeEnvelopeBuilderTest extends TestCase
{
    public function testBuildCreatesMultipartAlternativeWithCalendarPart(): void
    {
        $builder = new MimeEnvelopeBuilder();
        $message = $builder->build(
            'Plain invitation',
            '<p>HTML invitation</p>',
            "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nEND:VCALENDAR\r\n",
            'REQUEST'
        );

        $this->assertSame('multipart/alternative', $message->getType());

        $types = [];
        foreach ($message->getParts() as $part) {
            $types[] = $part->getType();
        }

        $this->assertSame(['text/plain', 'text/html', 'text/calendar'], $types);

        $calendar = $message->getPart(3);
        $this->assertInstanceOf(Horde_Mime_Part::class, $calendar);
        $this->assertSame('REQUEST', $calendar->getContentTypeParameter('method'));
        $this->assertSame('inline', $calendar->getDisposition());
    }
}
