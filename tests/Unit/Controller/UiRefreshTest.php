<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Controller;

use OCA\YumiSignNxtC\Controller\UiController;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class UiRefreshTest extends TestCase
{
	public function testReadsOnlyTheSignedInSignersSignals(): void
	{
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::exactly(3))->method('getValueString')->willReturnCallback(
			static function ($uid, $app, $key): string {
				self::assertSame('signer', $uid);
				self::assertSame('yumisign_nextcloud', $app);
				return ['ui_refresh_token' => 'status-token', 'ui_refresh_scope' => 'transactions', 'ui_files_refresh_token' => 'signed-file-token'][$key];
			}
		);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$controller = $this->controller($config, $session);
		$data = $controller->pollRefreshSignal('status-token')->getData()['data'];
		self::assertSame('signed-file-token', $data['filesToken']);
		self::assertSame('transactions', $data['scope']);
		self::assertFalse($data['shouldRefresh']);
	}

	public function testAnonymousRequestDoesNotReadUserConfiguration(): void
	{
		$config = $this->createMock(IUserConfig::class);
		$config->expects(self::never())->method('getValueString');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$data = $this->controller($config, $session)->pollRefreshSignal()->getData()['data'];
		self::assertSame('', $data['filesToken']);
		self::assertFalse($data['shouldRefresh']);
	}

	private function controller(IUserConfig $config, IUserSession $session): UiController
	{
		return new UiController(
			'yumisign_nextcloud',
			$this->createMock(IRequest::class),
			$this->createMock(IAppConfig::class),
			$config,
			$session,
			$this->createMock(LogRCDevs::class),
			new \OCA\YumiSignNxtC\RCDevs\Service\FilesRefreshService(
				new \OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService($this->createMock(IAppConfig::class)), $config
			),
		);
	}
}
