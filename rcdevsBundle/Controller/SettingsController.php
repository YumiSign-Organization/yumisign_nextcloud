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

// RCDevs App
use OCA\OpenOTPSign\Constant\CstTransaction;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\SettingsService;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller
{
	private	ConfigurationService	$configurationService;
	private IConfig					$config;

	public function __construct(
		string $AppName,
		IRequest $request,
		IConfig $config,
		private SettingsService $settingsService
	) {
		parent::__construct($AppName, $request);

		$this->config	= $config;
		$this->request	= $request;

		$this->configurationService = new ConfigurationService($config);
	}

	/** ******************************************************************************************
	 * PROTECTED
	 ****************************************************************************************** */

	public function checkServerUrl(
		string $serverUrl
	): array {
		// Get the server url status (OK/KO) for the Settings page
		return $this->settingsService->checkServerUrl($serverUrl);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function checkCronStatus(): JSONResponse
	{
		$resp = $this->settingsService->lastJobRun();

		return new JSONResponse([
			CstReturn::CODE	=> $resp[CstReturn::CODE],
			CstReturn::MESSAGE	=> $resp[CstReturn::MESSAGE],
			CstRequest::STATUS	=> $resp[CstRequest::STATUS],
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkEnabledSign(): JSONResponse
	{
		$signTypes[CstRequest::ENABLESIGN] = $this->configurationService->isEnabledSign();

		return new JSONResponse($signTypes);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkSignTypes(): JSONResponse
	{
		return new JSONResponse($this->settingsService->checkSignTypes());
	}

	public function resetJob(): JSONResponse
	{
		$resp = $this->settingsService->resetJob();

		return new JSONResponse([
			CstReturn::CODE	=> $resp[CstReturn::CODE],
			CstRequest::STATUS	=> $resp[CstRequest::STATUS],
			CstReturn::MESSAGE	=> $resp[CstReturn::MESSAGE]
		]);
	}

	public function saveSettings()
	{
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::API_KEY,					$this->request->getParam(CstTransaction::API_KEY));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::ASYNC_TIMEOUT,			$this->request->getParam(CstTransaction::ASYNC_TIMEOUT));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::CLIENT_ID,				$this->request->getParam(CstTransaction::CLIENT_ID));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::CLIENT_SECRET,			$this->request->getParam(CstTransaction::CLIENT_SECRET));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::CRON_INTERVAL,			$this->request->getParam(CstTransaction::CRON_INTERVAL));

		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::ENABLE_SIGN,				$this->request->getParam(CstTransaction::ENABLE_SIGN));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::OVERWRITE,				$this->request->getParam(CstTransaction::OVERWRITE));

		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::PROXY_HOST,				$this->request->getParam(CstTransaction::PROXY_HOST));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::PROXY_PASSWORD,			$this->request->getParam(CstTransaction::PROXY_PASSWORD));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::PROXY_PORT,				$this->request->getParam(CstTransaction::PROXY_PORT));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::PROXY_USERNAME,			$this->request->getParam(CstTransaction::PROXY_USERNAME));

		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::SIGN_TYPE_ADVANCED,		$this->request->getParam(CstTransaction::SIGN_TYPE_ADVANCED));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::SIGN_TYPE_QUALIFIED,		$this->request->getParam(CstTransaction::SIGN_TYPE_QUALIFIED));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::SIGN_TYPE_STANDARD,		$this->request->getParam(CstTransaction::SIGN_TYPE_STANDARD));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::TEXTUAL_COMPLEMENT_SIGN,	$this->request->getParam(CstTransaction::TEXTUAL_COMPLEMENT_SIGN));
		$this->config->setAppValue($this->configurationService->getAppId(), CstTransaction::USE_PROXY,				$this->request->getParam(CstTransaction::USE_PROXY));
	}
}
