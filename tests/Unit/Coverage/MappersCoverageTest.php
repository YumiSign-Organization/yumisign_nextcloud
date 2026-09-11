<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Coverage;

use OCA\YumiSignNxtC\Tests\Support\CoverageAssertions;
use OCA\YumiSignNxtC\Tests\Support\CoverageCatalog;
use PHPUnit\Framework\TestCase;

class MappersCoverageTest extends TestCase
{
	use CoverageAssertions;

	/**
	 * Validate current app state for mapper files in lib/Db.
	 *
	 * @group yms_mapper
	 * @return void
	 */
	public function testAppMapperInventoryCurrentState(): void
	{
		$base = dirname(__DIR__, 3) . '/lib/Db';
		$files = CoverageCatalog::filesFromDirectory($base, 'Mapper');
		self::assertContains($base . '/TransactionMapper.php', $files);
		foreach ($files as $file) {
			$this->assertFileFunctionInventory($file);
		}
	}

	/**
	 * Inventory all bundle mapper functions.
	 *
	 * @group bundle_mapper
	 * @dataProvider provideBundleMapperFiles
	 * @param string $filePath
	 * @return void
	 */
	public function testBundleMapperFunctionsInventory(string $filePath): void
	{
		$this->assertFileFunctionInventory($filePath);
	}

	/**
	 * @return array<int, array{0:string}>
	 */
	public function provideBundleMapperFiles(): array
	{
		$base = dirname(__DIR__, 3) . '/rcdevsBundle/Db';
		$files = CoverageCatalog::filesFromDirectory($base, 'Mapper');
		return array_map(static fn (string $file): array => [$file], $files);
	}
}

