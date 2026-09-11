<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SignService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SignServiceWorkspaceConfigTest extends TestCase
{
	public function testSignatureServiceReadsWorkspaceIdAsStoredString(): void
	{
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->willReturnCallback(static function ($app, $key): int {
			if ($key === 'workspace_id') {
				throw new \RuntimeException('Workspace ID is stored as a string');
			}
			return 1;
		});
		$config->method('getValueString')->willReturnCallback(static fn ($app, $key): string => $key === 'workspace_id' ? '42' : '');
		$reflection = new ReflectionClass(SignService::class);
		$args = [];
		foreach ($reflection->getConstructor()->getParameters() as $parameter) {
			$type = $parameter->getType();
			if ($type->isBuiltin()) {
				$args[] = null;
			} elseif ($type->getName() === IAppConfig::class) {
				$args[] = $config;
			} elseif ($type->getName() === ConfigurationService::class) {
				$args[] = new ConfigurationService($config);
			} else {
				$args[] = $this->createMock($type->getName());
			}
		}
		$service = $reflection->newInstanceArgs($args);
		$property = $reflection->getProperty('workspaceId');
		$property->setAccessible(true);
		self::assertSame(42, $property->getValue($service));
	}
}
