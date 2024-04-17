<?php

/**
 *
 * @copyright Copyright (c) 2024, RCDevs (info@rcdevs.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Utility;

use Exception;
use OC\Config;
use OC\Server;
use OC\SystemConfig;
use OCA\Activity\AppInfo\Application;
use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

use function OCP\Log\logger;

class LogYumiSign
{

	private string $appName = YumiSignApp::APP_ID;
	private string|null $logYms;
	private $logFile;

	public function __construct(private LoggerInterface $logger)
	{
		try {
			$this->logYms = null;
			$logYumisign = rtrim(\OC::$server->get(SystemConfig::class)->getValue('log_yumisign', false), DIRECTORY_SEPARATOR);

			if ($logYumisign) {
				// Check if folder exists
				if (!is_dir($logYumisign)) {
					$this->createDirectory($logYumisign);
				}

				$logYumisignFile = "{$logYumisign}/{$this->appName}.log";
				
				// Check if file exists
				if (!file_exists($logYumisignFile)) {
					file_put_contents($logYumisignFile, '');
				}

				if (is_writable($logYumisignFile)) {
					$this->logYms = $logYumisignFile;
				} else {
					$this->logYms = null;
				}
			} else {
				$this->logYms = null;
			}

			if (!is_null($this->logYms)) {
				$this->logFile = fopen($this->logYms, 'a');
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function __destruct()
	{
		if ($this->logFile) {
			fclose($this->logFile);
		}
	}

	public function debug(string $logMsg, string $functionName = '', bool $throw = false): void
	{
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function info(string $logMsg, string $functionName = '', bool $throw = false): void
	{
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function warning(string $logMsg, string $functionName = '', bool $throw = false): void
	{
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function error(string $logMsg, string $functionName = '', bool $throw = false): void
	{
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function critical(string $logMsg, string $functionName = '', bool $throw = false): void
	{
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	private function write(string $logMsg, string $callerFunction, string $functionName = '', bool $throw = false): void
	{
		if (!is_null($this->logYms)) {
			fwrite($this->logFile, sprintf("[%s] [YumiSign-Nextcloud] [%s] [%s] %s\n", date("Y-m-d H:i:s"), strtoupper($callerFunction), $functionName, $logMsg));
		} else {
			// Standard login
			$this->logger->$callerFunction($logMsg);
		}
		if ($throw) throw new Exception($logMsg, 1);
	}

	private function createDirectory(string $directoryName): bool
	{
		try {
			mkdir($directoryName, 0770, true);
		} catch (\Throwable $th) {
			//throw $th;
		}
		return is_dir($directoryName);
	}
}
