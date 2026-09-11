<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class BackgroundJobsCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Inventory all app background job functions.
	 *
	 * @group yms_background_job
	 * @dataProvider provideAppBackgroundJobFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testAppBackgroundJobFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * Validate current bundle state for background job files.
	 *
	 * @group bundle_background_job
	 * @return void
	 */
	public function testBundleBackgroundJobInventoryCurrentState(): void
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/BackgroundJob';
		$files = CoverageCatalog::filesFromDirectory($base);
		self::assertSame([], $files, 'Bundle currently has no dedicated background job classes.');
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideAppBackgroundJobFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/lib/BackgroundJob';
		$files = CoverageCatalog::filesFromDirectory($base);
		return array_map(static fn (string $file): array => [$file], $files);
	}
}

