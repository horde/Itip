<?php
/**
 * Test the base response definition.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Itip
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
namespace Horde\Itip\Integration\Unit\Response\Type;
use PHPUnit\Framework\TestCase;
use \Horde_Itip_Response_Type_Accept;
use \Horde_Itip_Resource_Base;

/**
 * Test the base response definition.
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
class BaseTest extends TestCase
{

    public function testExceptionOnUndefinedRequest()
    {
        $this->expectException('Horde_Itip_Exception');
        $type = new Horde_Itip_Response_Type_Accept(
            new Horde_Itip_Resource_Base('', '')
        );
        $type->getRequest();
    }
}
