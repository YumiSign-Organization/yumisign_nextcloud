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

namespace OCA\YumiSignNxtC\Controller;

use OCA\RCDevs\Utility\LogRCDevs;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SettingsService;
use OCA\YumiSignNxtC\Utility\Constantes\CstCommon;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class PersonalSettingsController extends Controller implements ISettings
{
	private	ConfigurationService	$configurationService;
	private	string					$serverUrl;
	private IConfig					$config;
	private IUserManager			$userManager;
	private string					$currentUserId;
	private string|null				$redirectUri;
	private string|null				$code;
	private string|null				$state;

	public function __construct(
		IConfig						$config,
		IRequest					$request,
		IUserManager				$userManager,
		private	IInitialState		$initialState,
		private	IURLGenerator		$urlGenerator,
		private	IUserSession		$userSession,
		private	SettingsService		$settingsService,
		private LogRCDevs			$logRCDevs,
		string						$UserId,
		string						$AppName,
	) {
		parent::__construct($AppName, $request);

		$this->config			= $config;
		$this->currentUserId	= $UserId;
		$this->request			= $request;
		$this->userManager		= $userManager;

		$this->configurationService = new ConfigurationService($config);

		$this->redirectUri		= $request->getParam('redirect_uri');
		$this->code		= $request->getParam('code');
		$this->serverUrl		= $this->configurationService->getUrlApp() ?? '';
		$this->state			= $request->getParam('state');
	}

	public function accessTokenRefreshToken(): array
	{
		return $this->settingsService->accessTokenRefreshToken($this->code, $this->redirectUri);
	}

	public function checkAccessToken(): array
	{
		return $this->settingsService->checkAccessToken();
	}

	public function getForm(): TemplateResponse
	{
		$initialSettings = [
			// 'clientId'	=> $this->config->getAppValue($this->configurationService->getClientId(), 'client_id'),
			'clientId'			=> $this->configurationService->getClientId(),
			'state'				=> hash_hmac(CstCommon::SHA256, $this->currentUserId . intval(time()) . md5(intval(time())), md5(intval(time()))),
			'ymsApiAuthorize'	=> $this->configurationService->getUrlIntegAppsAuthorize(),
		];
		$this->initialState->provideInitialState('initialSettings', $initialSettings);
		$this->logRCDevs->debug(sprintf('Initial Personal Settings provided : [%s]', json_encode($initialSettings)));

		Util::addScript($this->configurationService->getAppId(), $this->configurationService->getAppId() . '-personal-settings');

		return new TemplateResponse($this->configurationService->getAppId(), 'settings/personal-settings', [], '');
	}

	public function getPriority(): int
	{
		return 0;
	}

	public function getSection(): string
	{
		return $this->configurationService->getAppId();
	}
}
