<?php

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__.'/../../../lib/base.php';

// Fix for "Autoload path not allowed: .../tests/lib/testcase.php"
\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

// Fix for "Autoload path not allowed: .../yumisign_nextcloud/tests/testcase.php"
\OC_App::loadApp('yumisign_nextcloud');

if(!class_exists('PHPUnit_Framework_TestCase')) {
    require_once('PHPUnit/Autoload.php');
}

OC_Hook::clear();
