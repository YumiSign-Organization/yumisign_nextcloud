<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogRCDevsTest extends TestCase
{
	public function testConstructorFallsBackToNextcloudLoggerWhenCustomLogIsUnavailable(): void
	{
		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);

		/** @var LoggerInterface&MockObject $logger */
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('warning')
			->with(self::stringContains('Custom log unavailable'));

		$configurationService = new class($appConfig) extends ConfigurationService {
			public function getLogConfig(): string
			{
				return '';
			}

			public function getLogFilename(): string
			{
				return '';
			}
		};

		self::assertInstanceOf(LogRCDevs::class, new LogRCDevs($configurationService, $logger));
	}
}
