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

namespace OCA\YumiSignNxtC\Controller;

use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Controller\UiController as RCDevsUiController;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\Config\IUserConfig;
use OCA\YumiSignNxtC\RCDevs\Service\FilesRefreshService;
use OCP\IRequest;
use OCP\IUserSession;

class UiController extends Controller
{
	private	ConfigurationService	$configurationService;
	private	RCDevsUiController	$rcdevsUiController;

	public function __construct(
		$AppName,
		IRequest $request,
		private IAppConfig $config,
		private IUserConfig $userConfig,
		private IUserSession $userSession,
		private LogRCDevs $logRCDevs,
		private FilesRefreshService $filesRefresh,
	) {
		parent::__construct($AppName, $request);

		$this->configurationService = new ConfigurationService($config);

		// Common RCDevs Settings controller
		$this->rcdevsUiController = new RCDevsUiController(
			$request,
			$this->configurationService,
			$AppName,
		);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getItemsPerPage(): JSONResponse
	{
		return $this->rcdevsUiController->getItemsPerPage();
	}

	/**
	 * @NoAdminRequired
	 */
	public function pollRefreshSignal(
		string $lastToken = ''
	): JSONResponse {
		$cdem = new CDEM();

		try {
			$user = $this->userSession->getUser();
			$userId = is_null($user) ? '' : $user->getUID();

			if ($userId === '') {
				$cdem->setOk(data: [
					'token' => '',
					'filesToken' => '',
					'scope' => 'all',
					'shouldRefresh' => false,
				]);
				return new JSONResponse($cdem->toArray());
			}

			$currentToken = $this->userConfig->getValueString(
				$userId,
				$this->configurationService->getAppId(),
				'ui_refresh_token',
				''
			);
			$currentScope = $this->userConfig->getValueString(
				$userId,
				$this->configurationService->getAppId(),
				'ui_refresh_scope',
				'all'
			);

			$shouldRefresh = ($lastToken !== '' && $currentToken !== '' && $lastToken !== $currentToken);
			$cdem->setOk(data: [
				'token' => $currentToken,
				'filesToken' => $this->filesRefresh->token($userId),
				'scope' => $currentScope,
				'shouldRefresh' => $shouldRefresh,
			]);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return new JSONResponse($cdem->toArray());
	}
}
