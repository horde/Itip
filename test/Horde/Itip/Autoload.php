<?php
/**
 * Setup autoloading for the tests.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Itip
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/** Load dependencies from the test suite */
$autoload = __DIR__ . '/Stub/Identity.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
