<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
	private ConfigurationService $configurationService;

	protected function setUp(): void
	{
		parent::setUp();

		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);
		$this->configurationService = new ConfigurationService($appConfig);
	}

	public function testCheckCronTargetsYumiSignJob(): void
	{
		self::assertSame(
			'OCA\\YumiSignNxtC\\BackgroundJob\\CheckAsyncSignatureTask',
			$this->configurationService->getCheckCron(),
		);
	}

	public function testDebugVueJsConfigIsBoolean(): void
	{
		self::assertIsBool($this->configurationService->getDebugVueJs());
	}
}
