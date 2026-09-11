<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\Service\CurlService;
use OCA\YumiSignNxtC\Service\SettingsService;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WorkspaceLookupTest extends TestCase
{
	public function testUnknownWorkspaceReturnsItsNameWithoutTranslationError(): void
	{
		$body = new CurlEntity();
		$body->setBody('[{"id":42,"name":"Existing workspace"}]');
		$curl = $this->createMock(CurlService::class);
		$curl->method('getCurlResponse')->willReturn($body);
		$log = $this->createMock(LogRCDevs::class);
		$log->expects(self::never())->method('error');
		$reflection = new ReflectionClass(SettingsService::class);
		$service = $reflection->newInstanceWithoutConstructor();
		foreach ([
			'curlService' => $curl,
			'logRCDevs' => $log,
			'l10nYmsSettingsService' => \OC::$server->get(IFactory::class)->get('yumisign_nextcloud', 'en'),
		] as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($service, $value);
		}
		$result = $service->checkWorkspace('https://example.invalid/workspaces', 'dev_nextcloud_33', '');
		self::assertFalse($result['status']);
		self::assertSame([], $result['listId']);
		self::assertSame('The workspace named "dev_nextcloud_33" was not found on YumiSign server', $result['message']);
	}
}
