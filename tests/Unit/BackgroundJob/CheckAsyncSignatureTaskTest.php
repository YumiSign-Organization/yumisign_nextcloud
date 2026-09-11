<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\BackgroundJob;

use OCA\YumiSignNxtC\BackgroundJob\CheckAsyncSignatureTask;
use OCA\YumiSignNxtC\Constant\CstSettings;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SignService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

class CheckAsyncSignatureTaskTest extends TestCase
{
	/**
	 * Verify constructor computes and sets the expected job interval.
	 *
	 * @group yms_background_job
	 * @return void
	 */
	public function testConstructorSetsIntervalFromConfig(): void
	{
		$timeFactory = $this->createMock(ITimeFactory::class);
		$signService = $this->createMock(SignService::class);
		$configurationService = $this->createMock(ConfigurationService::class);
		$appConfig = $this->createMock(IAppConfig::class);

		$configurationService
			->expects(self::once())
			->method('getAppId')
			->willReturn('yumisign_nextcloud');

		$configurationService
			->expects(self::once())
			->method('getCronInterval')
			->willReturn(5);

		$appConfig
			->expects(self::once())
			->method('getValueInt')
			->with('yumisign_nextcloud', CstSettings::CRON_INTERVAL, default: 5)
			->willReturn(7);

		$task = new CheckAsyncSignatureTask(
			$timeFactory,
			$signService,
			$configurationService,
			$appConfig
		);

		self::assertSame(7 * 59, $task->getInterval());
	}

	/**
	 * Verify run delegates to sign service and returns null in nominal flow.
	 *
	 * @group yms_background_job
	 * @return void
	 */
	public function testRunCallsSignServiceAndReturnsNull(): void
	{
		$task = $this->buildTaskForRunTests();

		$signService = $this->createMock(SignService::class);
		$signService
			->expects(self::once())
			->method('checkAsyncSignatureTask');

		$this->setPrivateProperty($task, 'signService', $signService);
		$result = $this->invokeProtectedRun($task, []);

		self::assertNull($result);
	}

	/**
	 * Verify run rethrows service exceptions.
	 *
	 * @group yms_background_job
	 * @return void
	 */
	public function testRunRethrowsThrowableFromSignService(): void
	{
		$task = $this->buildTaskForRunTests();

		$exception = new \RuntimeException('boom');
		$signService = $this->createMock(SignService::class);
		$signService
			->expects(self::once())
			->method('checkAsyncSignatureTask')
			->willThrowException($exception);

		$this->setPrivateProperty($task, 'signService', $signService);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('boom');
		$this->invokeProtectedRun($task, []);
	}

	private function buildTaskForRunTests(): CheckAsyncSignatureTask
	{
		$timeFactory = $this->createMock(ITimeFactory::class);
		$signService = $this->createMock(SignService::class);
		$configurationService = $this->createMock(ConfigurationService::class);
		$appConfig = $this->createMock(IAppConfig::class);

		$configurationService
			->expects(self::once())
			->method('getAppId')
			->willReturn('yumisign_nextcloud');

		$configurationService
			->expects(self::once())
			->method('getCronInterval')
			->willReturn(1);

		$appConfig
			->expects(self::once())
			->method('getValueInt')
			->with('yumisign_nextcloud', CstSettings::CRON_INTERVAL, default: 1)
			->willReturn(1);

		return new CheckAsyncSignatureTask(
			$timeFactory,
			$signService,
			$configurationService,
			$appConfig
		);
	}

	/**
	 * @param CheckAsyncSignatureTask $task
	 * @param array<mixed> $arguments
	 * @return mixed
	 */
	private function invokeProtectedRun(CheckAsyncSignatureTask $task, array $arguments): mixed
	{
		$method = new \ReflectionMethod(CheckAsyncSignatureTask::class, 'run');
		return $method->invoke($task, $arguments);
	}

	/**
	 * @param CheckAsyncSignatureTask $task
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	private function setPrivateProperty(CheckAsyncSignatureTask $task, string $property, mixed $value): void
	{
		$reflection = new \ReflectionProperty(CheckAsyncSignatureTask::class, $property);
		$reflection->setValue($task, $value);
	}
}
