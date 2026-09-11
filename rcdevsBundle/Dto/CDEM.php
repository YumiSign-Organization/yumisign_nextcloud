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

namespace OCA\YumiSignNxtC\RCDevs\Dto;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransactionType;
use OCA\YumiSignNxtC\RCDevs\Enum\CDEMLogLevel;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;

// Nextcloud Core
use ArrayObject;
use nusoap_client;
use OCA\YumiSignNxtC\RCDevs\Constant\CstLogMessages;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use Throwable;

class CDEM
{
	private const	_FAILED		= 1;
	private const	_SUCCESS	= 0;
	private	int		$_code;
	private	mixed	$_data;
	private	mixed	$_error;
	private	?string	$_message;

	public function __construct(
		int $code = 1,
		mixed $data = null,
		mixed $error = null,
		?string $message = null
	) {
		$this->_code = $code;
		$this->_data = $data;
		$this->_error = $error;
		$this->_message = $message;
	}

	public function fromArray(
		array $input
	): self {
		$this->_code = isset($input[CstReturn::CODE]) ? (int)$input[CstReturn::CODE] : 1;
		$this->_data = $input[CstReturn::DATA] ?? null;
		$this->_error = $input[CstReturn::ERROR] ?? null;
		$this->_message = isset($input[CstReturn::MESSAGE]) ? (string)$input[CstReturn::MESSAGE] : null;

		return $this;
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function commonSet(
		Throwable	$exception,
		LogRCDevs	$logRCDevs,
		string		$callerFunction,
		mixed		$data = null,
	): self {
		// Retrieve Exception intel
		$exceptionCode	= $exception->getCode(); // int|string
		$trace			= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller			= $trace[1] ?? [];

		$logMethod = $this->resolveLogMethod($callerFunction, $logRCDevs);
		$this->_code	= self::_FAILED;
		$this->_data	= $data;
		$this->_error	= is_int($exceptionCode) && $exceptionCode !== 0 ? $exceptionCode : 500;
		$this->_message	= $exception->getMessage();

		$logRCDevs->$logMethod(
			sprintf($exception->getMessage()),
			$logRCDevs->format(
				exceptionFunction: $caller['function'] ?? null,
				exceptionClass: $caller['class']	?? null,
				exceptionFile: $caller['file']	 ?? null,
				exceptionLine: $caller['line']	 ?? null
			)
		);

		return $this;
	}

	private function resolveLogMethod(
		string $callerFunction,
		LogRCDevs $logRCDevs
	): string {
		$defaultMethod = 'error';
		$candidate = strtolower(substr($callerFunction, 3));

		if ($candidate === '') {
			return $defaultMethod;
		}

		$allowedLevels = array_map(
			static fn(CDEMLogLevel $level) => strtolower($level->name),
			CDEMLogLevel::cases()
		);

		$allowedLevels = array_values(array_filter(
			$allowedLevels,
			static fn(string $level) => $level !== strtolower(CDEMLogLevel::NONE->name)
		));

		if (!in_array($candidate, $allowedLevels, true)) {
			return $defaultMethod;
		}

		if (!method_exists($logRCDevs, $candidate)) {
			return $defaultMethod;
		}

		return $candidate;
	}

	/** ******************************************************************************************
	 * GETTERS
	 ****************************************************************************************** */
	public function code(): int
	{
		return $this->_code;
	}

	public function data(): mixed
	{
		return $this->_data;
	}

	public function error(): mixed
	{
		return $this->_error;
	}

	public function message(): ?string
	{
		return $this->_message;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function isFailed(): bool
	{
		return ($this->_code === self::_FAILED);
	}

	public function isSuccess(): bool
	{
		return ($this->_code === self::_SUCCESS);
	}

	public function isValid(): bool
	{
		return ($this->_code === self::_SUCCESS);
	}

	/**
	 * Returns a formatted CDEM array and logs the exception
	 * 
	 * @param Throwable $exception 
	 * @param LogRCDevs $logRCDevs 
	 * @return self
	 */
	public function setError(
		Throwable $exception,
		LogRCDevs $logRCDevs
	): self {
		// $cdem = new self();

		// // Retrieve Exception intel
		// $exceptionCode	= $exception->getCode(); // int|string
		// $trace			= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		// $caller			= $trace[1] ?? [];

		// $cdem->_code	= self::_FAILED;
		// $cdem->_data	= null;
		// $cdem->_error	= is_int($exceptionCode) && $exceptionCode !== 0 ? $exceptionCode : 500;
		// $cdem->_message	= $exception->getMessage();

		// $logRCDevs->error(
		// 	sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $exception->getMessage()),
		// 	$logRCDevs->format(
		// 		exceptionFunction: $caller['function'] ?? null,
		// 		exceptionClass: $caller['class']	?? null,
		// 		exceptionFile: $caller['file']	 ?? null,
		// 		exceptionLine: $caller['line']	 ?? null
		// 	)
		// );

		// return $cdem;
			return $this->commonSet($exception, $logRCDevs, __FUNCTION__, data: null);
	}

	/**
	 * Returns a formatted CDEM array
	 * It can log according to the provided level
	 *
	 * @param mixed|null $data
	 * @param string|null $message
	 * @param LogRCDevs|null $logRCDevs
	 * @param CDEMLogLevel $logLevel
	 * @return self
	 */
	public function setOk(
		mixed $data = null,
		string|null $message = null,
		LogRCDevs|null $logRCDevs = null,
		CDEMLogLevel $logLevel = CDEMLogLevel::NONE,
	): self {
		$this->_code	= self::_SUCCESS;
		$this->_data	= $data;
		$this->_error	= null;
		$this->_message	= $message;

		// Send log if needed
		if (!is_null($logRCDevs)) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			$caller = $trace[1] ?? [];
			$context = $logRCDevs->format(
				exceptionFunction: $caller['function'] ?? null,
				exceptionClass: $caller['class'] ?? null,
				exceptionFile: $caller['file'] ?? null,
				exceptionLine: $caller['line'] ?? null
			);
				$payload = json_encode($this->toArray());

			// match ($logLevel) {
			// 	CDEMLogLevel::DEBUG => $logRCDevs->debug($payload, $context),
			// 	CDEMLogLevel::INFO => $logRCDevs->info($payload, $context),
			// 	CDEMLogLevel::WARNING => $logRCDevs->warning($payload, $context),
			// 	CDEMLogLevel::ERROR => $logRCDevs->error($payload, $context),
			// 	CDEMLogLevel::NOTICE => $logRCDevs->notice($payload, $context),
			// 	CDEMLogLevel::CRITICAL => $logRCDevs->critical($payload, $context),
			// 	CDEMLogLevel::ALERT => $logRCDevs->alert($payload, $context),
			// 	CDEMLogLevel::EMERGENCY => $logRCDevs->emergency($payload, $context),
			// 	CDEMLogLevel::NONE => null,
			// };
				switch ($logLevel) {
					case CDEMLogLevel::NONE:
						# nothing
						break;

					default:
						$logMethod = strtolower($logLevel->name);
						if (method_exists($logRCDevs, $logMethod)) {
							$logRCDevs->$logMethod($payload, $context);
						} else {
							$logRCDevs->error($payload, $context);
						}
						break;
				}
			}

			return $this;
	}

	/**
	 * Returns a formatted CDEM array and logs the exception
	 * 
	 * @param Throwable $exception 
	 * @param LogRCDevs $logRCDevs 
	 * @return self
	 */
	public function setWarning(
		Throwable $exception,
		LogRCDevs $logRCDevs,
		mixed $data = null,
	): self {
		return $this->commonSet($exception, $logRCDevs, __FUNCTION__, data: $data);
	}

	public function toArray(): array
	{
		return [
			CstReturn::CODE		=> $this->_code,
			CstReturn::DATA		=> $this->_data,
			CstReturn::ERROR	=> $this->_error,
			CstReturn::MESSAGE	=> $this->_message,
		];
	}
}
