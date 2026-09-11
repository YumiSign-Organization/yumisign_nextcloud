<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class SettingsCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Inventory app settings functions.
	 *
	 * @group yms_settings
	 * @dataProvider provideAppSettingsFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testAppSettingsFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Inventory bundle settings-related functions.
	 *
	 * @group bundle_settings
	 * @dataProvider provideBundleSettingsFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleSettingsFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideAppSettingsFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/lib/Settings';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleSettingsFiles(): array
	{
		$files = [
			dirname(__DIR__, 3) . '/rcdevsBundle/Controller/SettingsController.php',
			dirname(__DIR__, 3) . '/rcdevsBundle/Service/SettingsService.php',
		];
		return array_map(static fn (string $file): array => [$file], $files);
	}
}

