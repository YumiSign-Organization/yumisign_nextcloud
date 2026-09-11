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

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\WarningException;
use OCA\YumiSignNxtC\Constant\CstCommon;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstMessage;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstTransactionType;

// Nextcloud Core
use Exception;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use Throwable;

class TokenService
{
	private	ConfigurationService	$configurationService;
	protected	CurlService			$curlService;

	public function __construct(
		private IClientService $http,
		private IConfig $config,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IURLGenerator $urlGen,
		private LogRCDevs $logRCDevs
	) {
		$this->configurationService = new ConfigurationService($appConfig);

		$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		$this->curlService = new CurlService($appConfig, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	/** 
	 * Compute and persist absolute access token expiration for the current user.
	 * 
	 * @param int $expiresIn Lifetime in seconds as returned by the token endpoint (expires_in).
	 * @return void
	 */
	private function storeAccessExpireAt(
		int $expiresIn,
		string $uid
	): void {
		// Defensive: negative or zero -> consider already expired
		$expiresAt = time() + max(0, (int)$expiresIn);
		// Persist as unix epoch seconds in user config
		$this->config->setUserValue(
			$uid,
			$this->configurationService->getAppId(),
			'access_expire_at',
			(string)$expiresAt
		);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function retrieveTokens(
		string $code,
		string $redirectUri,
		string $uid
	): array {
		$returned = [];

		try {
			$client = $this->http->newClient();
			$redirectUri = $this->urlGen->linkToRouteAbsolute('yumisign_nextcloud.oauth.callback');

			$resp = $client->post(
				$this->configurationService->getUrlOAuthAccess(),
				[
					'headers' => ['Content-Type' => 'application/json'],
					'body'    => json_encode([
						CstRequest::CLIENT_ID		=> $this->configurationService->getClientId(),
						CstRequest::CLIENT_SECRET	=> $this->configurationService->getClientSecret(),
						CstRequest::REDIRECT_URI	=> $redirectUri,
						CstReturn::CODE			=> $code,
						CstRequest::GRANT_TYPE		=> CstCommon::CODE,
					], JSON_UNESCAPED_SLASHES),
					'timeout' => 15,
				]
			);

			$tokens = json_decode((string)$resp->getBody(), true) ?: [];

			// Persist tokens per-user (example using IConfig; replace with your DAO if needed)
			if (!empty($tokens[CstRequest::ACCESS_TOKEN])) {
				$this->config->setUserValue($uid, $this->configurationService->getAppId(), CstRequest::ACCESS_TOKEN, $tokens[CstRequest::ACCESS_TOKEN]);
			}
			if (!empty($tokens[CstRequest::REFRESH_TOKEN])) {
				$this->config->setUserValue($uid, $this->configurationService->getAppId(), CstRequest::REFRESH_TOKEN, $tokens[CstRequest::REFRESH_TOKEN]);
			}
			if (!empty($tokens['id_token'])) {
				// OPTIONAL: verify ID Token (signature, audience, nonce == $ctx['nonce'], exp, iat)
				$this->config->setUserValue($uid, $this->configurationService->getAppId(), 'id_token', $tokens['id_token']);
			}
			if (!empty($tokens['expires_in'])) {
				$this->storeAccessExpireAt((int)$tokens['expires_in'], $uid);
			}

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> ['redirect_to' => '?yms_connected=1'],
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> CstMessage::ACCESS_TOKEN_REGISTERED,
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> ['redirect_to' => '?yms_error=' . rawurlencode('token_exchange_failed')],
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
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
	public function checkAccessToken(
		string $uid
	): array {
		$returned = [];

		try {
			// Just read the Personal Settings data to check if the current user has the YumiSign Access Token filled
			$accessToken = $this->config->getUserValue($uid, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, default: null);

			if (is_null($accessToken)) {
				throw new WarningException(CstMessage::NO_TOKEN_REGISTERED);
			}

			$tokenExpired	= $this->isTokenExpired($uid);
			if ($tokenExpired) {
				throw new WarningException(CstMessage::TOKEN_EXPIRED);
			}

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> [
					CstRequest::TOKENREGISTERED => true,
					'tokenExpired'				=> $tokenExpired,
					// 'expiresAt'					=> $expiresAt,   // unix epoch seconds
					// 'secondsLeft'				=> $secondsLeft, // convenience for UI
				],
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> CstMessage::ACCESS_TOKEN_REGISTERED,
			];
		} catch (WarningException $th) {
			$this->logRCDevs->warning($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 1, // 1 because it is not an exception, just a missing token
				CstReturn::DATA	=> [CstRequest::TOKENREGISTERED => false,],
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	/**
	 * Delete the current user's Access Token + Refresh Token
	 * 
	 * @since	1.31.1
	 * @author	E.J.BAYSSETTE <eric.bayssette@rcdevs.com>
	 * 
	 * @return	array{code: int, data: array{state: string}, error: int|null, message: string} 
	 * @throws	Throwable 
	 * @throws	Exception 
	 */
	public function deleteAccessToken(
		string $uid
	): array {
		$returned = [];

		try {
			// Just read the Personal Settings data to check if the current user has the YumiSign Access Token filled
			$accessToken = $this->config->getUserValue($uid, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, default: null);

			if (is_null($accessToken)) {
				throw new WarningException(CstMessage::NO_TOKEN_REGISTERED);
			}

			$refreshToken	= $this->config->getUserValue($uid, $this->configurationService->getAppId(), CstEntity::REFRESH_TOKEN, default: null);

			// Delete Access Token
			$this->config->deleteUserValue($uid, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN);

			// Delete Refresh Token if exists
			if (!empty($refreshToken)) {
				$this->config->deleteUserValue($uid, $this->configurationService->getAppId(), CstEntity::REFRESH_TOKEN);
			}

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> [
					CstRequest::TOKENREGISTERED => false,
					'tokenDeleted'				=> true,
				],
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> CstMessage::TOKEN_DELETED,
			];
		} catch (WarningException $th) {
			$this->logRCDevs->warning($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 1, // 1 because it is not an exception, just a missing token
				CstReturn::DATA	=> [CstRequest::TOKENREGISTERED => false,],
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	/**
	 * Check if this Access Token is expired (using access_expire_at)
	 * If access_expire_at is missing or in the past, consider it expired
	 * 
	 * @since	1.31.1
	 * @author	E.J.BAYSSETTE <eric.bayssette@rcdevs.com>
	 * 
	 * @return bool 
	 * @throws Throwable 
	 */
	public function isTokenExpired(
		string $uid
	): bool {
		$expireAtRaw	= $this->config->getUserValue($uid, $this->configurationService->getAppId(), 'access_expire_at', default: null);
		$expiresAt		= is_null($expireAtRaw) || $expireAtRaw === '' ? 0 : (int)$expireAtRaw;
		$now			= time();
		// $secondsLeft	= $expiresAt > 0 ? max(0, $expiresAt - $now) : 0;

		return ($expiresAt === 0) ? true : ($now >= $expiresAt);
	}

	/**
	 * Refresh the access token using the user's refresh_token.
	 *
	 * @param string|null $uid If null, uses the current user.
	 * @return array True on success, false otherwise.
	 */
	public function refreshToken(
		string $uid
	): array {
		$returned = [];

		try {

			$encRefresh = $this->config->getUserValue($uid, $this->configurationService->getAppId(), CstRequest::REFRESH_TOKEN, '');
			if (empty($encRefresh)) {
				throw new Exception(CstMessage::MISSING_REFRESH_TOKEN, 1);
			}

			$client = $this->http->newClient();
			$resp = $client->post(
				$this->configurationService->getUrlOAuthRefresh(),
				[
					'headers' => ['Content-Type' => 'application/json'],
					'body'    => json_encode([
						CstRequest::CLIENT_ID		=> $this->configurationService->getClientId(),
						CstRequest::CLIENT_SECRET	=> $this->configurationService->getClientSecret(),
						// CstRequest::GRANT_TYPE		=> CstRequest::REFRESH_TOKEN,
						CstRequest::REFRESH_TOKEN	=> $encRefresh,
					], JSON_UNESCAPED_SLASHES),
					'timeout' => 15,
				]
			);

			$tokens = json_decode((string)$resp->getBody(), true) ?: [];
			if (empty($tokens[CstRequest::ACCESS_TOKEN])) {
				throw new Exception(CstMessage::MISSING_ACCESS_TOKEN, 1);
			}

			// Update tokens
			$this->config->setUserValue($uid, $this->configurationService->getAppId(), CstRequest::ACCESS_TOKEN, $tokens[CstRequest::ACCESS_TOKEN]);
			if (!empty($tokens[CstRequest::REFRESH_TOKEN])) {
				$this->config->setUserValue($uid, $this->configurationService->getAppId(), CstRequest::REFRESH_TOKEN, $tokens[CstRequest::REFRESH_TOKEN]);
			}
			if (!empty($tokens['expires_in'])) {
				$this->storeAccessExpireAt((int)$tokens['expires_in'], $uid);
			}

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> [CstRequest::TOKENREGISTERED => true,],
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> CstMessage::TOKEN_REFRESHED,
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}
}
