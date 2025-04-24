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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\RCDevs\Utility;

use Exception;
use OCA\RCDevs\Utility\Constantes\CstCommon;
use OCA\RCDevs\Utility\Constantes\CstException;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;

class Helpers
{
	public function __construct(
		protected	LogRCDevs				$logRCDevs,
	) {}

	public static function areDifferent(string $firstString, string $secondString)
	{
		try {
			return !self::areEqual($firstString, $secondString);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public static function areEqual(string $firstString, string $secondString)
	{
		try {
			$returned = strcasecmp($firstString, $secondString) === 0;
			return $returned;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public static function getArrayData(array|null $array, string $key, bool $missingForbidden, string $exceptionMessage = null)
	{
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

	public static function getIfExists(string $field, array|object $requestIntel, bool $returnNull = true)
	{
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
						// // if (
						// // 	count((new ReflectionObject($requestIntel))->getProperties(ReflectionProperty::IS_PUBLIC)) > 0
						// // ) {
						// // 	$checkProperty = ((new ReflectionObject($requestIntel))->getProperties(ReflectionProperty::IS_PUBLIC))[0];
						// // } else {
						// // 	$checkProperty = null;
						// // }
						// $checkProperty = (new ReflectionObject($requestIntel))->getProperties(ReflectionProperty::IS_PUBLIC);

						switch (true) {
							case (new ReflectionProperty($requestIntel, $field))->isPublic() :
								$returnValue = $requestIntel->$field;
								break;

							case method_exists($requestIntel, $field):
								$returnValue = $requestIntel->$field();
								break;

							case method_exists($requestIntel, 'get' . ucfirst($field)):
								$field = 'get' . ucfirst($field);
								$returnValue = $requestIntel->$field();
								break;

							default:
								$returnValue = null;
								break;
						}
					}
					break;

				default:
					// Will return initial returnValue, means null or empty string;
					break;
			}
		} catch (\Throwable $th) {
			$returnValue = ($returnNull ? null : '');
		}

		return $returnValue;
	}

	public static function humanFileSize(int $size, string $unit = "")
	{
		if ((!$unit && $size >= 1 << 30) || $unit == "GB")
			return number_format($size / (1 << 30), 2) . " GB";

		if ((!$unit && $size >= 1 << 20) || $unit == "MB")
			return number_format($size / (1 << 20), 2) . " MB";

		if ((!$unit && $size >= 1 << 10) || $unit == "KB")
			return number_format($size / (1 << 10), 2) . " KB";

		return number_format($size) . " bytes";
	}

	public static function isAdvanced(int $advanced)
	{
		try {
			return (intval($advanced) === 1);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	/**
	 * Indicates if response parameter contains CODE field and if this field is invalid
	 * @param array|object $response Paramter to check invalidity
	 * @return bool Returns true or false according if given $response is invalid or not
	 */
	public static function isIssueResponse(array|object $response): bool
	{
		return !self::isValidResponse($response);
	}

	/**
	 * Indicates if response parameter contains CODE field and if this field is valid
	 * @param array|object $response Paramter to check validity
	 * @return bool Returns true or false according if given $response is valid or not
	 */
	public static function isValidResponse(array|object $response): bool
	{
		try {
			$codeIfExists = self::getIfExists(CstRequest::CODE, $response);
			$code = is_null($codeIfExists)
				? 0
				: $codeIfExists;

			return (
				$code === 1 ||		// standard OK code
				$code === 2 ||		// code for pending operations but current status is OK
				$code === 200 ||	// https returned OK code
				$code === true		// true is... true so OK...
			);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public static function isPdf(string $path)
	{
		try {
			return
				strcasecmp(
					pathinfo($path, PATHINFO_EXTENSION),
					CstCommon::PDF
				) === 0;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public static function warning(string $warningMsg)
	{
		return [
			CstRequest::CODE	=> false,
			CstRequest::MESSAGE	=> $warningMsg,
		];
	}
}
