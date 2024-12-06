<?php


require_once './vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('build')
	->notPath('l10n')
	->notPath('src')
	->notPath('lib/Vendor')
	->notPath('vendor')
	->notPath('vendor-bin')
	->in(__DIR__);
return $config;
