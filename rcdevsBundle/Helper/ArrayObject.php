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

namespace OCA\YumiSignNxtC\RCDevs\Helper;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstException;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTypes;

// Nextcloud Core
use Exception;
use Throwable;

/**
 * Helper to manage arrays and objects
 *
 * @package	OCA\YumiSignNxtC\RCDevs\Helper
 */
class ArrayObject
{
	/**
	 * Returns data from the array, according to the key
	 * Can raise a managed exception if the missing key is mandatory
	 *
	 * @param array|null	$array
	 * @param string		$key
	 * @param bool			$missingForbidden
	 * @param string|null	$exceptionMessage
	 * @return mixed
	 * @throws Exception
	 */
	public static function getArrayData(
		array|null $array,
		string $key,
		bool $missingForbidden,
		string|null $exceptionMessage = null
	) {
		if (is_null($array) && $missingForbidden) {
			throw new Exception(CstException::ARRAY_NULL);
		}

		if (is_null($array) && !$missingForbidden) {
			return '';
		}

		if (array_key_exists($key, $array)) {
			return $array[$key];
		} else {
			if ($missingForbidden) {
				$exceptionMessage = $exceptionMessage ?? CstException::MISSING_KEY;
				throw new Exception("{$exceptionMessage} ({$key})");
			} else {
				return '';
			}
		}
	}

	/**
	 * Safely fetches a field from an array or object with optional type casting.
	 *
	 * @param string $field Name of the field to fetch
	 * @param array|object $requestIntel Source data (array or object)
	 * @param bool $returnNull Whether to return null (true) or empty string (false) if not found
	 * @param string $forceType Optional forced type: 'string', 'int', 'bool', 'float', etc.
	 * @return mixed
	 */
	public static function getIfExists(
		string $field,
		array|object $requestIntel,
		bool $returnNull = true,
		string $forceType = ''
	): mixed {
		$returnValue = ($returnNull ? null : '');

		try {
			switch (true) {
				case is_array($requestIntel):
					if (array_key_exists($field, $requestIntel)) {
						$returnValue = $requestIntel[$field];
					}
					break;

				case is_object($requestIntel):
					if (property_exists($requestIntel, $field)) {
						if ((new \ReflectionProperty($requestIntel, $field))->isPublic()) {
							$returnValue = $requestIntel->$field;
						}
					} elseif (method_exists($requestIntel, $field)) {
						$returnValue = $requestIntel->$field();
					} elseif (method_exists($requestIntel, 'get' . ucfirst($field))) {
						$method = 'get' . ucfirst($field);
						$returnValue = $requestIntel->$method();
					}
					break;
			}

			// Optional forced type casting
			if ($forceType !== '') {
				switch ($forceType) {
					case CstTypes::ARRAY:
						$returnValue = (array) $returnValue;
						break;

					case CstTypes::BOOL:
						$returnValue = filter_var($returnValue, FILTER_VALIDATE_BOOLEAN);
						break;

					case CstTypes::FLOAT:
						$returnValue = (float) $returnValue;
						break;

					case CstTypes::INT:
						$returnValue = (int) $returnValue;
						break;

					case CstTypes::JSON:
						$returnValue = json_encode($returnValue, JSON_UNESCAPED_UNICODE);
						break;

					case CstTypes::STRING:
						if (is_array($returnValue)) {
							if (count($returnValue) === 0) {
								$returnValue = '';
							} elseif (count($returnValue) === 1) {
								$returnValue = (string) reset($returnValue);
							} else {
								$returnValue = json_encode($returnValue, JSON_UNESCAPED_UNICODE);
							}
						} elseif (is_object($returnValue)) {
							$returnValue = json_encode($returnValue, JSON_UNESCAPED_UNICODE);
						} else {
							$returnValue = (string) $returnValue;
						}
						break;

					default:
						// Unknown type, keep as-is
						break;
				}
			}
		} catch (\Throwable) {
			$returnValue = ($returnNull ? null : '');
		}

		return $returnValue;
	}
}
