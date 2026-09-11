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

namespace OCA\YumiSignNxtC\RCDevs\Utility;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransactionType;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;

// Nextcloud Core
use ArrayObject;
use nusoap_client;

class RequestResponse extends ArrayObject
{
	private array $array;

	function __construct(array|bool $input)
	{
		switch (true) {
				case is_array($input):
					parent::__construct($input, ArrayObject::ARRAY_AS_PROPS);
					$this->array = $input;
					$this->array[CstReturn::CODE] = array_key_exists(CstReturn::CODE, $this->array)
						? intval($this->array[CstReturn::CODE])
						: 1;
					break;

			case is_bool($input) && !$input:
				$this->array[CstReturn::CODE] = 0;
				$this->array[CstReturn::MESSAGE] = 'Nusoap retuned a false';
				break;

			default:
				$this->array[CstReturn::CODE] = 0;
				$this->array[CstReturn::MESSAGE] = 'Nusoap retuned an unexpected response';
				break;
		}
	}

	public function getArray(): array
	{
		return $this->array;
	}

	public function getCode(): int|null
	{
		return Helpers::getIfExists(CstReturn::CODE, $this->array, returnNull: true);
	}

	public function getComment(): string|null
	{
		return Helpers::getIfExists(CstRequest::COMMENT, $this->array, returnNull: true);
	}

	public function getData(): mixed
	{
		$data = Helpers::getIfExists(CstReturn::DATA, $this->array, returnNull: true);
		if (is_null($data)) {
			$data = [];
		}
		return $data;
	}

	public function setData(
		array $data
	) {
		$this->array[CstReturn::DATA] = $data;
	}

	public function getError(): string|null
	{
		return Helpers::getIfExists(CstReturn::ERROR, $this->array, returnNull: true);
	}

	public function getFaultcode(): string|null
	{
		return Helpers::getIfExists(CstRequest::FAULTCODE, $this->array, returnNull: true);
	}

	public function getFaultstring(): string|null
	{
		return Helpers::getIfExists(CstRequest::FAULTSTRING, $this->array, returnNull: true);
	}

	public function getFile(): string|null
	{
		return Helpers::getIfExists(CstRequest::FILE, $this->array, returnNull: true);
	}

	public function getMessage(): string|null
	{
		return Helpers::getIfExists(CstReturn::MESSAGE, $this->array, returnNull: true);
	}

	public function getSession(): string|null
	{
		return Helpers::getIfExists(CstRequest::TRANSACTION, $this->array, returnNull: true);
	}

	public function getSoap(): nusoap_client
	{
		return $this->array[CstReturn::DATA][CstRequest::SOAP];
	}

	public function setSoap(
		nusoap_client $soap
	) {
		// Check id DATA exists
		if (is_null(Helpers::getIfExists(CstReturn::DATA, $this->array, returnNull: true))) {
			$this->setData([]);
		}
		$this->array[CstReturn::DATA][CstRequest::SOAP] = $soap;
	}

	public function isCode(
		int $value
	): bool {
		return ($this->getCode() === $value);
	}

	public function isFailed(): bool
	{
		return ($this->getCode() === 0);
	}
}
