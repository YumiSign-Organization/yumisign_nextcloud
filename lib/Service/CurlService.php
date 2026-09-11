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

namespace OCA\YumiSignNxtC\Service;

use CURLFile;
use Exception;
use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Service\CurlService as RCDevsCurlService;
use OCA\YumiSignNxtC\RCDevs\Service\FileService;
use OCA\YumiSignNxtC\Constant\CstCurl;
use OCA\YumiSignNxtC\Constant\CstException;
use OCA\YumiSignNxtC\Constant\CstLogMessages;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCP\IAppConfig;

class CurlService extends RCDevsCurlService
{
	private		ConfigurationService	$configurationService;

	private function isLocalStubTlsUrl(
		string $url
	): bool {
		$parsedUrl = parse_url($url);
		$scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
		$host = strtolower((string) ($parsedUrl['host'] ?? ''));
		$port = intval($parsedUrl['port'] ?? 443);

		return (
			$scheme === 'https'
			&& $host === 'devnextcloud'
			&& $port === 18080
		);
	}

	public function __construct(
		private	IAppConfig		$config,
		private	LogRCDevs	$logRCDevs,
	) {
		parent::__construct(
			$this->config,
			$this->logRCDevs,
		);

		$this->configurationService = new ConfigurationService($config);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function setCurlEnv(
		string $url,
		string $requestType,
		bool $contentTypeJson = true
	): void {
		parent::setCurlEnv($url, $requestType, $contentTypeJson);

		// Local stub certificate is self-signed in dev environment.
		if ($this->isLocalStubTlsUrl($url)) {
			$this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
			$this->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
		}
	}

	public function addPreferences(
		array $dataPost,
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			/**
			 * //	TODO	Remove these helpDebug rows
			 */
			$this->setCurlEnv($this->configurationService->getUrlPreferences($workflowId), CstCurl::POST);
			// $urlPreferences = $this->configurationService->getUrlPreferences($workflowId);
			// $cstCurlPost = CstCurl::POST;
			// $this->setCurlEnv($urlPreferences, $cstCurlPost);
			/**
			 * END
			 */
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function addRecipients(
		array $dataPut,
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlRecipients($workflowId), CstCurl::PUT);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPut));

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			// $return = new CurlEntity();
			throw $th;
		}

		return $return;
	}

	public function addSteps(
		array $dataPost,
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			$this->logRCDevs->debug("Add steps URL  : {$this->configurationService->getUrlSteps($workflowId)}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->debug("Add steps DATA : " . json_encode($dataPost), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlSteps($workflowId), CstCurl::POST);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function cancelWorkflow(
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlCancel($workflowId), CstCurl::PUT);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function createWorkflow(
		array $dataPost,
		string $workflowName
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			/**
			 * //	TODO	Remove these helpDebug rows
			 */
			$this->setCurlEnv($this->configurationService->getUrlWorkflows(), CstCurl::POST, false);
			// $getUrlWorkflows=$this->configurationService->getUrlWorkflows();
			// $cstcurlPost = CstCurl::POST;
			// $this->setCurlEnv($getUrlWorkflows, $cstcurlPost, false);
			/**
			 * END
			 */
			$this->setOpt(CURLOPT_VERBOSE, true);
			// $this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));
			$this->setOpt(CURLOPT_POSTFIELDS, $dataPost);
			$dataPostTmp=$dataPost;
			unset($dataPostTmp['document']);
			$this->logRCDevs->debug('Data sent to YumiSign server : ' . json_encode($dataPostTmp), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();
			$this->logRCDevs->debug('YumiSign server returned : ' . json_encode($return), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));


			// Add name if workflow has been created
			if (isset(json_decode($return->getBody())->id)) {
				$tmpResponse = json_decode($return->getBody());
				$tmpResponse->name = $workflowName;

				$return->setBody(json_encode($tmpResponse));
			}

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function createWorkspace(
		array $dataPost
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlWorkspaces(), CstCurl::POST, false);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function deleteWorkflows(
		array $dataPost
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlWorkflows(), CstCurl::DELETE, false);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function getDebrief(
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlDebrief($workflowId), CstCurl::GET);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function getDocument(
		string $url
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($url, CstCurl::GET, contentTypeJson: false);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_HEADER, 0);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function getEnvelopes(
		array $envelopesIds
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlEnvelopes($envelopesIds), CstCurl::GET, contentTypeJson: false);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	//	TODO	TBR
	public function getPostDataArray(
		string $workflowName,
		string $applicantName,
		FileService $fileToSign,
		string $signType,
		int $expiryDate
	): array {
		$return = [];
		try {
			$return = array(
				'name' => $workflowName,
				'document' => new CURLFILE(
					'data://application/octet-stream;base64,' . base64_encode($fileToSign->getContent()),
					$fileToSign->getMimeType(),
					$fileToSign->getName()
				),
				'type' => $signType,
				'expiryDate' => $expiryDate,
				'senderName' => $applicantName,
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = [];
			throw new Exception(CstException::POST_DATA_ARRAY, 1);
		}

		return $return;
	}

	public function getSession(
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlSession($workflowId), CstCurl::GET);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function getWorkflows(): CurlEntity
	{
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlWorkflows(), CstCurl::GET, false);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function getWorkspacesId(): CurlEntity
	{
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlWorkspacesId(), CstCurl::GET, false);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function retrieveAccessTokenRefreshToken(
		array $dataPost
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlOpenAuthorization(), CstCurl::POST, true);
			$this->setOpt(CURLOPT_VERBOSE, true);
			$this->setOpt(CURLOPT_POSTFIELDS, json_encode($dataPost));

			$this->logRCDevs->debug(sprintf('DataPost :--%s--', json_encode($dataPost)),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			//	TODO	Old code to transform - P3
			// // Check response validity: fields "access_token" and "refresh_token" have to be present
			// $this->curlService->checkCurlCode(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			// $this->curlService->checkCurlBody(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}

	public function startWorkflow(
		int $workflowId
	): CurlEntity {
		$return = new CurlEntity();

		try {
			// Init cUrl
			$this->setCurlEnv($this->configurationService->getUrlStartWorkflow($workflowId), CstCurl::PUT);
			$this->setOpt(CURLOPT_VERBOSE, true);

			// $this->logRCDevs->debug(curl_getinfo($this->curlHandleBundle, CURLINFO_HEADER_OUT),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Call YumiSign server
			$return = $this->getCurlResponse();

			curl_close($this->curlHandleBundle);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(sprintf("The cUrl response is : [%s]", json_encode($return)),					__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = new CurlEntity();
		}

		return $return;
	}
}
