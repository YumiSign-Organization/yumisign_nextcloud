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
use OCA\YumiSignNxtC\RCDevs\Constant\CstApplication;
use OCA\YumiSignNxtC\RCDevs\Constant\CstDate;

// Nextcloud Core
use OCP\IConfig;
use OCP\IUserSession;

class DateService
{
	public const FORMAT_DEFAULT		= 'Y-m-d_H:i:s';
	public const FORMAT_FILENAME	= 'Y-m-d_H.i.s';
	public const FORMAT_SHORT		= 'Y-m-d';

	public function __construct(
		private IUserSession $userSession,
		private IConfig $config
	) {}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	private function commonFormat(string $format): string
	{
		return (new \DateTimeImmutable())->format($format);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function formatCurrentUser(int $timestamp, string $format = self::FORMAT_DEFAULT): string
	{
		$user = $this->userSession->getUser();
		$timezone = CstDate::UTC;

		if ($user !== null) {
			$tz = $this->config->getUserValue($user->getUID(), CstApplication::CORE, CstDate::TIMEZONE, '');
			if (!empty($tz)) {
				$timezone = $tz;
			}
		} else {
			$timezone = date_default_timezone_get();
		}

		return (new \DateTimeImmutable("@{$timestamp}"))
			->setTimezone(new \DateTimeZone($timezone))
			->format($format);
	}

	public function formatDefault(string $format = self::FORMAT_DEFAULT): string
	{
		return $this->commonFormat($format);
	}

	public function formatFile(): string
	{
		return $this->commonFormat(self::FORMAT_FILENAME);
	}

	public function formatShort(): string
	{
		return $this->commonFormat(self::FORMAT_SHORT);
	}
}
