<?php

/**
 *
 * @copyright Copyright (c) 2021, RCDevs (info@rcdevs.com)
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
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\CurlResponse;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\Utility;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Util;

use Psr\Log\LoggerInterface;

class SignController extends Controller
{

	private $userId;
	private $signService;
	private $logger;
	private $mapper;

	/** @var IUserManager */
	private $userManager;

	public function __construct($AppName, IRequest $request, SignService $signService, $UserId, LoggerInterface $logger, SignSessionMapper $mapper, IUserManager $userManager)
	{
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
		$receiver = $this->userManager->get($this->userId);

		if (empty($receiver->getEMailAddress()) && empty($this->request->getParam('email'))) {
			$resp['code'] = false;
			$resp['message'] = "No email address found for this user";
		} else {
			$resp = $this->signService->asyncExternalMobileSignPrepare($this->request->getParam('path'), $receiver->getEMailAddress(), $this->userId, $this->request->getParam('appUrl'), $this->request->getParam('signatureType'));

			// Squeeze Designer if signature type is not SIMPLE
			if (strcasecmp($this->request->getParam('signatureType'), YMS_SIMPLE) !== 0) {
				$resp = $this->signService->asyncExternalMobileSignSubmit($resp['workspaceId'], $resp['workflowId'], $resp['envelopeId']);
			}
		}

		return new JSONResponse([
			'code' => $resp['code'],
			'message'		=> Utility::getArrayData($resp, 'message',     false),
			'session'		=> Utility::getArrayData($resp, 'session',     false),
			'designerUrl'	=> Utility::getArrayData($resp, 'designerUrl', false),
			'workspaceId'	=> Utility::getArrayData($resp, 'workspaceId', false),
			'workflowId'	=> Utility::getArrayData($resp, 'workflowId',  false),
			'envelopeId'	=> Utility::getArrayData($resp, 'envelopeId',  false),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function asyncLocalMobileSign()
	{
		// Retrieve chosen user email from Nextcloud database
		$receiver = $this->userManager->get($this->request->getParam('username'));

		if (empty($receiver->getEMailAddress()) && empty($this->request->getParam('email'))) {
			$resp['code'] = false;
			$resp['message'] = "No email address found for this user";
		} else {
			$resp = $this->signService->asyncExternalMobileSignPrepare(
				$this->request->getParam('path'),
				(empty($receiver->getEMailAddress()) ? $this->request->getParam('email') : $receiver->getEMailAddress()),
				$this->userId,
				$this->request->getParam('appUrl'),
				$this->request->getParam('signatureType')
			);

			// Squeeze Designer if signature type is not SIMPLE
			if (strcasecmp($this->request->getParam('signatureType'), YMS_SIMPLE) !== 0) {
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
			$this->request->getParam('appUrl'),
			$this->request->getParam('signatureType')
		);

		// Squeeze Designer if signature type is not SIMPLE
		if (strcasecmp($this->request->getParam('signatureType'), YMS_SIMPLE) !== 0) {
			$resp = $this->signService->asyncExternalMobileSignSubmit($resp['workspaceId'], $resp['workflowId'], $resp['envelopeId']);
		}

		return new JSONResponse([
			'code'			=> array_key_exists('code', $resp) ? $resp['code'] : '',
			'message'		=> array_key_exists('message', $resp) ? $resp['message'] : '',
			'session'		=> array_key_exists('session', $resp) ? $resp['session'] : '',
			'designerUrl'	=> array_key_exists('designerUrl', $resp) ? $resp['designerUrl'] : '',
			'workspaceId'	=> array_key_exists('workspaceId', $resp) ? $resp['workspaceId'] : '',
			'workflowId'	=> array_key_exists('workflowId', $resp) ? $resp['workflowId'] : '',
			'envelopeId'	=> array_key_exists('envelopeId', $resp) ? $resp['envelopeId'] : '',
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function asyncExternalMobileSignSubmit($workspaceId = null, $workflowId = null, $envelopeId = null, $url = null)
	{
		if (!is_null($workspaceId) && !is_null($workflowId) && !is_null($envelopeId) && !is_null($url)) {
			$resp = $this->signService->asyncExternalMobileSignSubmit($workspaceId, $workflowId, $envelopeId);

			return new RedirectResponse($url);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function testing()
	{
		return $this->signService->checkAsyncSignature();

		
		// t=1676476613,v1=358baac8de1cc5fd12c532c68638eb5a0396f03df47c3230d6cc8dcccc82576f
		$url = "https://sandbox.bayssette.fr/nextcloud-25.0.3/index.php/apps/yumisign_nextcloud/webhook";
		$request = "POST";
		$dataJson =
			substr(
				trim('
		Webhook (2023-02-27 18.34.34) : {"_route":"yumisign_nextcloud.sign.webhook","id":"20230227892cecb3400d349f66bad2c4fb099bf4","workspaceId":14933,"status":"canceled","startDate":1677522849,"scheduledStartDate":null,"createDate":1677522817,"expiryDate":1677628799,"documents":[{"id":10068,"file":{"file":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf78155793b430a7226f2d40fbe80","thumb":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf7819512e4a6604d096c2255fc4f","format":"pdf","size":976625,"name":"Reasons to use Nextcloud.pdf"},"position":1}],"steps":[{"order":1,"type":"sign","expiryDate":null,"actions":[{"status":"started","recipientId":12071,"transaction":null,"recipientEmail":"eric.james@bayssette.fr","role":12191,"type":"sign","comment":null,"stepNumber":1,"id":12276},{"status":"started","recipientId":12073,"transaction":null,"recipientEmail":"hello@ejb-info.fr","role":12193,"type":"sign","comment":null,"stepNumber":1,"id":12278}],"status":"started","id":10488}],"recipients":[{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#fd94f5","id":12191}],"email":"eric.james@bayssette.fr","id":12071,"name":"eric.james@bayssette.fr"},{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#8eb09e","id":12193}],"email":"hello@ejb-info.fr","id":12073,"name":"hello@ejb-info.fr"}],"type":"simple","metadata":[],"name":"2023-02-27_18:33:35_63fcf77fee6da8.91635760"}
		'),
		// Webhook (2023-02-27 18.34.34) : {"_route":"yumisign_nextcloud.sign.webhook","id":"20230227892cecb3400d349f66bad2c4fb099bf4","workspaceId":14933,"status":"canceled","startDate":1677522849,"scheduledStartDate":null,"createDate":1677522817,"expiryDate":1677628799,"documents":[{"id":10068,"file":{"file":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf78155793b430a7226f2d40fbe80","thumb":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf7819512e4a6604d096c2255fc4f","format":"pdf","size":976625,"name":"Reasons to use Nextcloud.pdf"},"position":1}],"steps":[{"order":1,"type":"sign","expiryDate":null,"actions":[{"status":"started","recipientId":12071,"transaction":null,"recipientEmail":"eric.james@bayssette.fr","role":12191,"type":"sign","comment":null,"stepNumber":1,"id":12276},{"status":"started","recipientId":12073,"transaction":null,"recipientEmail":"hello@ejb-info.fr","role":12193,"type":"sign","comment":null,"stepNumber":1,"id":12278}],"status":"started","id":10488}],"recipients":[{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#fd94f5","id":12191}],"email":"eric.james@bayssette.fr","id":12071,"name":"eric.james@bayssette.fr"},{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#8eb09e","id":12193}],"email":"hello@ejb-info.fr","id":12073,"name":"hello@ejb-info.fr"}],"type":"simple","metadata":[],"name":"2023-02-27_18:33:35_63fcf77fee6da8.91635760"}
				32
			);
		// 	str_replace(
		// 		'Webhook : ',
		// 		'',
		// 		'
		// 		Webhook (2023-02-27 18.34.34) : {"_route":"yumisign_nextcloud.sign.webhook","id":"20230227892cecb3400d349f66bad2c4fb099bf4","workspaceId":14933,"status":"canceled","startDate":1677522849,"scheduledStartDate":null,"createDate":1677522817,"expiryDate":1677628799,"documents":[{"id":10068,"file":{"file":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf78155793b430a7226f2d40fbe80","thumb":"https:\/\/app.yumisign.com:443\/api\/v1\/storage\/temp\/9946\/doc\/63fcf7819512e4a6604d096c2255fc4f","format":"pdf","size":976625,"name":"Reasons to use Nextcloud.pdf"},"position":1}],"steps":[{"order":1,"type":"sign","expiryDate":null,"actions":[{"status":"started","recipientId":12071,"transaction":null,"recipientEmail":"eric.james@bayssette.fr","role":12191,"type":"sign","comment":null,"stepNumber":1,"id":12276},{"status":"started","recipientId":12073,"transaction":null,"recipientEmail":"hello@ejb-info.fr","role":12193,"type":"sign","comment":null,"stepNumber":1,"id":12278}],"status":"started","id":10488}],"recipients":[{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#fd94f5","id":12191}],"email":"eric.james@bayssette.fr","id":12071,"name":"eric.james@bayssette.fr"},{"picture":{"file":null,"thumb":null,"format":" ","size":0},"roles":[{"type":"sign","color":"#8eb09e","id":12193}],"email":"hello@ejb-info.fr","id":12073,"name":"hello@ejb-info.fr"}],"type":"simple","metadata":[],"name":"2023-02-27_18:33:35_63fcf77fee6da8.91635760"}
		// 		'
		// 	)
		// );
		$secret = "bea5bb30c185d85c2b0e5cbcfb6ff219";

		$dataJson = json_decode($dataJson, true);
		if (array_key_exists('_route', $dataJson)) unset($dataJson['_route']);
		$dataJson = json_encode($dataJson);

		$payload = ".{$dataJson}";

		try {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
			curl_setopt($ch, CURLOPT_HEADER, true);

			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'YUMISIGN-SIGNATURE:t=,v1=' . hash_hmac('sha256', $payload, $secret),
				'Content-Type:application/json',
			]);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);

			$curlResponse = new CurlResponse();

			$curlResponse->response = curl_exec($ch);
			$curlResponse->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			$curlResponse->header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$curlResponse->header = substr($curlResponse->response, 0, $curlResponse->header_size);
			$curlResponse->body   = substr($curlResponse->response,    $curlResponse->header_size);

			return $curlResponse->body;

			curl_close($ch);
		} catch (\Throwable $th) {
			return $th->getMessage();
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function getLocalUsers()
	{
		$cm = \OC::$server->getContactsManager();

		// The API is not active -> nothing to do
		if (!$cm->isEnabled()) {
			$this->logger->error('Contact Manager not enabled');
			return new JSONResponse();
		}

		$result = $cm->search($this->request->getParam('searchQuery'), array('FN', 'EMAIL'));

		$contacts = array();
		foreach ($result as $raw_contact) {
			$contact = array();
			$contact['uid'] = $raw_contact['UID'];
			$contact['display_name'] = $raw_contact['FN'];

			if (array_key_exists('EMAIL', $raw_contact)) {
				$contact['email'] = $raw_contact['EMAIL'][0];
			}

			array_push($contacts, $contact);
		}

		return new JSONResponse($contacts);
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
