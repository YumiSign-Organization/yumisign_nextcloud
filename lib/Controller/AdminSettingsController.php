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

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Controller\AdminSettingsController as RCDevsSettingsController;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;
use OCA\YumiSignNxtC\Constant\CstCommon;
use OCA\YumiSignNxtC\Constant\CstConfig;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SettingsService;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Settings\ISettings;
use OCP\IUserManager;
use OCP\Util;

class AdminSettingsController extends Controller implements ISettings
{
	private	ConfigurationService		$configurationService;
	private	RCDevsSettingsController	$rcdevsSettingsController;
	private	string						$serverUrl;
	private	string						$workspaceId;
	private	string						$workspaceName;
	private IAppConfig					$config;

	public function __construct(
		IAppConfig $config,
		IRequest $request,
		private SettingsService $settingsService,
		private IInitialState $initialState,
		private LogRCDevs $logRCDevs,
		private IUserManager $userManager,
		private string $userId,
		string $AppName
	) {
		parent::__construct($AppName, $request);

		$this->config	= $config;
		$this->request	= $request;

		$this->configurationService = new ConfigurationService($config);

		// Common RCDevs Settings controller
		$this->rcdevsSettingsController = new RCDevsSettingsController(
			$AppName,
			$request,
			$config,
			$settingsService,
		);

		$this->serverUrl		= $this->configurationService->getUrlApp() ?? '';
		$this->workspaceId		= (string) ($request->getParam('workspace_id') ?? $this->configurationService->getWorkspaceId());
		$this->workspaceName	= $request->getParam('workspace_name')	?? ($this->configurationService->getWorkspaceName()	?? '');
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function convertForVueJsSwitch(
		$settingToConvert
	): bool {
		switch (true) {
			case empty($settingToConvert):
				return false;
				break;

			case intval($settingToConvert) === 0:
				return false;
				break;

			case intval($settingToConvert) === 1:
				return true;
				break;

			default:
				return false;
				break;
		}
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function checkCronStatus(): JSONResponse
	{
		return $this->rcdevsSettingsController->checkCronStatus();
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkEnabledSign(): JSONResponse
	{
		$response = $this->rcdevsSettingsController->checkEnabledSign()->getData();
		$response['debugVueJs'] = $this->configurationService->getDebugVueJs();

		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkServerUrl(): JSONResponse
	{
		// Get the YumiSign server url status (OK/KO) for the Settings page
		$resp = $this->rcdevsSettingsController->checkServerUrl("{$this->serverUrl}/workspaces/{$this->workspaceId}");
		$data = Helpers::getArrayData($resp, CstReturn::DATA, false);
		$status = Helpers::getArrayData(is_array($data) ? $data : null, CstRequest::STATUS, false);

		$resp[CstRequest::ID] = (Helpers::isValidHttpResponse($resp) ? $this->workspaceId : 0);

		return new JSONResponse([
			CstReturn::CODE	=> $resp[CstReturn::CODE],
			CstRequest::ID		=> $resp[CstRequest::ID],
			CstReturn::MESSAGE	=> $resp[CstReturn::MESSAGE],
			CstRequest::STATUS	=> $status,
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkSignTypes(): JSONResponse
	{
		return $this->rcdevsSettingsController->checkSignTypes();
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkModalBootstrap(): JSONResponse
	{
		$serverCheck = $this->checkServerUrl()->getData();
		$signTypes = $this->checkSignTypes()->getData();
		$appCheck = $this->checkEnabledSign()->getData();

		$currentUserEmail = '';
		try {
			$currentUser = $this->userManager->get($this->userId);
			if ($currentUser !== null) {
				$currentUserEmail = $currentUser->getEMailAddress() ?? '';
			}
		} catch (\Throwable $th) {
			$currentUserEmail = '';
		}

		return new JSONResponse([
			CstReturn::CODE => 0,
			CstReturn::DATA => [
				'settingsCheck' => $serverCheck,
				'settingsCheckApp' => $appCheck,
				'settingsCheckTypes' => $signTypes,
				'userEmail' => $currentUserEmail,
				'userId' => $this->userId,
			],
			CstReturn::ERROR => null,
			CstReturn::MESSAGE => null,
		]);
	}

	private function checkWorkspace(): JSONResponse
	{
		$resp = $this->settingsService->checkWorkspace("{$this->serverUrl}/workspaces", $this->workspaceName, $this->workspaceId);

		return new JSONResponse([
			CstCommon::CODE		=> $resp[CstCommon::CODE],
			CstCommon::STATUS	=> $resp[CstCommon::STATUS],
			CstCommon::ID		=> $resp[CstCommon::ID],
			CstCommon::LIST_ID	=> $resp[CstCommon::LIST_ID],
			CstCommon::MESSAGE	=> $resp[CstCommon::MESSAGE]
		]);
	}

	public function checkWorkspaceId(): JSONResponse
	{
		return $this->checkWorkspace();
	}

	public function checkWorkspaceName(): JSONResponse
	{
		return $this->checkWorkspace();
	}

	public function getForm(): TemplateResponse
	{
		$initialSettings = [
			'apiKey'				=> $this->config->getValueString($this->configurationService->getAppId(), 'api_key'),
			'asyncTimeout'			=> $this->config->getValueInt($this->configurationService->getAppId(), 'async_timeout', default: 1),
			'clientId'				=> $this->config->getValueString($this->configurationService->getAppId(), 'client_id'),
			'clientSecret'			=> $this->config->getValueString($this->configurationService->getAppId(), 'client_secret'),
			'cronInterval'			=> $this->config->getValueInt($this->configurationService->getAppId(), 'cron_interval', default: 5),
			'description'			=> $this->config->getValueString($this->configurationService->getAppId(), 'description'),
			'enableSign'			=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'enable_sign')),
			'installedVersion'		=> $this->config->getValueString($this->configurationService->getAppId(), 'installed_version'),
			'overwrite'				=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'overwrite')),
			'proxyHost'				=> $this->config->getValueString($this->configurationService->getAppId(), 'proxy_host'),
			'proxyPassword'			=> $this->config->getValueString($this->configurationService->getAppId(), 'proxy_password'),
			'proxyPort'				=> $this->config->getValueString($this->configurationService->getAppId(), 'proxy_port'),
			'proxyUsername'			=> $this->config->getValueString($this->configurationService->getAppId(), 'proxy_username'),
			'sign_type_advanced'		=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'sign_type_advanced')),
			'sign_type_qualified'		=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'sign_type_qualified')),
			'sign_type_standard'		=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'sign_type_standard')),
			'textualComplementSign'	=> $this->config->getValueString($this->configurationService->getAppId(), 'textual_complement_sign'),
			'useProxy'				=> $this->convertForVueJsSwitch($this->config->getValueInt($this->configurationService->getAppId(), 'use_proxy')),
			'workspaceId'			=> $this->config->getValueString($this->configurationService->getAppId(), 'workspace_id'),
			'workspaceName'			=> $this->config->getValueString($this->configurationService->getAppId(), 'workspace_name'),
		];

		$this->initialState->provideInitialState('debugVueJs', $this->configurationService->getDebugVueJs());
		$this->initialState->provideInitialState('initialSettings', $initialSettings);
		$this->logRCDevs->debug(sprintf('Initial Admin Settings provided : [%s]', json_encode($initialSettings)));

		Util::addScript($this->configurationService->getAppId(), $this->configurationService->getAppId() . '-admin-settings');

		return new TemplateResponse($this->configurationService->getAppId(), 'settings/admin-settings', [], '');
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int
	{
		return 55;
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string
	{
		return $this->configurationService->getAppId();
	}

	public function resetJob(): JSONResponse
	{
		return $this->rcdevsSettingsController->resetJob();
	}

	public function saveSettings()
	{
		// Common data
		$this->rcdevsSettingsController->saveSettings();

		// Specific App data
		$this->config->setValueString($this->configurationService->getAppId(), CstConfig::DESCRIPTION,	$this->request->getParam(CstConfig::DESCRIPTION));
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::WORKSPACE_ID,	$this->workspaceId);
		$this->config->setValueString($this->configurationService->getAppId(), CstEntity::WORKSPACE_NAME,	$this->request->getParam(CstEntity::WORKSPACE_NAME));

		return new JSONResponse([
			'code' => 0,
		]);
	}
}
