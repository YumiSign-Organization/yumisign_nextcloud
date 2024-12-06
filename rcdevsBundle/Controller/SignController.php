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

namespace OCA\RCDevs\Controller;

use Exception;
use OCA\RCDevs\Service\ConfigurationService;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use OCA\RCDevs\Utility\LogRCDevs;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class SignController extends Controller
{
	public function __construct(
		IRequest							$request,
		private ConfigurationService		$configurationService,
		private LogRCDevs					$logRCDevs,
		string								$AppName,
	) {
		$this->request = $request;
		parent::__construct($AppName, $request);
		// $this->currentUserId = $UserId;

		// $this->userManager = $userManager;

		// // Define "Sender name" which will be displayed on mobile push/email : "You received a signature request from ..."
		// $displayName = $this->userManager->get($this->currentUserId)->getDisplayName();
		// if (empty($displayName)) {
		// 	$displayName = $this->$this->currentUserId;
		// }
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
						CstRequest::CODE	=> 1,
						CstRequest::DATA	=> null,
						CstRequest::ERROR	=> null,
						CstRequest::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception('Sign process is disabled', 1);
					break;

				case !$this->configurationService->isEnabledSignTypeAdvanced():
					throw new Exception('Cannot sign with disabled Sign type', 1);
					break;

				default:
					throw new Exception('Something went wrong during this process', 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
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
						CstRequest::CODE	=> 1,
						CstRequest::DATA	=> null,
						CstRequest::ERROR	=> null,
						CstRequest::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception('Sign process is disabled', 1);
					break;

				case !$this->configurationService->isEnabledSignTypeQualified():
					throw new Exception('Cannot sign with disabled Sign type', 1);
					break;

				default:
					throw new Exception('Something went wrong during this process', 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
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
						CstRequest::CODE	=> 1,
						CstRequest::DATA	=> null,
						CstRequest::ERROR	=> null,
						CstRequest::MESSAGE	=> null,
					];
					break;

				case !$this->configurationService->isEnabledSign():
					throw new Exception('Sign process is disabled', 1);
					break;

				case !$this->configurationService->isEnabledSignTypeStandard():
					throw new Exception('Cannot sign with disabled Sign type', 1);
					break;

				default:
					throw new Exception('Something went wrong during this process', 1);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}
}
