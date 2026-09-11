<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class UtilitiesHelpersCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Inventory app utility/helper related functions.
	 *
	 * @group yms_utility
	 * @dataProvider provideAppUtilityFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testAppUtilityFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Inventory bundle helper functions.
	 *
	 * @group bundle_helper
	 * @dataProvider provideBundleHelperFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleHelperFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Inventory bundle utility functions.
	 *
	 * @group bundle_utility
	 * @dataProvider provideBundleUtilityFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleUtilityFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideAppUtilityFiles(): array
	{
		$files = [
			dirname(__DIR__, 3) . '/lib/Config.php',
			dirname(__DIR__, 3) . '/lib/FilesLoader.php',
		];
		return array_map(static fn (string $file): array => [$file], $files);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleHelperFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/Helper';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleUtilityFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/Utility';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}
}

