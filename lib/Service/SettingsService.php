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

use Exception;
use OC\URLGenerator;
use OCA\RCDevs\Entity\CurlEntity;
use OCA\RCDevs\Service\SettingsService as RCDevsSettingsService;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\RCDevs\Utility\WarningException;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\CurlService;
use OCA\YumiSignNxtC\Utility\Constantes\CstCommon;
use OCA\YumiSignNxtC\Utility\Constantes\CstCurl;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCA\YumiSignNxtC\Utility\Constantes\CstException;
use OCA\YumiSignNxtC\Utility\Constantes\CstMessage;
use OCA\YumiSignNxtC\Utility\Constantes\CstRequest;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use Throwable;

class SettingsService extends RCDevsSettingsService
{
	private		ConfigurationService	$configurationService;
	private		string					$userId;
	protected	CurlService				$curlService;

	public function __construct(
		private		IAccountManager		$accountManager,
		private		IConfig				$config,
		private		IConfig				$systemConfig,
		private		IDateTimeFormatter	$formatter,
		private		IL10N				$l10nYmsSettingsService,
		private		IRootFolder			$rootFolder,
		private		IUserManager		$userManager,
		private		IUserSession		$userSession,
		private		SignSessionMapper	$mapper,
		private		URLGenerator		$urlGenerator,
		protected	LogRCDevs			$logRCDevs,
		string							$UserId,
	) {
		parent::__construct(
			$config,
			$l10nYmsSettingsService,
			$mapper,
			$logRCDevs,
			$UserId,
		);

		$this->configurationService = new ConfigurationService($config);

		$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		$this->curlService = new CurlService($config, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);

		$this->userId = $UserId;
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	public function checkWspCommon(array $unitWorkspace, string $workspaceName, string $workspaceId)
	{
		if (empty($workspaceId)) {
			return (Helpers::areEqual(
				Helpers::getArrayData($unitWorkspace, CstCommon::NAME, missingForbidden: true),
				$workspaceName
			));
		} else {
			return (Helpers::areEqual(
				Helpers::getArrayData($unitWorkspace, CstCommon::NAME, missingForbidden: true),
				$workspaceName
			) &&
				Helpers::getArrayData($unitWorkspace, CstRequest::ID, missingForbidden: true) === intval($workspaceId));
		}
	}

	private function retrieveAccessTokenRefreshToken(
		array $oAuthIntel
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->retrieveAccessTokenRefreshToken($oAuthIntel);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
		}

		return $curlResponse;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function accessTokenRefreshToken(string $code, string $redirectUri): array
	{
		$returned = [];

		try {

			$appUrl = $this->urlGenerator->getBaseUrl();
			$this->logRCDevs->debug(sprintf('$appUrl : [%s]', $appUrl), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$curlAccessToken = $this->retrieveAccessTokenRefreshToken([
				CstRequest::CLIENT_ID		=> $this->configurationService->getClientId(),
				CstRequest::CLIENT_SECRET	=> $this->configurationService->getClientSecret(),
				CstRequest::REDIRECT_URI	=> $redirectUri,
				CstRequest::CODE			=> $code,
				CstRequest::GRANT_TYPE		=> CstCommon::CODE,
			]);
			$this->logRCDevs->debug('YumiSign server returned : ' . json_encode($curlAccessToken), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$tokenData		= json_decode($curlAccessToken->getBody(), true);
			$accessToken	= Helpers::getIfExists(CstRequest::ACCESS_TOKEN, $tokenData, returnNull: true);
			$refreshToken	= Helpers::getIfExists(CstRequest::REFRESH_TOKEN, $tokenData, returnNull: true);
			$this->logRCDevs->debug(vsprintf('Token intel : tokenData is %s, accessToken is %s, refreshToken is %s', [
				$tokenData,
				$accessToken,
				$refreshToken
			]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Check validity response
			if (is_null($accessToken) || is_null($refreshToken)) {
				throw new Exception(CstException::TOKEN_RETRIEVAL, 0);
			}

			// Store Token data in Personal Settings table for this user
			$this->config->setUserValue($this->userId, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, $accessToken);
			$this->config->setUserValue($this->userId, $this->configurationService->getAppId(), CstEntity::REFRESH_TOKEN, $refreshToken);

			$returned = [
				CstRequest::CODE	=> 1,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> null,
				CstRequest::MESSAGE	=> CstMessage::ACCESS_TOKEN_REGISTERED,
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		$this->logRCDevs->debug('Fct for Token returned : ' . json_encode($returned), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		return $returned;
	}

	/**
	 * Check if a token is already registered
	 * 
	 * @since	1.28.5
	 * @author	E.J.BAYSSETTE <eric.bayssette@rcdevs.com>
	 * 
	 * @return	array{code: int, data: array{state: string}, error: int|null, message: string} 
	 * @throws	Throwable 
	 * @throws	Exception 
	 */
	public function checkAccessToken()
	{
		$returned = [];
		$hmac = hash_hmac(CstCommon::SHA256, $this->userId . intval(time()), md5(intval(time())));

		try {
			// just read the Personal Settings data to check if the current user has the YumiSign Access Token filled
			$accessToken = $this->config->getUserValue($this->userId, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, default: null);

			if (is_null($accessToken)) {
				throw new WarningException(CstMessage::NO_TOKEN_REGISTERED);
			}

			$returned = [
				CstRequest::CODE	=> 1,
				CstRequest::DATA	=> [CstRequest::STATE => $hmac],
				CstRequest::ERROR	=> null,
				CstRequest::MESSAGE	=> CstMessage::ACCESS_TOKEN_REGISTERED,
			];
		} catch (WarningException $th) {
			$this->logRCDevs->warning($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstRequest::CODE	=> 1, // 1 because it is not an exception, just a missing token
				CstRequest::DATA	=> [
					CstRequest::STATE => $hmac,
					CstRequest::TOKENREGISTERED => false,
				],
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	public function checkWorkspace(string $serverUrl, string $workspaceName, string $workspaceId)
	{
		$returned = [];
		$returned[CstCommon::LIST_ID] = [];
		$returned[CstRequest::ID] = '';

		try {
			// Init cUrl
			$this->curlService->setCurlEnv($serverUrl, CstCurl::GET, false);
			$this->curlService->setOpt(CURLOPT_TIMEOUT, 10);
			$this->curlService->setOpt(CURLOPT_VERBOSE, true);

			// Call YumiSign server
			$curlResponse = $this->curlService->getCurlResponse();

			$this->curlService->closeHandle();

			// Check response validity
			$this->curlService->checkCurlCode(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->curlService->checkCurlBody(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$found = false;
			$workspaces = json_decode($curlResponse->getBody(), true);

			// Verify received intel from YumiSign server
			foreach ($workspaces as $unitWorkspace) {
				if (
					$this->checkWspCommon($unitWorkspace, $workspaceName, $workspaceId)
				) {
					// Common behaviour based on Name value
					$found = true;
					$returned[CstRequest::CODE] = $returned[CstCommon::STATUS] = true;
					$returned[CstCommon::NAME] = $returned[CstRequest::MESSAGE] = Helpers::getArrayData($unitWorkspace, CstCommon::NAME, true, true);

					// According to the ID value
					if (!empty($workspaceId)) {
						// Name & ID are filled => found this pair
						$returned[CstRequest::ID] = Helpers::getArrayData($unitWorkspace, CstRequest::ID, true, true);
						break;
					} else {
						// Just a valid Name but maybe several exist => add to the IDs list
						$returned[CstCommon::LIST_ID][] = Helpers::getArrayData($unitWorkspace, CstRequest::ID, true, true);
					}
				}
			}

			// If only one ID is found, convert IDs list to unique ID
			if (sizeof($returned[CstCommon::LIST_ID]) === 1) {
				$returned[CstRequest::ID] = $returned[CstCommon::LIST_ID][0];
			}

			if (!$found) $this->logRCDevs->info(sprintf($this->l10nYmsSettingsService->t("The workspace named \"%s\" was not found on YumiSign server"), $workspaceName), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstCommon::LIST_ID	=> [],
				CstCommon::STATUS	=> false,
				CstRequest::CODE	=> false,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::ID		=> '',
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
			// $returned[CstRequest::ID] = '';
			// $returned[CstCommon::LIST_ID] = [];
			// $returned[CstRequest::CODE] = $returned[CstCommon::STATUS] = false;
			// $returned[CstRequest::MESSAGE] = $th->getMessage();
		}

		return $returned;
	}
}
