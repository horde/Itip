<?php

/**
 * Test the itip response handling.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Itip
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Itip\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Horde_Mail_Transport_Mock;
use Horde_Icalendar;
use Horde_Itip;
use Horde_Itip_Resource_Base;
use Horde_Itip_Response_Type_Accept;
use Horde_Itip_Response_Options_Kolab;
use Horde_Itip_Resource_Identity;
use Horde_Itip_Stub_Identity;
use Horde_Itip_Response_Type_Decline;
use Horde_Itip_Response_Type_Tentative;
use Horde_Mime_Part;
use Horde_Itip_Response_Options_Horde;

/**
 * Test the itip response handling.
 *
 * Copyright 2010-2026 Kolab Systems AG
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did not
 * receive this file, see
 * http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @package    Itip
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(Horde_Itip::class)]
#[UsesClass(Horde_Itip_Resource_Base::class)]
#[UsesClass(Horde_Itip_Resource_Identity::class)]
#[UsesClass(Horde_Itip_Response_Type_Accept::class)]
#[UsesClass(Horde_Itip_Response_Type_Decline::class)]
#[UsesClass(Horde_Itip_Response_Type_Tentative::class)]
#[UsesClass(Horde_Itip_Response_Options_Kolab::class)]
#[UsesClass(Horde_Itip_Response_Options_Horde::class)]
class ItipTest extends TestCase
{
    private Horde_Mail_Transport_Mock $_transport;

    public static function setUpBeforeClass(): void
    {
        setlocale(LC_ALL, 'C');
    }

    public static function tearDownAfterClass(): void
    {
        setlocale(LC_ALL, '');
    }

    public function setUp(): void
    {
        $this->_transport = new Horde_Mail_Transport_Mock();
    }

    public function testMinimalItipHandlingSteps()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertEquals($reply->getAttribute('ATTENDEE'), 'mailto:test@example.org');
    }

    public function testForCopiedSequenceIdFromRequestToResponse()
    {
        $inv = $this->_getInvitation();
        $inv->setAttribute('SEQUENCE', 555);
        $iTip = $this->_getItip($inv);
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('SEQUENCE'), 555);
    }

    public function testForCopiedStartTimeFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertEquals(1222419600, $reply->getAttribute('DTSTART'));
    }

    public function testForCopiedEndTimeFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertEquals(1222423200, $reply->getAttribute('DTEND'));
    }

    public function testForCopiedDurationFromRequestToResponse()
    {
        $vCal = new Horde_Icalendar();
        $inv = Horde_Icalendar::newComponent('VEVENT', $vCal);
        $inv->setAttribute('METHOD', 'REQUEST');
        $inv->setAttribute('UID', '1');
        $inv->setAttribute('SUMMARY', 'Test Invitation');
        $inv->setAttribute('DESCRIPTION', 'You are invited');
        $inv->setAttribute('ORGANIZER', 'orga@example.org');
        $inv->setAttribute('DTSTART', '20080926T110000');
        $inv->setAttribute('DURATION', 3600);
        $iTip = $this->_getItip($inv);
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('DURATION'), 3600);
    }

    public function testForCopiedOrganizerFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('ORGANIZER'), 'orga@example.org');
    }

    public function testForCopiedLocationFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('LOCATION'), 'Somewhere');
    }

    public function testForCopiedDescriptionFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('DESCRIPTION'), 'You are invited');
    }

    public function testForCopiedUidFromRequestToResponse()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource())
        );
        $this->assertSame($reply->getAttribute('UID'), '1');
    }

    public function testIcalendarResponseHasMethodReply()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getIcalendarResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab()
        );
        $this->assertEquals($reply->getAttribute('METHOD'), 'REPLY');
    }

    public function testCounterIcalendarHasMethodCounterAndProposedTimes()
    {
        $response = Horde_Itip::prepareResponse(
            $this->_getInvitation(),
            $this->_getResource()
        );
        $proposedStart = new \Horde_Date(1222506000);
        $proposedEnd = new \Horde_Date(1222509600);
        $counter = $response->getCounterIcalendar(
            new Horde_Itip_Response_Type_Tentative($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $proposedStart,
            $proposedEnd
        );

        $this->assertSame('COUNTER', $counter->getAttribute('METHOD'));
        $vevent = $counter->findComponent('vEvent');
        $dtstart = $vevent->getAttribute('DTSTART');
        $dtend = $vevent->getAttribute('DTEND');
        $this->assertInstanceOf(\Horde_Date::class, $dtstart);
        $this->assertInstanceOf(\Horde_Date::class, $dtend);
        $this->assertSame('20080927T090000Z', $dtstart->format('Ymd\THis\Z'));
        $this->assertSame('20080927T100000Z', $dtend->format('Ymd\THis\Z'));
        $this->assertSame('TENTATIVE', $vevent->getAttribute('ATTENDEE', true)[0]['PARTSTAT']);
    }

    public function testSendCounterMultiPartResponseUsesCounterMethod()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $this->_getItip()->sendCounterMultiPartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport,
            new \Horde_Date(1222506000),
            new \Horde_Date(1222509600)
        );

        $this->assertStringContainsString(
            'METHOD=COUNTER',
            $this->_transport->sentMessages[0]['body']
        );
    }

    public function testMessageResponseHasFromAddress()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );

        $this->assertStringContainsString(
            'From: Mister Test <test@example.org>',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageResponseWithIdentityResourceHasFromAddress()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $invitation = $this->_getInvitation();
        $resource = new Horde_Itip_Resource_Identity(
            new Horde_Itip_Stub_Identity(),
            'mailto:test@example.org',
            'test'
        );
        $iTip = Horde_Itip::factory(
            $invitation,
            $resource
        );
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($resource),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );

        $this->assertStringContainsString(
            'From: "Mr. Test" <test@example.org>',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageResponseWithDefaultIdentityResourceHasDefaultFromAddress()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $invitation = $this->_getInvitation();
        $resource = new Horde_Itip_Resource_Identity(
            new Horde_Itip_Stub_Identity(),
            'mailto:default@example.org',
            'default'
        );
        $iTip = Horde_Itip::factory(
            $invitation,
            $resource
        );
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($resource),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );

        $this->assertStringContainsString(
            'From: default@example.org',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageResponseHasToAddress()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );

        $this->assertStringContainsString(
            'To: orga@example.org',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageAcceptResponseHasAcceptSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Accepted: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageDeclineResponseHasDeclineSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Decline($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Declined: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageTentativeResponseHasTentativeSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Tentative($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Tentative: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMessageResponseAllowsAddingCommentsToTheSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendSinglepartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource(), 'info'),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Accepted [info]: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testAttendeeHoldsInformationAboutMailAddress()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab()
        );
        $this->assertEquals($reply->getAttribute('ATTENDEE'), 'mailto:test@example.org');
    }

    public function testAttendeeHoldsInformationAboutCommonNameAndStatus()
    {
        $iTip = $this->_getItip();
        $reply = $iTip->getVeventResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab()
        );
        $parameters = $reply->getAttribute('ATTENDEE', true);
        $this->assertEquals(
            array_pop($parameters),
            [
                'CN' => 'Mister Test',
                'PARTSTAT' => 'ACCEPTED',
            ]
        );
    }

    public function testMultipartMessageResponseHoldsMultipleParts()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendMultipartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource(), 'info'),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $mail = $this->_transport->sentMessages[0]['header_text'] . "\r\n\r\n";
        $mail .= $this->_transport->sentMessages[0]['body'];
        $part = Horde_Mime_Part::parseMessage($mail);
        $this->assertEquals(2, count($part->getParts()));
    }

    public function testMultipartMessageDeclineResponseHasDeclineSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendMultipartResponse(
            new Horde_Itip_Response_Type_Decline($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Declined: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMultipartMessageTentativeResponseHasTentativeSubject()
    {
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendMultipartResponse(
            new Horde_Itip_Response_Type_Tentative($this->_getResource()),
            new Horde_Itip_Response_Options_Kolab(),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Subject: Tentative: Test',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    public function testMultipartMessageWithHordeOptionsHasMessageId()
    {
        $_SERVER['REMOTE_ADDR'] = 'none';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $iTip = $this->_getItip();
        $reply = $iTip->sendMultipartResponse(
            new Horde_Itip_Response_Type_Accept($this->_getResource(), 'info'),
            new Horde_Itip_Response_Options_Horde('UTF-8', []),
            $this->_transport
        );
        $this->assertStringContainsString(
            'Message-ID:',
            $this->_transport->sentMessages[0]['header_text']
        );
    }

    private function _getItip($invitation = null)
    {
        if ($invitation === null) {
            $invitation = $this->_getInvitation();
        }
        return Horde_Itip::factory(
            $invitation,
            $this->_getResource()
        );
    }

    private function _getInvitation()
    {
        $vCal = new Horde_Icalendar();
        $inv = Horde_Icalendar::newComponent('VEVENT', $vCal);
        $inv->setAttribute('METHOD', 'REQUEST');
        $inv->setAttribute('UID', '1');
        $inv->setAttribute('SUMMARY', 'Test Invitation');
        $inv->setAttribute('DESCRIPTION', 'You are invited');
        $inv->setAttribute('LOCATION', 'Somewhere');
        $inv->setAttribute('ORGANIZER', 'orga@example.org');
        $inv->setAttribute('DTSTART', 1222419600);
        $inv->setAttribute('DTEND', 1222423200);
        return $inv;
    }

    private function _getResource($mail = null, $cn = null)
    {
        if ($mail === null) {
            $mail = 'test@example.org';
        }
        if ($cn === null) {
            $cn = 'Mister Test';
        }
        return new Horde_Itip_Resource_Base($mail, $cn);
    }
}
