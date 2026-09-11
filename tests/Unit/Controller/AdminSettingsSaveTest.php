<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Controller;

use OCA\YumiSignNxtC\Controller\AdminSettingsController;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\Service\SettingsService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class AdminSettingsSaveTest extends TestCase
{
	/** @dataProvider workspaceSettingsProvider */
	public function testWorkspaceNameIsSavedAndReloadedWithBooleanSwitches($workspaceId, bool $enabled): void
	{
		$values = [];
		$params = [
			'async_timeout' => '10',
			'cron_interval' => '5',
			'enable_sign' => $enabled,
			'overwrite' => true,
			'sign_type_advanced' => $enabled,
			'sign_type_qualified' => $enabled,
			'sign_type_standard' => $enabled,
			'use_proxy' => false,
			'workspace_name' => 'dev_nextcloud_33',
			'workspace_id' => $workspaceId,
		];
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn ($key) => $params[$key] ?? '');
		$config = $this->createMock(IAppConfig::class);
		$config->method('setValueInt')->willReturnCallback(static function (string $app, string $key, int $value) use (&$values): bool {
			$values[$key] = $value;
			return true;
		});
		$config->method('setValueString')->willReturnCallback(static function (string $app, string $key, string $value) use (&$values): bool {
			$values[$key] = $value;
			return true;
		});
		$config->method('getValueString')->willReturnCallback(static function (string $app, string $key) use (&$values): string {
			return $values[$key] ?? '';
		});
		$config->method('getValueInt')->willReturnCallback(static function (string $app, string $key) use (&$values): int {
			return $values[$key] ?? 0;
		});
		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(static function ($key, $value) use ($workspaceId, $enabled): void {
			if ($key === 'initialSettings') {
				self::assertSame('dev_nextcloud_33', $value['workspaceName']);
				self::assertSame((string) $workspaceId, $value['workspaceId']);
				foreach (['enableSign', 'sign_type_standard', 'sign_type_advanced', 'sign_type_qualified'] as $flag) {
					self::assertSame($enabled, $value[$flag], $flag);
				}
			}
		});
		$controller = new AdminSettingsController(
			$config,
			$request,
			$this->createMock(SettingsService::class),
			$initialState,
			$this->createMock(LogRCDevs::class),
			$this->createMock(IUserManager::class),
			'admin',
			'yumisign_nextcloud',
		);

		self::assertSame(['code' => 0], $controller->saveSettings()->getData());
		self::assertSame('dev_nextcloud_33', $values['workspace_name']);
		foreach ($params as $key => $value) {
			if (!in_array($key, ['workspace_name', 'workspace_id'], true)) {
				self::assertSame((int) $value, $values[$key], $key);
			}
		}
		self::assertSame((string) $workspaceId, $values['workspace_id']);
		$controller->getForm();
	}

	public static function workspaceSettingsProvider(): array
	{
		return [
			'empty ID' => ['', false],
			'numeric ID with signatures enabled' => [42, true],
			'numeric ID with signatures disabled' => [42, false],
			'text ID' => ['42', true],
		];
	}

}
