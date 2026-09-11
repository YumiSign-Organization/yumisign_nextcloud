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

namespace OCA\YumiSignNxtC\RCDevs\Service;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstLogMessages;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Exception\LogNullException;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;

// Nextcloud Core
use Exception;
use OC\SystemConfig;
use Psr\Log\LoggerInterface;
use Throwable;

class LogRCDevs
{
	/**
	 * @var resource|false
	 */
	private mixed			$logFile = null;
	private int				$globalLogLevel;
	private string|null		$logRCDevs;

	public function __construct(
		private ConfigurationService $configurationService,
		private LoggerInterface $logger,
	) {
		try {
			$systemConfig = \OC::$server->get(SystemConfig::class);
			$this->globalLogLevel = (int) $systemConfig->getValue('loglevel', 2);

			$logConfig = $this->configurationService->getLogConfig();
			$logFilename = $this->configurationService->getLogFilename();

			if (empty($logConfig) || empty($logFilename)) {
				throw new LogNullException(CstLogMessages::PATH_EMPTY);
			}

			$logRCDevs = $systemConfig->getValue($logConfig, '');

			if (!is_string($logRCDevs) || $logRCDevs === '') {
				throw new LogNullException(CstLogMessages::PATH_EMPTY);
			}

			$logRCDevs = rtrim($logRCDevs, DIRECTORY_SEPARATOR);

			if ($logRCDevs === '') {
				throw new LogNullException(CstLogMessages::PATH_EMPTY);
			}

			// Create directory if it does not exist
			if (!is_dir($logRCDevs)) {
				if (!mkdir($logRCDevs, 0750, true) && !is_dir($logRCDevs)) {
					throw new LogNullException(sprintf(CstLogMessages::DIR_CREATION_FAILED, $logRCDevs));
				}
			}

			$logRCDevsFile = $logRCDevs . DIRECTORY_SEPARATOR . $logFilename;

			// Create file if it does not exist
			if (!file_exists($logRCDevsFile)) {
				if (@file_put_contents($logRCDevsFile, '') === false) {
					throw new LogNullException(sprintf(CstLogMessages::FILE_CREATION_FAILED, $logRCDevsFile));
				}
			}

			// Check if file is writable
			if (!is_writable($logRCDevsFile)) {
				throw new LogNullException(sprintf(CstLogMessages::FILE_NOT_WRITABLE, $logRCDevsFile));
			}

			$this->logRCDevs = $logRCDevsFile;
			$this->logFile = fopen($this->logRCDevs, 'a');
			if ($this->logFile === false) {
				throw new LogNullException(sprintf(CstLogMessages::FILE_OPEN_FAILED, $this->logRCDevs));
			}
		} catch (LogNullException $logNullException) {
			// Logging fallback to Nextcloud standard logger
			$this->logRCDevs = null;
			$this->logger->warning(sprintf(CstLogMessages::CUSTOM_LOG_UNAVAILABLE, $logNullException->getMessage()));
		} catch (\Throwable $th) {
			// Fatal error, rethrow
			$this->logRCDevs = null;
			throw $th;
		}
	}

	public function __destruct()
	{
		if ($this->logFile) {
			fclose($this->logFile);
		}
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	private function commonFormatReturnedException(
		\Throwable $exception,
		int $code = 1,
	): array {
		return [
			CstReturn::CODE    => $code,
			CstReturn::DATA    => null,
			CstReturn::ERROR   => $exception->getCode(),
			CstReturn::MESSAGE => $exception->getMessage(),
		];
	}

	private function getLevelValue(
		string $callerFunction
	): int {
		return match ($callerFunction) {
			'alert' => 4,
			'critical' => 4,
			'debug' => 0,
			'emergency' => 4,
			'error' => 3,
			'info' => 1,
			'notice' => 1,
			'warning' => 2,
			default => 2,
		};
	}

	private function write(
		string $logMsg,
		string $callerFunction,
		string $functionName = '',
		bool $throw = false
	): void {
		if (!is_null($this->logRCDevs)) {
			if ($this->getLevelValue($callerFunction) < $this->globalLogLevel) {
				return;
			}

			fwrite($this->logFile, vsprintf("[%s] [%s-Nextcloud] [%s] [%s] %s\n", [
				date("Y-m-d H:i:s"),
				$this->configurationService->getApplicationName(),
				strtoupper($callerFunction),
				$functionName,
				$logMsg
			]));
		} else {
			// Standard login
			$this->logger->$callerFunction($logMsg);
		}
		if ($throw) throw new Exception($logMsg, 1);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function alert(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function critical(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function debug(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function emergency(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function error(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function format(
		string|null	$exceptionFunction	= null,
		string|null	$exceptionClass		= null,
		string|null	$exceptionFile		= null,
		int|null	$exceptionLine		= null
	): string {
		$parts = [];

		// Add function and class without initial separator
		if ($exceptionFunction !== null) {
			$parts[] = $exceptionFunction;
		}

		if ($exceptionClass !== null) {
			$parts[] = $exceptionClass;
		}

		// Manages file:line with conditional addition of a space if elements precede
		if ($exceptionFile !== null) {
			$filePart = $exceptionFile;
			if ($exceptionLine !== null) {
				$filePart .= ':' . $exceptionLine;
			}

			if (!empty($parts)) {
				$parts[] = ' ' . $filePart; // space BEFORE the path
			} else {
				$parts[] = $filePart;
			}
		}

		return $parts !== [] ? implode(DIRECTORY_SEPARATOR, $parts) : '[no context]';
	}

	public function formatReturnedException(
		\Throwable $exception,
	): array {
		return $this->commonFormatReturnedException($exception, code: 1);
	}

	public function formatReturnedWarning(
		\Throwable $warning,
		int $code = 0,
	): array {
		return $this->commonFormatReturnedException($warning, code: $code);
	}

	public function info(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function notice(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}

	public function warning(
		string $logMsg,
		string $functionName = '',
		bool $throw = false
	): void {
		$this->write($logMsg, __FUNCTION__, $functionName, $throw);
	}
}
