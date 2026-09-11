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

namespace OCA\YumiSignNxtC\RCDevs\Service;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstSettings;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTypes;
use OCA\YumiSignNxtC\RCDevs\Entity\ProxyEntity;
use OCA\YumiSignNxtC\RCDevs\Helper\ArrayObject;

// Nextcloud Core
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class ConfigurationService
{
	protected string		$rcdevsAppId;
	// From info.xml
	protected string		$appId;
	protected string		$applicationName;
	protected string		$appNamespace;

	// From config.xml
	protected array			$configXml;
	protected array			$statusDone;
	protected array			$statusIssue;
	protected array			$statusPending;
	protected int|null		$cnxTimeOut;
	protected int|null		$cronInterval;
	protected int|null		$itemsListing;
	protected int|null		$multipleFactorAsync;
	protected int|null		$multipleFactorSync;
	protected int|null		$uiItemsPerPage;
	protected string		$apiKeyName;
	protected string		$appMainLogo;
	protected string		$appNameAbbr;
	protected string		$appNameShort;
	protected string		$appNameSigned;
	protected string		$appTableNameSessions;
	protected string		$checkCron;
	protected string		$tokenName;
	protected string		$userSignedFolderApplicant;
	protected string		$userSignedFolderRecipient;
	protected string|null	$appNameSealed;
	protected string|null	$logConfig;
	protected string|null	$logFilename;

	private	LogRCDevs	$logRCDevs;

	public function __construct(
		private	IAppConfig	$config,
	) {
		$currentPathFile = pathinfo(__FILE__, PATHINFO_DIRNAME);

		/**
		 * Read info.xml file
		 */
		$infoXml = json_decode(json_encode(simplexml_load_string(file_get_contents("{$currentPathFile}/../../appinfo/info.xml"))), true);

		$this->appId			= $infoXml['id'];
		$this->applicationName	= $infoXml['name'];
		$this->appNamespace		= $infoXml['namespace'];

		// Cron check job
		// Retrieve the specific job
		$jobs = $infoXml['background-jobs']['job'];
		if (is_string($jobs)) {
			$jobs = [$jobs]; // A single job in the XML → we put it in an array
		}

		// $checkAsyncSignatureTask = null;
		foreach ($jobs as $job) {
			if (str_contains($job, 'CheckAsyncSignatureTask')) {
				// $checkAsyncSignatureTask = $job;
				$this->checkCron = $job;
				break;
			}
		}

		/**
		 * Read config.xml file
		 */
		$this->configXml = json_decode(json_encode(simplexml_load_string(file_get_contents("{$currentPathFile}/../../appinfo/config.xml"))), true);

		$this->apiKeyName			= ArrayObject::getIfExists('api-key-name',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->appMainLogo			= ArrayObject::getIfExists('app-main-logo',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->appNameSigned		= ArrayObject::getIfExists('name-signed',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->appNameAbbr			= ArrayObject::getIfExists('app-name-abbr',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->appNameShort			= ArrayObject::getIfExists('app-name-short',		$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->appTableNameSessions	= ArrayObject::getIfExists('table-name-sessions',	$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->cronInterval			= ArrayObject::getIfExists('cron-interval',			$this->configXml, returnNull: false, forceType: CstTypes::INT);
		$this->itemsListing			= ArrayObject::getIfExists('items-per-listing',		$this->configXml, returnNull: false, forceType: CstTypes::INT);
		$this->uiItemsPerPage		= ArrayObject::getIfExists('items-per-page',		$this->configXml, returnNull: false, forceType: CstTypes::INT);

		// Logs
		$this->logConfig			= ArrayObject::getIfExists('log-config',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);
		$this->logFilename			= ArrayObject::getIfExists('log-filename',			$this->configXml, returnNull: false, forceType: CstTypes::STRING);

		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);
		$this->logRCDevs = new LogRCDevs($this, $logger);

		//	TODO	Move to module (out from Bundle)
		// // Exceptions for specific modules
		$this->appNameSealed		= ArrayObject::getIfExists('name-sealed',			$this->configXml, returnNull: true, forceType: CstTypes::STRING);
		$this->tokenName			= ArrayObject::getIfExists('token-name',			$this->configXml, returnNull: true, forceType: CstTypes::STRING);

		// Status
		$this->statusPending		= explode(',', strtolower(ArrayObject::getIfExists('status-pending',	$this->configXml, returnNull: true, forceType: CstTypes::STRING)));
		$this->statusDone			= explode(',', strtolower(ArrayObject::getIfExists('status-done',		$this->configXml, returnNull: true, forceType: CstTypes::STRING)));
		$this->statusIssue			= explode(',', strtolower(ArrayObject::getIfExists('status-issue',		$this->configXml, returnNull: true, forceType: CstTypes::STRING)));

		// Timeout
		$this->multipleFactorAsync	= ArrayObject::getIfExists('multiplication-factor-asynchronous',		$this->configXml, returnNull: false, forceType: CstTypes::INT);
		$this->multipleFactorSync	= ArrayObject::getIfExists('multiplication-factor-synchronous',			$this->configXml, returnNull: false, forceType: CstTypes::INT);
		$this->cnxTimeOut			= ArrayObject::getIfExists('cnx-time-out',								$this->configXml, returnNull: false, forceType: CstTypes::INT);

		// User signed folder
		$this->userSignedFolderApplicant = (string) ($this->configXml['user-signed-folder-applicant'] ?? '');
		$this->userSignedFolderRecipient = (string) ($this->configXml['user-signed-folder-recipient'] ?? '');
	}

	/** ******************************************************************************************
	 * PROTECTED
	 ****************************************************************************************** */

	protected function convertForVueJsSwitch(
		int|null $settingToConvert,
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

	protected function intCompare(string $columnName): bool
	{
		$return = ($this->config->getValueInt($this->getAppId(), $columnName) === 1);
		return $return;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function _getSetting(
		string	$settingName,
		bool	$integer		= false,
		bool	$switch			= false,
		bool	$array			= false,
	): array|bool|int|string {
		/**@var array|bool|int|string $returned */
		$returned = '';
		try {
			if ($integer || $switch) {
				$returned = $this->config->getValueInt($this->getAppId(), $settingName);
				if ($switch) {
					$returned = $this->convertForVueJsSwitch($returned);
				}
			} else { // String value
				$returned = $this->config->getValueString($this->getAppId(), $settingName);
				if ($array) {
					$returned = (is_null(json_decode($returned, true)) ? ['', ''] : json_decode($returned, true));
				}
			}

			$this->logRCDevs->debug(
				vsprintf('Returned: [%s] for following parameters $settingName: %s, $integer: %s, $switch: %s, $array: %s', [
					json_encode((string) $returned),
					$settingName,
					$integer,
					$switch,
					$array
				]),
				__FUNCTION__
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(
				vsprintf('Error: [%s] for following parameters $settingName: %s, $integer: %s, $switch: %s, $array: %s', [
					$th->getMessage(),
					$settingName,
					$integer,
					$switch,
					$array
				]),
				__FUNCTION__,
				throw: true
			);
		}

		return $returned;
	}

	public function doyouOverwrite(): bool
	{
		try {
			return $this->intCompare(CstSettings::OVERWRITE);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApiKey(): string
	{
		try {
			return $this->config->getValueString($this->getAppId(), CstSettings::API_KEY);
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

	public function getApplicationNameAbbr(): string
	{
		try {
			return $this->appNameAbbr;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getApplicationNameShort(): string
	{
		try {
			return $this->appNameShort;
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

	public function getCheckCron(): string
	{
		try {
			return $this->checkCron;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getCnxTimeOut(): int
	{
		try {
			return $this->cnxTimeOut;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getCronInterval(): int
	{
		try {
			return $this->cronInterval;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getItemsListing(): int
	{
		try {
			return $this->itemsListing;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getLogConfig(): string
	{
		try {
			return $this->logConfig;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getLogFilename(): string
	{
		try {
			return $this->logFilename;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getMultipleFactorAsync(): int
	{
		try {
			return $this->multipleFactorAsync;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getMultipleFactorSync(): int
	{
		try {
			return $this->multipleFactorSync;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getStatusDone(): array
	{
		try {
			return $this->statusDone;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getStatusIssue(): array
	{
		try {
			return $this->statusIssue;
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

	public function getTimeout(bool $asynchronous): int
	{
		try {
			if ($asynchronous) {
				return $this->config->getValueInt($this->getAppId(), CstSettings::ASYNC_TIMEOUT) * 86400;
			} else {
				return $this->config->getValueInt($this->getAppId(), CstSettings::SYNC_TIMEOUT) * 60;
			}
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

	public function getUiItemsPerPage(): int|null
	{
		try {
			return $this->uiItemsPerPage;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUserSignedFolderApplicant(): string
	{
		try {
			return $this->userSignedFolderApplicant;
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function getUserSignedFolderRecipient(): string
	{
		try {
			return $this->userSignedFolderRecipient;
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
			return $this->intCompare(CstSettings::USE_PROXY);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSign(): bool
	{
		try {
			return $this->intCompare(CstSettings::ENABLE_SIGN);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignFormatCades(): bool
	{
		try {
			return $this->intCompare(CstSettings::SIGN_FORMAT_CADES);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignFormatPades(): bool
	{
		try {
			return $this->intCompare(CstSettings::SIGN_FORMAT_PADES);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypeAdvanced(): bool
	{
		try {
			return $this->intCompare(CstSettings::SIGN_TYPE_ADVANCED);
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function isEnabledSignTypeQualified(): bool
	{
		try {
			return $this->intCompare(CstSettings::SIGN_TYPE_QUALIFIED);
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
			return $this->intCompare(CstSettings::SIGN_TYPE_STANDARD);
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
					$proxy->$key = $this->config->getValueString($this->getAppId(), "proxy_{$key}");
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

	public function textualComplementSign(): string
	{
		try {
			return $this->config->getValueString($this->getAppId(), CstSettings::TEXTUAL_COMPLEMENT_SIGN);
		} catch (\Throwable $th) {
			throw $th;
		}
	}
}
