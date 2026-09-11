<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Support;

class CoverageCatalog
{
	/**
	 * List PHP files recursively for a directory, optionally filtered by basename suffix.
	 *
	 * @param string $directory
	 * @param string $suffixFilter
	 * @return array<int, string>
	 */
	public static function filesFromDirectory(
		string $directory,
		string $suffixFilter = '',
	): array {
		if (!is_dir($directory)) {
			return [];
		}

		$results = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $item) {
			if (!$item->isFile()) {
				continue;
			}
			$path = $item->getPathname();
			if (!str_ends_with($path, '.php')) {
				continue;
			}
			$base = basename($path, '.php');
			if ($suffixFilter !== '' && !str_ends_with($base, $suffixFilter)) {
				continue;
			}
			$results[] = $path;
		}

		sort($results);
		return array_values(array_unique($results));
	}
}

