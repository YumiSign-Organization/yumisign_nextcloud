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
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstTransactionType;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\TokenService;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

class OAuthController extends Controller
{
	const	YMS_OAUTH_SESSION	=	'yms_oauth_sess';
	private	ConfigurationService	$configurationService;

	public function __construct(
		IRequest $request,
		private TokenService $tokenService,
		private IClientService $http,
		private IAppConfig $config,
		private ISecureRandom $secureRandom,
		private ISession $session,
		private ITimeFactory $timeFactory,
		private IURLGenerator $urlGen,
		private LogRCDevs $logRCDevs,
		private string $UserId,
		string $AppName
	) {
		parent::__construct($AppName, $request);
		$this->configurationService = new ConfigurationService($config);
	}

	/** Helper: base64url without padding */
	private function b64url(
		string $bin
	): string {
		return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
	}

	/**
	 * Step 1: start OAuth — server-side redirect to YumiSign /authorize.
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function connect(): RedirectResponse
	{
		// Capture origin (nice UX: where to go back after finishing)
		$originUrl = $this->request->getHeader('referer') ?: $this->urlGen->linkToRouteAbsolute('settings.PersonalSettings.index', ['section' => $this->configurationService->getAppId()]);

		// Generate state, PKCE and optional nonce
		$state			= $this->b64url(random_bytes(32));
		$codeVerifier	= $this->b64url(random_bytes(64));
		$nonce			= $this->b64url(random_bytes(16));

		// Persist context in session (one-time + TTL)
		$now = $this->timeFactory->getTime();
		$ctx = [
			'state'         => $state,
			'code_verifier' => $codeVerifier,
			'nonce'         => $nonce,
			'created_at'    => $now,
			'ttl'           => $this->configurationService->getTTL(),
			'origin'        => $originUrl,
		];
		$this->session->set(self::YMS_OAUTH_SESSION, json_encode($ctx, JSON_UNESCAPED_SLASHES));

		// Build redirect_uri (absolute route of callback)
		$redirectUri = $this->urlGen->linkToRouteAbsolute('yumisign_nextcloud.oauth.callback');

		// Build authorize URL (server-side redirect)
		$query = http_build_query([
			'response_type'         => 'code',
			'client_id'             => $this->configurationService->getClientId(),
			'redirect_uri'          => $redirectUri,
			'scope'                 => $this->configurationService->getScope(),
			'state'                 => $state,
		], '', '&', PHP_QUERY_RFC3986);

		return new RedirectResponse($this->configurationService->getUrlIntegAppsAuthorize() . '?' . $query);
	}

	/**
	 * Step 2: callback — validate state/TTL, exchange code (server-side), then redirect back.
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function callback(): RedirectResponse
	{
		// From query parameters
		$code	= (string)($this->request->getParam('code')  ?? '');
		$state	= (string)($this->request->getParam('state') ?? '');
		$err	= (string)($this->request->getParam('error') ?? '');

		// Restore and clear one-time ctx
		$raw = $this->session->get(self::YMS_OAUTH_SESSION);
		$this->session->remove(self::YMS_OAUTH_SESSION);

		// Default fallback after flow
		$fallback = $this->urlGen->linkToRouteAbsolute('settings.PersonalSettings.index', ['section' => $this->configurationService->getAppId()]);

		if ($err !== '') {
			// Provider error → back to settings with an error flag if you wish
			return new RedirectResponse($fallback . '?yms_error=' . rawurlencode($err));
		}
		if (!$raw) {
			return new RedirectResponse($fallback . '?yms_error=missing_session_ctx');
		}

		$ctx = json_decode((string)$raw, true) ?: [];
		$origin = (string)($ctx['origin'] ?? $fallback);

		$this->logRCDevs->debug(vsprintf('fallback: [%s] / origin: [%s] / ctx: [%s]', [$fallback, $origin, json_encode($ctx)]));

		// Basic validations
		if ($code === '' || $state === '' || !isset($ctx['state']) || !hash_equals($ctx['state'], $state)) {
			return new RedirectResponse($origin . '?yms_error=invalid_state');
		}

		// TTL
		$now = $this->timeFactory->getTime();
		$created = (int)($ctx['created_at'] ?? 0);
		$ttl     = (int)($ctx['ttl'] ?? 0);
		if ($created <= 0 || $ttl <= 0 || ($now - $created) > $ttl) {
			return new RedirectResponse($origin . '?yms_error=state_expired');
		}

		// PKCE
		$codeVerifier = (string)($ctx['code_verifier'] ?? '');
		if ($codeVerifier === '') {
			return new RedirectResponse($origin . '?yms_error=missing_pkce');
		}

		$returned = $this->tokenService->retrieveTokens(
			$code,
			$this->urlGen->linkToRouteAbsolute('yumisign_nextcloud.oauth.callback'),
			$this->UserId
		);

		$redirectTo	= Helpers::getIfExists('redirect_to', $returned[CstReturn::DATA], returnNull: false);
		$this->logRCDevs->debug('YMS redirect : ' . $redirectTo, __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

		return new RedirectResponse($origin . $redirectTo);
	}

	/**
	 * Check Access Token is valid.
	 * So not missing and not expired
	 * 
	 * @since	1.31.1
	 * @author	E.J.BAYSSETTE <eric.bayssette@rcdevs.com>
	 * 
	 * @NoAdminRequired
	 */
	public function checkAccessToken(): array
	{
		return $this->tokenService->checkAccessToken($this->UserId);
	}

	/**
	 * Delete Access Token.
	 * 
	 * @since	1.31.1
	 * @author	E.J.BAYSSETTE <eric.bayssette@rcdevs.com>
	 * 
	 * @NoAdminRequired
	 */
	public function deleteAccessToken(): array
	{
		return $this->tokenService->deleteAccessToken($this->UserId);
	}

	public function refreshToken(): array
	{
		return $this->tokenService->refreshToken($this->UserId);
	}
}
