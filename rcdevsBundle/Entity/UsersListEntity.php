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

namespace OCA\RCDevs\Entity;

use Exception;
use JsonSerializable;
use OCA\RCDevs\Utility\Constantes\CstException;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUserManager;

class UsersListEntity implements JsonSerializable
{
	public array $list;

	public function __construct(
		private IConfig				$config,
		private IRootFolder			$rootFolder,
		private IUserManager		$userManager,
		private string|array|null	$userIds = null,
		private string|array|null	$emailAddresses = null,
	) {
		if (empty($userIds) && empty($emailAddresses)) {
			throw new Exception(CstException::NO_RECIPIENT);
		}

		if (empty($userIds)) {
			// Process emails
			$dataList = $emailAddresses;
			$processIds = !($processEmails = true);
		} else {
			// Process Ids
			$dataList = $userIds;
			$processEmails = !($processIds = true);
		}

		if (is_array($dataList)) {
			$dataArray = $dataList;
		} else {
			$dataArray = explode(";", str_replace(",", ";", $dataList));
		}

		// Add intel to final list
		foreach ($dataArray as $key => $unitData) {
			$user = new UserEntity(
				$this->config,
				$this->rootFolder,
				$this->userManager,
				id: ($processIds ? $unitData : null),
				emailAddress: ($processEmails ? $unitData : null),
			);

			$this->list[] = $user;
		}
	}

	public function areInternalRecipients(): bool
	{
		return !empty($userIds);
	}

	public function jsonSerialize(): mixed
{
	return [
		'list' => $this->list,
		'users' => $this->userIds,
		'emails' => $this->emailAddresses,
	];

}
}
