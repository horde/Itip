<?php
/**
 * Test the vEvent iCal handling.
 *
 * Copyright 2010 Kolab Systems AG
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
namespace Horde\Itip\Integration\Unit\Event;
use PHPUnit\Framework\TestCase;
use \Horde_Icalendar;
use \Horde_Itip_Event_Vevent;

class VeventTest extends TestCase
{
    public function testGetMethodReturnsMethod()
    {
        $inv = Horde_Icalendar::newComponent('VEVENT', false);
        $inv->setAttribute('METHOD', 'TEST');
        $vevent = new Horde_Itip_Event_Vevent($inv);
        $this->assertEquals('TEST', $vevent->getMethod());
    }

    public function testGetMethodReturnsDefaultMethod()
    {
        $inv = Horde_Icalendar::newComponent('VEVENT', false);
        $vevent = new Horde_Itip_Event_Vevent($inv);
        $this->assertEquals('REQUEST', $vevent->getMethod());
    }

}
