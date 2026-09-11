<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class ServicesCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Inventory all app service functions.
	 *
	 * @group yms_service
	 * @dataProvider provideAppServiceFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testAppServiceFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Inventory all bundle service functions.
	 *
	 * @group bundle_service
	 * @dataProvider provideBundleServiceFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleServiceFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideAppServiceFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/lib/Service';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleServiceFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/Service';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}
}

