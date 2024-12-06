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

namespace OCA\RCDevs\Service;

use OCA\RCDevs\Entity\ProxyEntity;
use OCA\RCDevs\Utility\Constantes\CstEntity;
use OCA\RCDevs\Utility\Helpers;
use OCP\IConfig;

class ConfigurationService
{
	protected string $rcdevsAppId;
	// From info.xml
	protected string $appId;
	protected string $applicationName;
	protected string $appNamespace;

	// From config.xml
	protected array			$configXml;
	protected array			$statusPending;
	protected string		$apiKeyName;
	protected string		$appMainLogo;
	protected string		$appNameSigned;
	protected string		$appShortName;
	protected string		$appTableNameSessions;
	protected string		$tokenName;
	protected string		$uiItemsPerPage;
	protected string|null	$appNameSealed;

	public function __construct(
		private		IConfig	$config,
	) {
		$currentPathFile = pathinfo(__FILE__, PATHINFO_DIRNAME);

		/**
		 * Read info.xml file
		 */
		// $infoXml = simplexml_load_string(file_get_contents('../../appinfo/info.xml'));
		$infoXml = json_decode(json_encode(simplexml_load_string(file_get_contents("{$currentPathFile}/../../appinfo/info.xml"))), true);

		$this->appId			= $infoXml['id'];
		$this->applicationName	= $infoXml['name'];
		$this->appNamespace		= $infoXml['namespace'];

		/**
		 * Read config.xml file
		 */
		$this->configXml = json_decode(json_encode(simplexml_load_string(file_get_contents("{$currentPathFile}/../../appinfo/config.xml"))), true);

		$this->apiKeyName			= $this->configXml['api-key-name'];
		$this->appMainLogo			= $this->configXml['app-main-logo'];
		$this->appNameSigned		= $this->configXml['name-signed'];
		$this->appShortName			= $this->configXml['app-short-name'];
		$this->appTableNameSessions	= $this->configXml['table-name-sessions'];
		$this->uiItemsPerPage		= $this->configXml['items-per-page'];
		// Exceptions for specific modules
		$this->appNameSealed		= Helpers::getIfExists('name-sealed', $this->configXml, returnNull: true);
		$this->tokenName			= Helpers::getIfExists('token-name', $this->configXml, returnNull: true);

		$this->statusPending		= explode(',', strtolower($this->configXml['status-pending']));
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function intCompare(string $columnName): bool
	{
		$return = (intval($this->config->getAppValue($this->getAppId(), $columnName)) === 1);
		return $return;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function doyouOverwrite(): bool
	{
		try {
			return $this->intCompare('overwrite');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApiKey(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), 'api_key');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApiKeyName(): string
	{
		try {
			return $this->apiKeyName;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getAppId(): string
	{
		try {
			return $this->appId;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApplicationLogo(): string
	{
		try {
			return $this->appMainLogo;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApplicationName(): string
	{
		try {
			return $this->applicationName;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApplicationNameShort(): string
	{
		try {
			return $this->appShortName;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getAppNameSealed(): string|null
	{
		try {
			return $this->appNameSealed;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getAppNameSigned(): string
	{
		try {
			return $this->appNameSigned;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getAppNamespace(): string
	{
		try {
			return $this->appNamespace;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getAppTableNameSessions(): string
	{
		try {
			return $this->appTableNameSessions;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getStatusPending(): array
	{
		try {
			return $this->statusPending;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getTokenName(): string
	{
		try {
			return $this->tokenName;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUiItemsPerPage(): string
	{
		try {
			return $this->uiItemsPerPage;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledDemoMode(): bool
	{
		try {
			return $this->intCompare('enable_demo_mode');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledProxy(): bool
	{
		try {
			return $this->intCompare('use_proxy');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSign(): bool
	{
		try {
			return $this->intCompare(CstEntity::ENABLE_SIGN);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignFormatCades(): bool
	{
		try {
			return $this->intCompare('sign_format_cades');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignFormatPades(): bool
	{
		try {
			return $this->intCompare('sign_format_pades');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypeAdvanced(): bool
	{
		try {
			return $this->intCompare(CstEntity::SIGN_TYPE_ADVANCED);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypeQualified(): bool
	{
		try {
			return $this->intCompare(CstEntity::SIGN_TYPE_QUALIFIED);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypesAll(): bool
	{
		try {
			return
				$this->isEnabledSignTypeAdvanced() ||
				$this->isEnabledSignTypeQualified() ||
				$this->isEnabledSignTypeStandard();
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypeStandard(): bool
	{
		try {
			return $this->intCompare(CstEntity::SIGN_TYPE_STANDARD);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function proxy(): ProxyEntity
	{
		try {
			$proxy = new ProxyEntity();

			if ($this->isEnabledProxy()) {
				foreach ($proxy as $key => $value) {
					$proxy->$key = $this->config->getAppValue($this->getAppId(), "proxy_{$key}");
				}
			} else {
				foreach ($proxy as $key => $value) {
					$proxy->$key = false;
				}
			}

			return $proxy;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function serversUrls(): array
	{
		$returned = [];

		try {
			return json_decode($this->config->getAppValue($this->getAppId(), 'servers_urls'));
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function textualComplementSeal(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), 'textual_complement_seal');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function textualComplementSign(): string
	{
		try {
			return $this->config->getAppValue($this->getAppId(), 'textual_complement_sign');
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function timeout(bool $asynchronous): int
	{
		try {
			if ($asynchronous) {
				return $this->config->getAppValue($this->getAppId(), 'async_timeout') * 86400;
			} else {
				return $this->config->getAppValue($this->getAppId(), 'sync_timeout') * 60;
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}
}
