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

namespace OCA\YumiSignNxtC\Service;

use OCA\RCDevs\Service\ConfigurationService as RCDevsConfigurationService;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCP\IConfig;

class ConfigurationService extends RCDevsConfigurationService
{
	private		string		$urlAppNoApi;
	private		string 		$urlArchive;
	private		string 		$urlCancel;
	private		string 		$urlDebrief;
	private		string 		$urlEnvelopes;
	private		string 		$urlIntegAppsAuthorize;
	private		string 		$urlOpenAuthorization;
	private		string 		$urlPreferences;
	private		string 		$urlRecipients;
	private		string 		$urlSession;
	private		string 		$urlStartWorkflow;
	private		string 		$urlSteps;
	private		string 		$urlWorkflows;
	private		string 		$urlWorkspaces;
	private		string 		$urlWorkspacesId;
	protected	string		$urlApp;

	public function __construct(
		private		IConfig	$config,
	) {
		parent::__construct($config);

		/**
		 * Read config.xml file
		 */
		$this->urlApp					= rtrim($this->configXml['url-app'], DIRECTORY_SEPARATOR);
		$this->urlAppNoApi				= rtrim($this->configXml['url-app-no-api'], DIRECTORY_SEPARATOR);
		$this->urlArchive				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-archive'], DIRECTORY_SEPARATOR);
		$this->urlCancel				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-cancel'], DIRECTORY_SEPARATOR);
		$this->urlDebrief				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-debrief'], DIRECTORY_SEPARATOR);
		$this->urlEnvelopes				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-envelopes'], DIRECTORY_SEPARATOR);
		$this->urlIntegAppsAuthorize	= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-integ-apps-authorize'], DIRECTORY_SEPARATOR);
		$this->urlOpenAuthorization		= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-open-authorization'], DIRECTORY_SEPARATOR);
		$this->urlPreferences			= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-preferences'], DIRECTORY_SEPARATOR);
		$this->urlRecipients			= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-recipients'], DIRECTORY_SEPARATOR);
		$this->urlSession				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-session'], DIRECTORY_SEPARATOR);
		$this->urlStartWorkflow			= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-start-workflow'], DIRECTORY_SEPARATOR);
		$this->urlSteps					= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-steps'], DIRECTORY_SEPARATOR);
		$this->urlWorkflows				= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-workflows'], DIRECTORY_SEPARATOR);
		$this->urlWorkspaces			= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-workspaces'], DIRECTORY_SEPARATOR);
		$this->urlWorkspacesId			= DIRECTORY_SEPARATOR . ltrim($this->configXml['url-workspaces-id'], DIRECTORY_SEPARATOR);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
 
	public function getClientId(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), 'client_id');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getClientSecret(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), 'client_secret');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlApp(): string
	{
		try {
			return $this->urlApp;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlAppNoApi(): string
	{
		try {
			return $this->urlAppNoApi;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlArchive(): string
	{
		try {
			return $this->getUrlApp() . $this->urlArchive;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlCancel(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlCancel, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlDebrief(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlDebrief, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlEnvelopes(array $envelopesIds): string
	{
		try {
			// Create array parameters
			$envelopesParameters = '';
			foreach ($envelopesIds as $key => $envelopeIdArray) {
				$envelopeId = $envelopeIdArray[CstEntity::ENVELOPE_ID];
				$envelopesParameters .= "&ids[]={$envelopeId}";
			}

			return $this->getUrlApp() . sprintf($this->urlEnvelopes, $envelopesParameters);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlIntegAppsAuthorize(): string
	{
		try {
			return $this->getUrlAppNoApi() . $this->urlIntegAppsAuthorize;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlOpenAuthorization(): string
	{
		try {
			return $this->getUrlApp() . $this->urlOpenAuthorization;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlPreferences(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlPreferences, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlRecipients(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlRecipients, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlSession(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlSession, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlStartWorkflow(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlStartWorkflow, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlSteps(int $workflowId): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlSteps, [
				$this->getWorkspaceId(),
				$workflowId,
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlWorkflows(): string
	{
		try {
			$urlWorkflows = $this->getUrlApp() . sprintf($this->urlWorkflows, $this->getWorkspaceId());
			return $urlWorkflows;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlWorkspaces(): string
	{
		try {
			return $this->getUrlApp() . $this->urlWorkspaces;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUrlWorkspacesId(): string
	{
		try {
			return $this->getUrlApp() . vsprintf($this->urlWorkspacesId, [
				$this->getWorkspaceId(),
			]);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getWorkspaceId(): string
	{
		try {
			$workspaceId = $this->config->getAppValue($this->getAppId(), CstEntity::WORKSPACE_ID);
			return $workspaceId;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getWorkspaceName(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), CstEntity::WORKSPACE_NAME);
		} catch (\Throwable $th) {
			throw $th;
		}
	}
}
