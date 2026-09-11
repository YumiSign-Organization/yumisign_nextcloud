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
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;

// Nextcloud Core
use Throwable;

/**
 * Helper to return standardized CDEM (Code, Data, Error, Message) response arrays
 *
 * @package	OCA\YumiSignNxtC\RCDevs\Helper
 */
class Response
{
	/**
	 * Indicates if response parameter contains CODE field and if this field is valid
	 * 
	 * @param array|object $response Paramter to check validity
	 * @return bool Returns true or false according if given $response is valid or not
	 */
	public static function isValid(
		array|object $response
	): bool {
		try {
			$codeIfExists = ArrayObject::getIfExists(CstReturn::CODE, $response);
			$code = is_null($codeIfExists)
				? 1					// Return error because the CODE is missing in the parameter Response
				: $codeIfExists;

			return (
				$code === 0 ||		// Standard OK code
				$code === 2 ||		// Code for warnings because a warning is not an exception
				$code === 200 ||	// https returned OK code
				$code === true		// true is... true so OK...
			);
		} catch (\Throwable $th) {
			throw $th;
		}
	}
}
