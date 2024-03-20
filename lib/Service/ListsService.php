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

namespace OCA\YumiSignNxtC\Service;

class ListsService
{
	public static function getIfExists(string $field, array|object $requestIntel, bool $returnNull = true)
	{
		try {
			$returnValue = ($returnNull ? null : '');
	
			$field = strtolower($field);
	
			switch (true) {
				case is_array($requestIntel):
					$requestIntel = array_change_key_case($requestIntel, CASE_LOWER);
	
					if (array_key_exists($field, $requestIntel)) {
						return $requestIntel[$field];
					} else {
						return $returnValue;
					}
					break;
	
				case is_object($requestIntel):
					foreach ($requestIntel as $key => $value) {
						if (strtolower($key) == $field) {
							return $value;
							break;
						}
					}
					return $returnValue; //Not found here...
					break;
	
				default:
					return $returnValue;
					break;
			}
		} catch (\Throwable $th) {
			return $returnValue;
		}
	}
}



