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
use OCA\YumiSignNxtC\RCDevs\Constant\CstEntity;
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\SettingsService;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class AdminSettingsController extends Controller
{
	private	ConfigurationService	$configurationService;
	private IAppConfig				$config;

	public function __construct(
		string $AppName,
		IRequest $request,
		IAppConfig $config,
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
			CstReturn::CODE		=> $resp[CstReturn::CODE],
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
			CstReturn::CODE		=> $resp[CstReturn::CODE],
			CstReturn::MESSAGE	=> $resp[CstReturn::MESSAGE],
			CstRequest::STATUS	=> $resp[CstRequest::STATUS],
		]);
	}

	public function saveSettings()
	{
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::API_KEY,					$this->request->getParam(CstEntity::API_KEY));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::ASYNC_TIMEOUT,			(int) $this->request->getParam(CstEntity::ASYNC_TIMEOUT));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::CLIENT_ID,				$this->request->getParam(CstEntity::CLIENT_ID));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::CLIENT_SECRET,			$this->request->getParam(CstEntity::CLIENT_SECRET));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::CRON_INTERVAL,			(int) $this->request->getParam(CstEntity::CRON_INTERVAL));

		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::ENABLE_SIGN,				(int) $this->request->getParam(CstEntity::ENABLE_SIGN));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::OVERWRITE,				(int) $this->request->getParam(CstEntity::OVERWRITE));

		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::PROXY_HOST,				$this->request->getParam(CstEntity::PROXY_HOST));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::PROXY_PASSWORD,			$this->request->getParam(CstEntity::PROXY_PASSWORD));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::PROXY_PORT,				$this->request->getParam(CstEntity::PROXY_PORT));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::PROXY_USERNAME,			$this->request->getParam(CstEntity::PROXY_USERNAME));

		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::SIGN_TYPE_ADVANCED,		(int) $this->request->getParam(CstEntity::SIGN_TYPE_ADVANCED));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::SIGN_TYPE_QUALIFIED,		(int) $this->request->getParam(CstEntity::SIGN_TYPE_QUALIFIED));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::SIGN_TYPE_STANDARD,		(int) $this->request->getParam(CstEntity::SIGN_TYPE_STANDARD));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::TEXTUAL_COMPLEMENT_SIGN,	$this->request->getParam(CstEntity::TEXTUAL_COMPLEMENT_SIGN));
		$this->config->setValueInt($this->configurationService->getAppId(), CstEntity::USE_PROXY,				(int) $this->request->getParam(CstEntity::USE_PROXY));
	}
}