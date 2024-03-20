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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Controller;

use \OCP\AppFramework\Http\RedirectResponse;
use Exception;
use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCA\YumiSignNxtC\Service\CurlResponse;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\Utility;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Share\IShare;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse as HttpFoundationJsonResponse;

class SignController extends Controller
{

	private $userId;
	private $signService;
	private $logger;
	private $mapper;

	/** @var IUserManager */
	private $userManager;

	public function __construct(
		$AppName,
		IRequest $request,
		SignService $signService,
		$UserId,
		LoggerInterface $logger,
		SignSessionMapper $mapper,
		IUserManager $userManager,
		private ISearch $search,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->signService = $signService;
		$this->logger = $logger;
		$this->mapper = $mapper;

		$this->userManager = $userManager;
	}

	/**
	 * @NoAdminRequired
	 */
	public function mobileSign()
	{
		// Retrieve current user email from Nextcloud database
		$currentUser = $this->userManager->get($this->userId);

		// If userId is an email, not needed to search an email...
		if (filter_var($this->userId, FILTER_VALIDATE_EMAIL)) {
			$currentUserEmail = $this->userId;
		} else {
			$currentUserEmail = $currentUser->getEMailAddress();
		}


		if (empty($currentUserEmail) && empty($this->request->getParam('email'))) {
			$resp['code'] = false;
			$resp['message'] = "No email address found for this user";
		} else {
			$resp = $this->signService->asyncExternalMobileSignPrepare(
				$this->request->getParam('path'),
				$currentUserEmail,
				$this->userId,
				$this->urlGenerator->getAbsoluteURL($this->request->getParam('appUrl')),
				$this->request->getParam('signatureType'),
				$this->request->getParam('fileId')
			);

			// Squeeze Designer if signature type is not SIMPLE
			if (strcasecmp($this->request->getParam('signatureType'), Constante::get(Cst::YMS_SIMPLE)) !== 0) {
				$resp = $this->signService->asyncExternalMobileSignSubmit(
					$resp['workspaceId'],
					$resp['workflowId'],
					$resp['envelopeId']
				);
			}
		}

		return new JSONResponse([
			'code' => $resp['code'],
			'message'			=> Utility::getArrayData($resp, 'message',     false),
			'session'			=> Utility::getArrayData($resp, 'session',     false),
			'designerUrl'		=> Utility::getArrayData($resp, 'designerUrl', false),
			'workspaceId'		=> Utility::getArrayData($resp, 'workspaceId', false),
			'workflowId'		=> Utility::getArrayData($resp, 'workflowId',  false),
			'envelopeId'		=> Utility::getArrayData($resp, 'envelopeId',  false),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function asyncLocalMobileSign()
	{
		// Retrieve chosen user email from Nextcloud database
		$nextcloudUser = $this->userManager->get($this->request->getParam('username'));

		if (empty($nextcloudUser->getEMailAddress()) && empty($this->request->getParam('email'))) {
			$resp['code'] = false;
			$resp['message'] = "No email address found for this user";
		} else {
			$resp = $this->signService->asyncExternalMobileSignPrepare(
				$this->request->getParam('path'),
				(empty($nextcloudUser->getEMailAddress()) ? $this->request->getParam('email') : $nextcloudUser->getEMailAddress()),
				$this->userId,
				$this->urlGenerator->getAbsoluteURL($this->request->getParam('appUrl')),
				$this->request->getParam('signatureType'),
				$this->request->getParam('fileId'),
			);

			// Squeeze Designer if signature type is not SIMPLE
			if (strcasecmp($this->request->getParam('signatureType'), Constante::get(Cst::YMS_SIMPLE)) !== 0) {
				$resp = $this->signService->asyncExternalMobileSignSubmit($resp['workspaceId'], $resp['workflowId'], $resp['envelopeId']);
			}
		}

		return new JSONResponse([
			'code'			=> $resp['code'],
			'message'		=> $resp['message'],
			'session'		=> $resp['session'],
			'designerUrl'	=> $resp['designerUrl'],
			'workspaceId'	=> $resp['workspaceId'],
			'workflowId'	=> $resp['workflowId'],
			'envelopeId'	=> $resp['envelopeId'],
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function asyncExternalMobileSign()
	{
		$resp = $this->signService->asyncExternalMobileSignPrepare(
			$this->request->getParam('path'),
			$this->request->getParam('email'),
			$this->userId,
			$this->urlGenerator->getAbsoluteURL($this->request->getParam('appUrl')),
			$this->request->getParam('signatureType'),
			$this->request->getParam('fileId'),
		);

		// Squeeze Designer if signature type is not SIMPLE
		if (strcasecmp($this->request->getParam('signatureType'), Constante::get(Cst::YMS_SIMPLE)) !== 0) {
			$resp = $this->signService->asyncExternalMobileSignSubmit($resp['workspaceId'], $resp['workflowId'], $resp['envelopeId']);
		}

		return new JSONResponse([
			'code'				=> array_key_exists('code', $resp) ? $resp['code'] : '',
			'message'			=> array_key_exists('message', $resp) ? $resp['message'] : '',
			'session'			=> array_key_exists('session', $resp) ? $resp['session'] : '',
			'designerUrl'		=> array_key_exists('designerUrl', $resp) ? $resp['designerUrl'] : '',
			'workspaceId'		=> array_key_exists('workspaceId', $resp) ? $resp['workspaceId'] : '',
			'workflowId'		=> array_key_exists('workflowId', $resp) ? $resp['workflowId'] : '',
			'envelopeId'		=> array_key_exists('envelopeId', $resp) ? $resp['envelopeId'] : '',
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function asyncExternalMobileSignSubmit($workspaceId = null, $workflowId = null, $envelopeId = null, $url = null)
	{
		try {
			if (!is_null($workspaceId) && !is_null($workflowId) && !is_null($envelopeId) && !is_null($url)) {
				$resp = $this->signService->asyncExternalMobileSignSubmit($workspaceId, $workflowId, $envelopeId);

				return new RedirectResponse($url);
			}
		} catch (\Throwable $th) {
			return $th->getMessage();
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function cancelSignRequest()
	{
		$resp = $this->signService->cancelSignRequest($this->request->getParam('envelopeId'), $this->userId);

		return new JSONResponse([
			'code' => $resp['code'],
			'message' => $resp['message']
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function forceDeletion()
	{
		$recipient = ($this->request->getParam('recipient') ? $this->request->getParam('recipient') : '');
		$resp = $this->signService->cancelSignRequest($this->request->getParam('envelopeId'), $this->userId, true, $recipient);

		return new JSONResponse([
			'code' => strval($resp['code']),
			'message' => $resp['message']
		]);
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index()
	{
		Util::addScript('yumisign_nextcloud', 'yumisign_nextcloud-index');
		return new TemplateResponse('yumisign_nextcloud', 'index');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function webhook()
	{
		$resp = $this->signService->webhook($this->request->getHeader("YUMISIGN-SIGNATURE"), $this->request->getParams());
		return new JSONResponse([
			'code'		=> strval(Utility::getArrayData($resp, 'code',    false)),
			'message'	=>        Utility::getArrayData($resp, 'message', false),
		]);
	}
}
