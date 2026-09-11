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

namespace OCA\YumiSignNxtC\RCDevs\Controller;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstLogMessages;
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransactionType;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use Exception;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class SignController extends Controller
{
	public function __construct(
		IRequest $request,
		private ConfigurationService $configurationService,
		private LogRCDevs $logRCDevs,
		string $AppName
	) {
		$this->request = $request;
		parent::__construct($AppName, $request);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	/**
	 * @NoAdminRequired
	 */
	public function signLocalAsyncAdvanced(): array
	{
		$returned = [];

		try {
			switch (true) {
				case $this->configurationService->isEnabledSign() && $this->configurationService->isEnabledSignTypeAdvanced():
					$returned = [
						CstReturn::CODE	=> 1,
						CstReturn::DATA	=> null,
						CstReturn::ERROR	=> null,
						CstReturn::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception(CstLogMessages::SIGN_DISABLED, 1);
					break;

				case !$this->configurationService->isEnabledSignTypeAdvanced():
					throw new Exception(CstLogMessages::CANNOT_SIGN_DISABLED, 1);
					break;

				default:
					throw new Exception(CstLogMessages::SOMETHING_WRONG, 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	/**
	 * @NoAdminRequired
	 */
	public function signLocalAsyncQualified(): array
	{
		$returned = [];

		try {
			switch (true) {
				case $this->configurationService->isEnabledSign() && $this->configurationService->isEnabledSignTypeQualified():
					$returned = [
						CstReturn::CODE	=> 1,
						CstReturn::DATA	=> null,
						CstReturn::ERROR	=> null,
						CstReturn::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception(CstLogMessages::SIGN_DISABLED, 1);
					break;

				case !$this->configurationService->isEnabledSignTypeQualified():
					throw new Exception(CstLogMessages::CANNOT_SIGN_DISABLED, 1);
					break;

				default:
					throw new Exception(CstLogMessages::SOMETHING_WRONG, 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	/**
	 * @NoAdminRequired
	 */
	public function signLocalAsyncStandard(): array
	{
		$returned = [];

		try {
			switch (true) {
				case $this->configurationService->isEnabledSign() && $this->configurationService->isEnabledSignTypeStandard():
					$returned = [
						CstReturn::CODE	=> 1,
						CstReturn::DATA	=> null,
						CstReturn::ERROR	=> null,
						CstReturn::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception(CstLogMessages::SIGN_DISABLED, 1);
					break;

				case !$this->configurationService->isEnabledSignTypeStandard():
					throw new Exception(CstLogMessages::CANNOT_SIGN_DISABLED, 1);
					break;

				default:
					throw new Exception(CstLogMessages::SOMETHING_WRONG, 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}
}
