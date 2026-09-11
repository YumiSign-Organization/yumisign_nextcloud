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
use OCA\YumiSignNxtC\RCDevs\Constant\CstLogMessages;
use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use CurlHandle;
use OCP\IAppConfig;

class CurlService
{
	private		ConfigurationService	$configurationServiceBundle;
	private		CurlEntity				$curlEntityBundle;
	protected	CurlHandle				$curlHandleBundle;
	protected	string					$credentialKeyBundle;
	
	public function __construct(
		private	IAppConfig	$configBundle,
		private	LogRCDevs	$logRCDevsBundle,
	) {
		$this->configurationServiceBundle = new ConfigurationService($configBundle);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function addCredentialKey(
		string $credentialKeyBundle
	) {
		try {
			$this->credentialKeyBundle = $credentialKeyBundle;
		} catch (\Throwable $th) {
			$this->logRCDevsBundle->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}
	}

	public function checkCurlBody(
		string $functionName
	) {
		if (is_null(json_decode($this->curlEntityBundle->getBody()))) {
			$message = sprintf("cURL returned empty body in process \"%s\".", $functionName);
			$this->logRCDevsBundle->debug($message, __FUNCTION__, true);
		}
	}

	public function checkCurlCode(
		string $functionName
	) {
		if (($this->curlEntityBundle->getCode() !== 200) && ($this->curlEntityBundle->getCode() !== 302)) {
			$message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $this->curlEntityBundle->getCode(), $functionName);
			$this->logRCDevsBundle->debug($message, __FUNCTION__, true);
		}
	}

	public function closeHandle(): void
	{
		curl_close($this->curlHandleBundle);
	}

	public function getCurlResponse(): CurlEntity
	{
		$this->curlEntityBundle = new CurlEntity($this->curlHandleBundle);

		$this->logRCDevsBundle->debug(sprintf("CURLINFO_HEADER_OUT: %s", json_encode(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT))), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

		return $this->curlEntityBundle;
	}

	public function setCurlEnv(
		string $url,
		string $requestType,
		bool $contentTypeJson = true
	): void {
		$this->curlHandleBundle = curl_init();

		curl_setopt($this->curlHandleBundle, CURLOPT_URL, $url);
		curl_setopt($this->curlHandleBundle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandleBundle, CURLOPT_CUSTOMREQUEST, $requestType);
		curl_setopt($this->curlHandleBundle, CURLOPT_HEADER, true);

		curl_setopt($this->curlHandleBundle, CURLINFO_HEADER_OUT, true);

		/** @var Proxy @proxy */
		$proxy = $this->configurationServiceBundle->proxy();

		// Proxy configuration
		if ($this->configurationServiceBundle->isEnabledProxy()) {
			curl_setopt($this->curlHandleBundle, CURLOPT_HTTPPROXYTUNNEL, 1);

			// Set the proxy IP
			curl_setopt($this->curlHandleBundle, CURLOPT_PROXY, $proxy->host);

			// Set the port
			curl_setopt($this->curlHandleBundle, CURLOPT_PROXYPORT, $proxy->port);

			// Set the username and password
			curl_setopt($this->curlHandleBundle, CURLOPT_PROXYUSERPWD, "{$proxy->username}:{$proxy->password}");
		}

		$this->logRCDevsBundle->debug("CURLOPT_HTTPHEADER: {$this->configurationServiceBundle->getApiKeyName()}:{$this->credentialKeyBundle}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

		curl_setopt($this->curlHandleBundle, CURLOPT_HTTPHEADER, [
			// "{$this->configurationServiceBundle->getApiKeyName()}:{$this->apikey}",
			$this->credentialKeyBundle,
			($contentTypeJson ? 'content-type:application/json' : ''),
		]);
	}

	public function setOpt(
		int $option,
		mixed $value
	): void {
		curl_setopt($this->curlHandleBundle, $option, $value);
	}
}
