<?php

/**
 *
 * @copyright Copyright (c) 2025, RCDevs (info@rcdevs.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RegexIterator;
use Exception;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;

class ConstantController extends Controller
{

	public function __construct(
		IRequest $request,
		private ConfigurationService $configurationService,
		string $appName,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAllConstants(): JSONResponse
	{
		$finalConstants = [];

		$baseDir = realpath(__DIR__ . '/../../../'); // App root

		$sources = [
			[
				'dir'       => $baseDir . '/rcdevsBundle/Constant',
				'namespace' => 'OCA\\RCDevs\\Constant',
			],
			[
				'dir'       => $baseDir . '/lib/Constant',
				'namespace' => "OCA\\{$this->configurationService->getAppNamespace()}\\Constant",
			]
		];

		foreach ($sources as $source) {
			if (!is_dir($source['dir'])) {
				continue;
			}

			$files = new RegexIterator(
				new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source['dir'])),
				'/^.+\/Cst.*\.php$/i',
				RegexIterator::GET_MATCH
			);

			foreach ($files as $matches) {
				foreach ($matches as $file) {
					$className = $this->getClassFromPath($file, $source['dir'], $source['namespace']);

					if (!class_exists($className)) {
						require_once $file;
					}

					if (!class_exists($className)) {
						continue;
					}

					$reflection = new \ReflectionClass($className);
					foreach ($reflection->getConstants() as $name => $value) {
						if (array_key_exists($name, $finalConstants)) {
							if ($finalConstants[$name] !== $value) {
								throw new Exception("Duplicate constant '$name' with different values found in $className");
							}
							continue; // If same key and same value, no exception raised

						}

						$finalConstants[$name] = $value;
					}
				}
			}
		}

		return new JSONResponse($finalConstants);
	}

	private function getClassFromPath(
		string $filePath,
		string $basePath,
		string $baseNamespace
	): string {
		$relativePath = str_replace([$basePath . '/', '.php'], '', $filePath);
		$parts = explode(DIRECTORY_SEPARATOR, $relativePath);
		return $baseNamespace . '\\' . implode('\\', $parts);
	}
}
