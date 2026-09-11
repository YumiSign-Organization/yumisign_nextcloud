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

namespace OCA\YumiSignNxtC\BackgroundJob;

// RCDevs App
use OCA\YumiSignNxtC\Constant\CstSettings;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use OCA\YumiSignNxtC\Service\SignService;

class CheckAsyncSignatureTask extends TimedJob
{
	private $signService;

	public function __construct(
		ITimeFactory $time,
		SignService $signService,
		ConfigurationService $configurationService,
		IAppConfig $appConfig
	) {
		parent::__construct($time);
		$this->signService = $signService;

		$cron_interval = (int) $appConfig->getValueInt(
			$configurationService->getAppId(),
			CstSettings::CRON_INTERVAL,
			default: $configurationService->getCronInterval(),
		) * 59;

		parent::setInterval($cron_interval);
	}

	protected function run($arguments)
	{
		try {
			$this->signService->checkAsyncSignatureTask();

			return null;
		} catch (\Throwable $th) {
			throw $th;
		}
	}
}
