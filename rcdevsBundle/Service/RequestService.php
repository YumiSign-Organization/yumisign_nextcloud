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
use OCA\YumiSignNxtC\RCDevs\Db\TransactionMapper;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class RequestService
{
	const CNX_TIME_OUT = 3;

	public function __construct(
		protected	IAccountManager		$accountManager,
		protected	IConfig				$config,
		protected	IConfig				$systemConfig,
		protected	IDateTimeFormatter	$formatter,
		protected	IFactory			$l10nFactory,
		protected	IL10N				$l,
		protected	IL10N				$l10n,
		protected	IRootFolder			$storage,
		protected	IUserManager		$userManager,
		protected	LogRCDevs			$logRCDevs,
		protected	TransactionMapper	$mapper,
		protected	string				$UserId,
	) {}
}
