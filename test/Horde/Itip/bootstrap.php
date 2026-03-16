<?php

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoload)) {
    $loader = require_once $autoload;
    // Register test namespace for PSR-4 autoloading
    $loader->addPsr4('Horde\\Itip\\', __DIR__);
}

// Load the Stub/Identity class manually since it uses old-style class naming
$stubIdentity = __DIR__ . '/Stub/Identity.php';
if (file_exists($stubIdentity)) {
    require_once $stubIdentity;
}
