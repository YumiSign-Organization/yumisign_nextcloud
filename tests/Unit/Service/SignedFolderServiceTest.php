<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService;
use OCP\Config\IUserConfig;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

class SignedFolderServiceTest extends TestCase
{
	private function service(string $default = 'YMSSigned/applicant'): SignedFolderService
	{
		$config = $this->createMock(ConfigurationService::class);
		$config->method('getAppId')->willReturn('test');
		$config->method('getUserSignedFolderApplicant')->willReturn($default);
		$config->method('getUserSignedFolderRecipient')->willReturn('YMSSigned/recipient');
		$preferences = $this->createMock(IUserConfig::class);
		$preferences->method('getValueString')->willReturnCallback(static fn ($uid, $app, $key, $default) => $default);
		$folder = $this->createMock(Folder::class);
		$folder->method('isCreatable')->willReturn(true);
		$folder->method('get')->willThrowException(new NotFoundException());
		$folder->expects(self::never())->method('newFolder');
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->willReturn($folder);
		return new SignedFolderService($config, $preferences, $root, \OCP\Server::get(IFilenameValidator::class));
	}

	/** @dataProvider invalidPaths */
	public function testInvalidPersonalPathIsFatal(string $path): void
	{
		$this->expectException(\RuntimeException::class);
		$this->service()->save('user', $path, 'YMSSigned/recipient');
	}

	public function invalidPaths(): array
	{
		return array_map(static fn ($path) => [$path], ['', '/absolute', '../outside', 'Signed/../outside', 'Signed//Docs', 'https://example.com', 'C:\\docs', '.', 'Signed/', ' Signed']);
	}

	public function testMissingXmlDefaultBlocksEvenWhenUserHasTwoCustomPaths(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->service('')->save('user', 'Custom A', 'Custom R');
	}

	public function testSettingsValidationAllowsNestedNamesWithoutCreatingFolders(): void
	{
		self::assertSame(['applicant' => 'Documents signés/2026', 'recipient' => 'Signed by coworkers'], $this->service()->save('user', 'Documents signés/2026', 'Signed by coworkers'));
	}
}
