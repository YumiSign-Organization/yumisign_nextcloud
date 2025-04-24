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

use OCA\RCDevs\Utility\Constantes\CstRequest;

class SignatureType
{
	private string $value;
	private string $valueNormalized;

	public function __construct(
		private bool|null $advanced = false,
		private bool|null $qualified = false,
		private bool|null $standard = false,
	) {
		try {
			$this->value = $advanced ?
				CstRequest::ADVANCED
				: ($qualified ?
					CstRequest::QUALIFIED :
					CstRequest::SIMPLE);
			// Normalized value uses "Standard" instead of Simple. But Simple is needed for specific Modules
			$this->valueNormalized = $advanced ?
				CstRequest::ADVANCED
				: ($qualified ?
					CstRequest::QUALIFIED :
					CstRequest::STANDARD);
			$this->standard = $standard || (!$advanced && !$qualified);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function get(): string {
		return $this->value;
	}

	public function getNormalized(): string {
		return $this->valueNormalized;
	}

	public function isAdvanced(): bool
	{
		return $this->advanced;
	}

	public function isQualified(): bool
	{
		return $this->qualified;
	}

	public function isStandard(): bool
	{
		return $this->standard;
	}
}
