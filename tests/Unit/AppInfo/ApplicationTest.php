<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\AppInfo;

use OCA\YumiSignNxtC\AppInfo\Application;
use OCA\YumiSignNxtC\Constant\CstApplication;
use OCA\YumiSignNxtC\FilesLoader;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
	/**
	 * Validate all string getters in Application without exercising constructor.
	 *
	 * @group yms_application
	 * @return void
	 */
	public function testGettersReturnInjectedStringValues(): void
	{
		$application = $this->createApplicationWithoutConstructor();
		$fixtures = [
			'appId' => 'yumisign_nextcloud',
			'appName' => 'YumiSign for Nextcloud',
			'appNameSigned' => 'YumiSign Signed',
			'appNameSealed' => 'YumiSign Sealed',
			'appNamespace' => 'yumisign_nextcloud',
			'appTableNameSessions' => 'yms_sessions',
		];

		foreach ($fixtures as $property => $value) {
			$this->setPrivateProperty($application, $property, $value);
		}

		self::assertSame($fixtures['appId'], $application->getAppId());
		self::assertSame($fixtures['appName'], $application->getAppName());
		self::assertSame($fixtures['appNameSigned'], $application->getAppNameSigned());
		self::assertSame($fixtures['appNameSealed'], $application->getAppNameSealed());
		self::assertSame($fixtures['appNamespace'], $application->getAppNamespace());
		self::assertSame($fixtures['appTableNameSessions'], $application->getAppTableNameSessions());
	}

	/**
	 * Validate getDebugVueJs returns the stored boolean value.
	 *
	 * @group yms_application
	 * @dataProvider provideDebugFlags
	 * @param bool $flag
	 * @return void
	 */
	public function testGetDebugVueJsReturnsStoredBoolean(bool $flag): void
	{
		$application = $this->createApplicationWithoutConstructor();
		$this->setPrivateProperty($application, 'debugVueJs', $flag);

		self::assertSame($flag, $application->getDebugVueJs());
	}

	/**
	 * Validate boot wires navigation entry and FilesLoader listener.
	 *
	 * @group yms_application
	 * @return void
	 */
	public function testBootRegistersNavigationAndFilesLoaderListener(): void
	{
		$application = $this->createApplicationWithoutConstructor();
		$this->setPrivateProperty($application, 'appId', 'yumisign_nextcloud');
		$this->setPrivateProperty($application, 'appNamespace', 'yumisign_nextcloud');
		$this->setPrivateProperty($application, 'appNameShort', 'YumiSign');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$expectedHref = 'generated-href::yumisign_nextcloud.Page.index';
		$expectedIcon = 'generated-icon::yumisign_nextcloud::app.svg';

		$urlGenerator
			->expects(self::once())
			->method('linkToRouteAbsolute')
			->with('yumisign_nextcloud.Page.index')
			->willReturn($expectedHref);

		$urlGenerator
			->expects(self::once())
			->method('imagePath')
			->with('yumisign_nextcloud', 'app.svg')
			->willReturn($expectedIcon);

		$navManager = $this->createMock(INavigationManager::class);
		$navManager
			->expects(self::once())
			->method('add')
			->with(self::callback(function (callable $navigationBuilder) use ($expectedHref, $expectedIcon): bool {
				$entry = $navigationBuilder();
				return $entry[CstApplication::ID] === 'yumisign_nextcloud'
					&& $entry[CstApplication::NAME] === 'YumiSign'
					&& $entry[CstApplication::HREF] === $expectedHref
					&& $entry[CstApplication::ICON] === $expectedIcon
					&& $entry[CstApplication::ORDER] === 3
					&& $entry[CstApplication::TYPE] === CstApplication::LINK;
			}));

		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher
			->expects(self::once())
			->method('addServiceListener')
			->with(LoadAdditionalScriptsEvent::class, FilesLoader::class);

		\OC::$server->registerService(INavigationManager::class, static fn ($container) => $navManager);
		\OC::$server->registerService(IURLGenerator::class, static fn ($container) => $urlGenerator);
		\OC::$server->registerService(IEventDispatcher::class, static fn ($container) => $dispatcher);

		$context = $this->createMock(IBootContext::class);
		$application->boot($context);
	}

	/**
	 * @return array<int, array{0:bool}>
	 */
	public function provideDebugFlags(): array
	{
		return [
			[true],
			[false],
		];
	}

	private function createApplicationWithoutConstructor(): Application
	{
		$reflection = new \ReflectionClass(Application::class);
		/** @var Application $application */
		$application = $reflection->newInstanceWithoutConstructor();
		return $application;
	}

	/**
	 * @param Application $application
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	private function setPrivateProperty(Application $application, string $property, mixed $value): void
	{
		$reflection = new \ReflectionProperty(Application::class, $property);
		$reflection->setValue($application, $value);
	}
}
