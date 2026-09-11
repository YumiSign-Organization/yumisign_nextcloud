<?php

declare(strict_types=1);

$ncPath = dirname(__DIR__, 3);

require_once $ncPath . '/lib/base.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
	$prefix = 'OCA\\YumiSignNxtC\\Tests\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relativeClass = substr($class, strlen($prefix));
	$file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
	if (file_exists($file)) {
		require_once $file;
	}
});
