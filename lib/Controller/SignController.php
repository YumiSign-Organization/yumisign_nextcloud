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

namespace OCA\YumiSignNxtC\Controller;

use Exception;
use OCA\RCDevs\Controller\SignController as RCDevsSignController;
use OCA\RCDevs\Entity\UserEntity;
use OCA\RCDevs\Entity\UsersListEntity;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\Constantes\CstException;
use OCA\YumiSignNxtC\Utility\Constantes\CstRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\Files\IRootFolder;
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
		IConfig								$config,
		IRequest							$request,
		IUserManager						$userManager,
		private			IRootFolder			$rootFolder,
		private			ISearch				$search,
		private			IUserSession		$userSession,
		private			LogRCDevs			$logRCDevs,
		private			SignService			$signService,
		private			SignSessionMapper	$mapper,
		string								$AppName,
		string								$UserId,
	) {
		parent::__construct($AppName, $request);

		$this->config			= $config;
		$this->currentUserId	= $UserId;
		$this->request			= $request;
		$this->userManager		= $userManager;

		$this->configurationService = new ConfigurationService($config);

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
	private function commonSignLocalAsync(SignatureType $signatureType)
	{
		$returned = [];

		try {
			$this->logRCDevs->debug(vsprintf('Asked signature type : (A:%b) (Q:%b) (S:%b)', [$signatureType->isAdvanced(), $signatureType->isQualified(), $signatureType->isStandard()]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Define recipients list
			$recipientsList = new UsersListEntity(
				$this->config,
				$this->rootFolder,
				$this->userManager,
				userIds: $this->request->getParam('recipientId'),
				emailAddresses: $this->request->getParam('recipientEmail'),
			);

			// Get data from request
			$resp = $this->signService->signLocalAsyncPrepare(
				$this->applicant,
				$recipientsList,
				$this->request->getParam('path'),
				$this->request->getParam('fileId'),
				$signatureType,
			);

			$this->logRCDevs->debug(json_encode($resp), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			if (Helpers::isIssueResponse($resp)) {
				throw new Exception($resp[CstRequest::MESSAGE], 1);
			}

			// Squeeze Designer if signature type is QUALIFIED
			if (
				$signatureType->isQualified()
			) {
				$resp = $this->signService->signLocalAsyncSubmit(
					$this->applicant,
					$resp[CstRequest::WORKSPACEID],
					$resp[CstRequest::WORKFLOWID],
					$resp[CstRequest::ENVELOPEID]
				);
				if (Helpers::getIfExists(CstRequest::SESSION, $resp) === CstRequest::OK) {
					$resp[CstRequest::CODE] = 1;
				} else {
					$resp[CstRequest::CODE] = 0;
				}
			}

			$returned = [
				CstRequest::CODE	=> $resp[CstRequest::CODE],
				CstRequest::DATA	=> $resp[CstRequest::DATA],
				CstRequest::ERROR	=> null,
				CstRequest::MESSAGE	=> $resp[CstRequest::MESSAGE],
				// Specific for Designer
				CstRequest::DESIGNERURL	=> $resp[CstRequest::DESIGNERURL],
				CstRequest::ENVELOPEID	=> $resp[CstRequest::ENVELOPEID],
				CstRequest::WORKFLOWID	=> $resp[CstRequest::WORKFLOWID],
				CstRequest::WORKSPACEID	=> $resp[CstRequest::WORKSPACEID],
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> CstException::SIGN_PROCESS,
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
					$signCheck[CstRequest::MESSAGE],
					$signCheck[CstRequest::ERROR],
				);
			}

			// Run process
			// $returned = $this->commonSignLocalAsync(advanced: true);
			$returned = $this->commonSignLocalAsync(new SignatureType(advanced: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
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
					$signCheck[CstRequest::MESSAGE],
					$signCheck[CstRequest::ERROR],
				);
			}

			// Run process
			$returned = $this->commonSignLocalAsync(new SignatureType(qualified: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
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
					$signCheck[CstRequest::MESSAGE],
					$signCheck[CstRequest::ERROR],
				);
			}

			// Run process
			$returned = $this->commonSignLocalAsync(new SignatureType(standard: true));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function signLocalAsyncSubmit($workspaceId = null, $workflowId = null, $envelopeId = null, $url = null)
	{
		try {
			if (!is_null($workspaceId) && !is_null($workflowId) && !is_null($envelopeId) && !is_null($url)) {
				$resp = $this->signService->signLocalAsyncSubmit(
					$this->applicant,
					$workspaceId,
					$workflowId,
					$envelopeId
				);

				return new RedirectResponse($url);
			}
		} catch (\Throwable $th) {
			return $th->getMessage();
		}
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
