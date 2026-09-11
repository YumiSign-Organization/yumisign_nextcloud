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
use OCA\YumiSignNxtC\RCDevs\Controller\SignController as RCDevsSignController;
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Entity\UsersListEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;
use OCA\YumiSignNxtC\RCDevs\Utility\RequestResponse;
use OCA\YumiSignNxtC\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Constant\CstException;
use OCA\YumiSignNxtC\Constant\CstFile;
use OCA\YumiSignNxtC\Constant\CstLogMessages;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstTransactionType;
use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SignService;

// Nextcloud Core
use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class SignController extends Controller
{
	private	ConfigurationService	$configurationService;
	private	RCDevsSignController	$rcdevsSignController;
	private IConfig					$config;
	private IUserManager			$userManager;
	private string					$currentUserId;
	private UserEntity				$applicant;

	public function __construct(
		IAppConfig $appConfig,
		IConfig $config,
		IRequest $request,
		IUserManager $userManager,
		private IRootFolder $rootFolder,
		private ISearch $search,
		private IUserSession $userSession,
		private LogRCDevs $logRCDevs,
		private SignService $signService,
		private TransactionMapper $mapper,
		string $AppName,
		string $UserId
	) {
		parent::__construct($AppName, $request);

		$this->config			= $config;
		$this->currentUserId	= $UserId;
		$this->request			= $request;
		$this->userManager		= $userManager;

		$this->configurationService = new ConfigurationService($appConfig);

		// Common RCDevs Settings controller
		$this->rcdevsSignController = new RCDevsSignController(
			$this->request,
			$this->configurationService,
			$this->logRCDevs,
			$AppName,
		);

		// Define "Sender name" which will be displayed on mobile push/email : "You received a signature request from ..."
		$displayName = $this->userManager->get($this->currentUserId)->getDisplayName();
		if (empty($displayName)) {
			$displayName = $this->currentUserId;
		}

		$this->applicant = new UserEntity(
			$this->config,
			$this->rootFolder,
			$this->userManager,
			$this->currentUserId,
			null,
		);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	// private function commonSignLocalAsync(bool $advanced = false, bool $qualified = false, bool $standard = false)
	private function commonSignLocalAsync(
		SignatureType $signatureType
	) {
		$returned = [];

		try {
			$this->logRCDevs->debug(vsprintf('Asked signature type : (A:%b) (Q:%b) (S:%b)', [$signatureType->isAdvanced(), $signatureType->isQualified(), $signatureType->isStandard()]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Define recipients list
			$recipientsList = new UsersListEntity(
				$this->config,
				$this->rootFolder,
				$this->userManager,
				userIds: $this->request->getParam(CstRequest::RECIPIENT_ID),
				emailAddresses: $this->request->getParam(CstRequest::RECIPIENTEMAIL),
			);

			// Get data from request
			$resp = $this->signService->signLocalAsyncPrepare(
				$this->applicant,
				$recipientsList,
				$this->request->getParam(CstFile::PATH),
				$this->request->getParam(CstFile::FILE_ID),
				$signatureType,
			);

			$this->logRCDevs->debug(json_encode($resp), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			if ($resp->isFailed()) {
				throw new Exception($resp->message() ?? CstException::SIGN_PROCESS, 1);
			}

			$respData = $resp->data();
			if (!is_array($respData)) {
				throw new Exception(CstException::SIGN_PROCESS, 1);
			}

			// Squeeze Designer if signature type is QUALIFIED
			if (
				$signatureType->isQualified()
			) {
				$resp = $this->signService->signLocalAsyncSubmit(
					$this->applicant,
					$respData[CstRequest::WORKSPACEID],
					$respData[CstRequest::WORKFLOWID],
					$respData[CstRequest::ENVELOPEID]
				);
				if (Helpers::getIfExists(CstRequest::SESSION, $resp) === CstRequest::OK) {
					$resp[CstReturn::CODE] = 1;
				} else {
					$resp[CstReturn::CODE] = 0;
				}
			}

			$designerUrl = null;
			$rawResponse = $respData[CstRequest::RESPONSE] ?? null;
			if ($rawResponse instanceof RequestResponse) {
				$designerUrl = Helpers::getIfExists(CstRequest::DESIGNERURL, $rawResponse->getArray());
			}

			$returned = [
				CstReturn::CODE	=> $resp->code(),
				CstReturn::DATA => [
					CstRequest::DESIGNERURL => $designerUrl,
					CstRequest::ENVELOPEID => $respData[CstRequest::ENVELOPEID],
					CstRequest::WORKFLOWID => $respData[CstRequest::WORKFLOWID],
					CstRequest::WORKSPACEID => $respData[CstRequest::WORKSPACEID],
				],
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> $resp->message(),
				// Specific for Designer
				CstRequest::DESIGNERURL	=> $designerUrl,
				CstRequest::ENVELOPEID	=> $respData[CstRequest::ENVELOPEID],
				CstRequest::WORKFLOWID	=> $respData[CstRequest::WORKFLOWID],
				CstRequest::WORKSPACEID	=> $respData[CstRequest::WORKSPACEID],
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> CstException::SIGN_PROCESS,
			];
		}

		return $returned;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	/**
	 * @NoAdminRequired
	 */
	public function signLocalAsyncAdvanced()
	{
		$returned = [];

		try {
			$signCheck = $this->rcdevsSignController->signLocalAsyncAdvanced();
			if (Helpers::isIssueResponse($signCheck)) {
				throw new Exception(
					$signCheck[CstReturn::MESSAGE],
					$signCheck[CstReturn::ERROR],
				);
			}

			// Run process
			// $returned = $this->commonSignLocalAsync(advanced: true);
			$returned = $this->commonSignLocalAsync(new SignatureType(advanced: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

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
	 * @NoAdminRequired
	 */
	public function signLocalAsyncQualified()
	{
		$returned = [];

		try {
			$signCheck = $this->rcdevsSignController->signLocalAsyncQualified();
			if (Helpers::isIssueResponse($signCheck)) {
				throw new Exception(
					$signCheck[CstReturn::MESSAGE],
					$signCheck[CstReturn::ERROR],
				);
			}

			// Run process
			$returned = $this->commonSignLocalAsync(new SignatureType(qualified: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

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
	 * @NoAdminRequired
	 */
	public function signLocalAsyncStandard()
	{
		$returned = [];

		try {
			$signCheck = $this->rcdevsSignController->signLocalAsyncStandard();
			if (Helpers::isIssueResponse($signCheck)) {
				throw new Exception(
					$signCheck[CstReturn::MESSAGE],
					$signCheck[CstReturn::ERROR],
				);
			}

			// Run process
			$returned = $this->commonSignLocalAsync(new SignatureType(standard: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signLocalAsyncSubmit(
		$workspaceId = null,
		$workflowId = null,
		$envelopeId = null,
		$url = null
	) {
		try {
			$workspaceId = $workspaceId ?? $this->request->getParam(CstRequest::WORKSPACEID);
			$workflowId = $workflowId ?? $this->request->getParam(CstRequest::WORKFLOWID);
			$envelopeId = $envelopeId ?? $this->request->getParam(CstRequest::ENVELOPEID);
			$url = $url ?? $this->request->getParam('url');

			if (is_null($workspaceId) || is_null($workflowId) || is_null($envelopeId)) {
				throw new Exception('Missing callback parameters');
			}

			$this->signService->signLocalAsyncSubmit(
				$this->applicant,
				intval($workspaceId),
				intval($workflowId),
				strval($envelopeId)
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}

		return new RedirectResponse(!is_null($url) && $url !== '' ? $url : '/');
	}

	/**
	 * CAUTION:	the @Stuff turns off security checks; for this page no admin is
	 *			required and no CSRF check. If you don't know what CSRF is, read
	 *			it up in the docs or you might create a security hole. This is
	 *			basically the only required method to add this exemption, don't
	 *			add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index()
	{
		Util::addScript($this->configurationService->getAppId(), "{$this->configurationService->getAppId()}-index");
		return new TemplateResponse($this->configurationService->getAppId(), 'index');
	}
}
