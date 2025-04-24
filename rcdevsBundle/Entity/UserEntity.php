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

use DateTime;
use JsonSerializable;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUserManager;

class UserEntity implements JsonSerializable
{
	private Folder|null		$folder;
	private string|null		$displayName;

	protected IConfig		$systemConfig;

	public function __construct(
		private IConfig $config,
		private IRootFolder		$rootFolder,
		private IUserManager	$userManager,
		private string|null		$id,
		private string|null		$emailAddress = null,
	) {
		$user = is_null($this->id) ? null : $this->userManager->get($this->id);
		$this->folder = (is_null($user) ? null : $this->rootFolder->getUserFolder($user->getUID()));

		// Get user email
		if (empty($this->emailAddress)) {
			$this->emailAddress = (is_null($user)	? null : $user->getEMailAddress());
		}
		// If standard email is empty and userId is an email, use userId
		if (empty($this->emailAddress) && filter_var($this->id, FILTER_VALIDATE_EMAIL)) {
			$this->emailAddress = $this->id;
		}

		// Define user identity
		$this->displayName	= (
			is_null($user)	? $this->id : (empty($user->getDisplayName()) ? $this->id : $user->getDisplayName())
		);
	}

	public function getDisplayName(): string
	{
		return $this->displayName;
	}

	public function getEmailAddress(): string|null
	{
		return $this->emailAddress;
	}

	public function getFolder(): Folder|null
	{
		return $this->folder;
	}

	public function getId(): string|null
	{
		return $this->id;
	}

	public function getTimedLocales(\DateTime $date = new DateTime()): string
	{
		if (is_null($this->id)) {
			$timeZone = new \DateTimeZone('UTC');
		} else {
			$defaultTimeZone = date_default_timezone_get();
			$timeZone = $this->config->getUserValue($this->id, 'core', 'timezone', $defaultTimeZone);
			$timeZone = isset($timeZone) ? new \DateTimeZone($timeZone) : new \DateTimeZone('UTC');
		}

		$date->setTimezone($timeZone);
		//	TODO	add this date format in config.xml
		return $date->format('Y-m-d H:i:s');
	}

	public function jsonSerialize(): mixed
	{
		return [
			'username' => $this->id,
			'displayName' => $this->displayName,
			'email' => $this->emailAddress,
		];
	}
}
