<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class ControllersCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Inventory all app controller functions.
	 *
	 * @group yms_controller
	 * @dataProvider provideAppControllerFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testAppControllerFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Inventory all bundle controller functions.
	 *
	 * @group bundle_controller
	 * @dataProvider provideBundleControllerFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleControllerFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideAppControllerFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/lib/Controller';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleControllerFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/Controller';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}
}
