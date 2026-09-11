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
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Entity\NotificationEntity;
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Entity\UsersListEntity;
use OCA\YumiSignNxtC\RCDevs\Exception\IgnoreIdException;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;
use OCA\YumiSignNxtC\RCDevs\Utility\Notification;
use OCA\YumiSignNxtC\RCDevs\Utility\RequestResponse;
use OCA\YumiSignNxtC\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Constant\CstApplication;
use OCA\YumiSignNxtC\Constant\CstCommon;
use OCA\YumiSignNxtC\Constant\CstDate;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstException;
use OCA\YumiSignNxtC\Constant\CstFile;
use OCA\YumiSignNxtC\Constant\CstLogMessages;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstStatus;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\Service\CurlService;

// Nextcloud Core
use DateTime;
use Exception;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Config\IUserConfig;
use OCA\YumiSignNxtC\RCDevs\Constant\CstSignedFolder;
use OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService;
use OCA\YumiSignNxtC\RCDevs\Service\SignedDocumentService;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;

class SignService
{
	const CNX_TIME_OUT = 3;
	private const UI_REFRESH_SCOPE_ALL = 'all';
	private const UI_REFRESH_SCOPE_FILES = 'files';
	private const UI_REFRESH_SCOPE_TRANSACTIONS = 'transactions';

	// Settings
	private int			$asyncTimeout;
	private int			$workspaceId;
	private string|null	$userId;
	private UserEntity	$applicant;

	public function __construct(
		private ConfigurationService $configurationService,
		private CurlService $curlService,
		private IAppConfig $appConfig,
		private IConfig $config,
		private IDateTimeFormatter $formatter,
		private IFactory $l10nFactory,
		private IL10N $l,
		private IL10N $l10n,
		private IRootFolder $rootFolder,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private LogRCDevs $logRCDevs,
		private Notification $notification,
		private TransactionMapper $mapper,
		private TokenService $tokenService,
		protected INotificationManager $notificationManager,
		protected IURLGenerator $url,
		string|null $UserId,
		private IUserConfig $userConfig,
		private SignedFolderService $signedFolders,
		private SignedDocumentService $signedDocuments,
	) {
		$this->asyncTimeout	= $this->appConfig->getValueInt($this->configurationService->getAppId(), 'async_timeout'); // in days
		$this->workspaceId	= (int) $this->configurationService->getWorkspaceId();
		$this->url			= $urlGenerator;
		$this->userId		= $UserId;

		$this->applicant = new UserEntity(
			$this->config,
			$this->rootFolder,
			$this->userManager,
			id: $this->userId,
			emailAddress: null,
		);

		if (empty($this->userId)) {
			$accessToken = null;
			$tokenExpired = true;
		} else {
			$accessToken	= $this->config->getUserValue($this->userId, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, default: null);
			$tokenExpired	= $this->tokenService->isTokenExpired($this->userId);
		}

		if (empty($accessToken) || $tokenExpired) { // Use API key
			$this->logRCDevs->info('Use API key', __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		} else { // Use Token instead
			$this->logRCDevs->info('Use Token instead', __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$_credentialKey = "{$this->configurationService->getTokenName()} {$accessToken}";
		}

		$this->curlService = new CurlService($this->appConfig, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function addPreferences(
		int $workflowId,
		array $preferences
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->addPreferences($preferences, $workflowId);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
		}

		return $curlResponse;
	}

	private function addRecipients(
		int $workflowId,
		array $recipients
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			$this->logRCDevs->info('Add recipients : ' . json_encode($recipients), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->addRecipients($recipients, $workflowId);

			$this->logRCDevs->debug('Add recipients returned : ' . json_encode($curlResponse), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			throw $th;
		}

		return $curlResponse;
	}

	private function addSteps(
		int $workflowId,
		CurlEntity $curlRecipients,
		SignatureType $signatureType
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			$this->logRCDevs->info('Add steps : ' . json_encode($curlRecipients), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$roles = json_decode($curlRecipients->getBody());
			$this->logRCDevs->info('Retrieve Roles : ' . json_encode($roles), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Business rules are different according to signature type
			switch (true) {
				case $signatureType->isAdvanced() || $signatureType->isQualified():
					// Initialize steps
					foreach ($roles as $key => $signRole) {
						$steps["steps"][] = ["sign" => true, "roles" => [$signRole->roles[0]->id]];
					}
					break;

				default: // Standard signature here
					// Initialize steps
					foreach ($roles as $key => $signRole) {
						$roleSteps[] = $signRole->roles[0]->id;
					}
					$steps["steps"][] = ["sign" => true, "roles" => $roleSteps];
					break;
			}

			$this->logRCDevs->debug('Ready to send steps : ' . json_encode($steps), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->addSteps($steps, $workflowId);

			$this->logRCDevs->debug('Add steps returned : ' . json_encode($curlResponse), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->debug('Critical on STEPS : ' . json_encode($curlResponse), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}

		return $curlResponse;
	}

	// Management of the YumiSign responses
	private function analyseYumiSignResponse(
		mixed $response,
		bool $subArray = false
	): array {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Analyse YumiSign response");
		$this->logRCDevs->debug((is_array($response) ? json_encode($response) : $response), __FUNCTION__);

		$return = [];

		// Input response may be json entity or array or entities
		if (!is_array($response)) {
			$response = json_decode($response, true);
		}

		// Check if this array response is simple or multidimensional
		switch (true) {
			case $subArray && !(array_key_exists(CstRequest::IDENTIFIER, $response) &&
				array_key_exists(CstRequest::RESULT, $response) &&
				array_key_exists(CstRequest::RESPONSE, $response) &&
				array_key_exists(CstReturn::ERROR, $response)
			):
				// Throw an exception because the YumiSign response structure is abnormal
				throw new Exception($this->l->t("YumiSign response is invalid; process: \"{$processPrefix}\""));
				break;

			case !$subArray && !(array_key_exists(CstRequest::IDENTIFIER, $response) &&
				array_key_exists(CstRequest::RESULT, $response) &&
				array_key_exists(CstRequest::RESPONSE, $response) &&
				array_key_exists(CstReturn::ERROR, $response)
			):
				// Multidimensional array
				foreach ($response as $key => $item) {
					$return = $this->analyseYumiSignResponse($item, true);
				}
				break;

			case (array_key_exists(CstRequest::IDENTIFIER, $response) &&
				array_key_exists(CstRequest::RESULT, $response) &&
				array_key_exists(CstRequest::RESPONSE, $response) &&
				array_key_exists(CstReturn::ERROR, $response)):
				/**
				 *	Simple array
				 *	YumiSign returns error or bad result
				 *	WARNING: this is not an exception but a Business Logic valid response
				 */
				if ($response[CstReturn::ERROR]	== true) {
					$return[CstReturn::CODE]		= 1;
					$return[CstReturn::DATA]		= [
						'api' => $response,
					];
					$return[CstReturn::ERROR]		= $response[CstReturn::ERROR][CstReturn::CODE] ?? 1;
					$return[CstReturn::MESSAGE]	= $response[CstReturn::ERROR][CstReturn::MESSAGE] ?? 'YumiSign API error';
					break;
				}
				// Just in case error intel not filled and result is wrong...
				if ($response[CstRequest::RESULT]	== false) {
					$return[CstReturn::CODE]		= 1;
					$return[CstReturn::DATA]		= [
						'api' => $response,
					];
					$return[CstReturn::ERROR]		= 1;
					$return[CstReturn::MESSAGE]	= "Error occurred during process";
					break;
				}
				// Here, the response is OK
				$return[CstReturn::CODE]		= 0;
				$return[CstReturn::DATA]		= [
					'api' => $response,
				];
				$return[CstReturn::ERROR]		= null;
				$return[CstReturn::MESSAGE]	= "OK";
				break;

			default:
				throw new Exception($this->l->t("Function not implemented: you should contact RCDevs at netadm@rcdevs.com; process: \"{$processPrefix}\""));
				break;
		}

		return $return;
	}

	private function commonSign(
		UserEntity $applicant,
		UsersListEntity $recipientsList,
		string $path,
		int $fileId,
		SignatureType $signatureType
	): CDEM {
		$cdem = new CDEM();

		try {
			$this->logRCDevs->info(vsprintf('Common Signature for file #%s: [%s]', [$fileId, json_encode($path)]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$this->signedFolders->validate((string) $applicant->getId());
			foreach ($recipientsList->list as $recipient) {
				$uid = (string) $recipient->getId();
				if ($uid !== '' && $this->userManager->userExists($uid)) {
					$this->signedFolders->validate($uid);
				}
			}

			$fileToSign = new FileService(
				configurationService: $this->configurationService,
				logRCDevs: $this->logRCDevs,
				user: $applicant,
				id: $fileId
			);

			// Create a workflow
			$this->logRCDevs->debug('Create a workflow', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$expiryDate = strtotime("+{$this->asyncTimeout} days");
			$curlWorkflow = $this->createWorkflow(
				$applicant->getDisplayName(),
				$fileToSign,
				$signatureType->get(),
				$expiryDate
			);
			$workflow = json_decode($curlWorkflow->getBody(), associative: false);

			// Add workflow preferences
			$this->logRCDevs->debug('Add workflow preferences', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$appUrl = $this->urlGenerator->getBaseUrl();
			$curlWorkflowPreferences = $this->addPreferences(
				$workflow->id,
				["preferences" => [
					["name" => "WorkflowNotificationCallbackUrlPreference", "value" => "{$appUrl}"]
				]]
			);

			// Retrieve the secret from YumiSign
			$this->logRCDevs->debug('Retrieve the secret from YumiSign', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$secret = $this->getYumiSignSecret($curlWorkflowPreferences);

			// Just for debug
			$this->logRCDevs->debug('Just for debug', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$webhook = $curlWorkflowPreferences->getBody();
			$this->logRCDevs->debug(sprintf('WEBHOOK : [%s]', $webhook),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Add recipients and retrieve roles
			$this->logRCDevs->debug('Add recipients and retrieve roles', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$recipientsData = $this->getRecipients($recipientsList);
			$curlRecipients = $this->addRecipients($workflow->id, $recipientsData['emails']);

			// Retrieve Workflow values and insert data in DB
			$this->logRCDevs->debug('Retrieve Workflow values and insert data in DB', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$curlDebriefWorkflow = $this->debriefWorkflow($workflow->id);
			$debriefWorkflow = json_decode($curlDebriefWorkflow->getBody());

			// Save data in DB for each recipient
			$this->logRCDevs->debug('Save data in DB for each recipient', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			foreach ($debriefWorkflow->recipients as $dbwRecipients) {
				// Insert row in DB
				$signSession = new SignSession();
				$signSession->setAdvanced(($signatureType->isAdvanced() ? 1 : 0));
				$signSession->setApplicantId($applicant->getId());
				$signSession->setChangeStatus($debriefWorkflow->createDate);
				$signSession->setCreated($debriefWorkflow->createDate);
				$signSession->setEnvelopeId($workflow->envelopeId);
				$signSession->setExpiryDate($debriefWorkflow->expiryDate);
				$signSession->setFileId($fileId);
				$signSession->setFilePath($fileToSign->getPath());
				$signSession->setGlobalStatus($debriefWorkflow->status);
				$signSession->setMsgDate($debriefWorkflow->createDate);
				$signSession->setMutex('');
				$signSession->setOverwrite($this->configurationService->doyouOverwrite());
				$signSession->setQualified(($signatureType->isQualified() ? 1 : 0));
				$signSession->setRecipient($dbwRecipients->email);
				$signSession->setRecipientId($recipientsData['idFromEmail'][$dbwRecipients->email]);
				$signSession->setSecret($secret);
				$signSession->setStatus($debriefWorkflow->status);
				$signSession->setWorkflowId($workflow->id);
				$signSession->setWorkflowName($workflow->name);
				$signSession->setWorkspaceId($this->workspaceId);

				$this->mapper->insert($signSession);
			}

			// Add steps
			$this->logRCDevs->debug('Add steps', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$curlSteps = $this->addSteps($workflow->id, $curlRecipients, $signatureType);
			if (Helpers::isIssueResponse($curlSteps)) {
				$this->logRCDevs->error(json_encode($curlSteps));
				throw new Exception(CstException::STEPS);
			}

			// Add fields
			$this->logRCDevs->debug('Add fields', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
			$curlSession = $this->getSession($workflow->id);
			$session = json_decode($curlSession->getBody(), associative: false);

			if (Helpers::isIssueResponse($curlSession)) {
				$this->logRCDevs->error(sprintf("Critical error when getting session. cUrl body is \"%s\"", $curlSession->getBody()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				throw new Exception(CstException::INTERNAL_SERVER_ERROR, 1);
			}

			// Prepare YMS Designer
				$this->logRCDevs->debug('Prepare YMS Designer', $this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__));
				if (!empty($session->session) && !empty($session->designerUrl)) {
					$response = new RequestResponse(json_decode(json_encode($session), true));
					$cdem->fromArray([
						CstReturn::CODE		=> 0,
						CstReturn::DATA		=> [
							CstReturn::CODE			=> 2,
							CstRequest::WORKSPACEID	=> $this->workspaceId,
							CstRequest::WORKFLOWID	=> $workflow->id,
							CstRequest::ENVELOPEID	=> $workflow->envelopeId,
							CstRequest::RESPONSE	=> $response,
							'api'					=> [
								'createWorkflow'			=> json_decode((string) $curlWorkflow->getBody(), true),
								'addPreferences'			=> json_decode((string) $curlWorkflowPreferences->getBody(), true),
								'addRecipients'				=> json_decode((string) $curlRecipients->getBody(), true),
								'debriefWorkflow'			=> json_decode((string) $curlDebriefWorkflow->getBody(), true),
								'addSteps'					=> json_decode((string) $curlSteps->getBody(), true),
								'getSession'				=> json_decode((string) $curlSession->getBody(), true),
								'createWorkflowHttpCode'	=> $curlWorkflow->getCode(),
								'addPreferencesHttpCode'	=> $curlWorkflowPreferences->getCode(),
								'addRecipientsHttpCode'		=> $curlRecipients->getCode(),
								'debriefWorkflowHttpCode'	=> $curlDebriefWorkflow->getCode(),
								'addStepsHttpCode'			=> $curlSteps->getCode(),
								'getSessionHttpCode'		=> $curlSession->getCode(),
							],
						],
						CstReturn::ERROR	=> null,
						CstReturn::MESSAGE	=> CstRequest::OPENDESIGNER,
					]);
			} else {
				$this->logRCDevs->error(sprintf("Critical error before running YumiSign Designer. Session value is \"%s\"", json_encode($session)), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				throw new Exception(CstException::INTERNAL_SERVER_ERROR, 1);
			}
			} catch (\Throwable $th) {
				$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

				$cdem->fromArray([
					CstReturn::CODE		=> 1,
					CstReturn::DATA		=> null,
					CstReturn::ERROR	=> $th->getCode(),
					CstReturn::MESSAGE	=> $th->getMessage(),
			]);
		}

		return $cdem;
	}

	private function createWorkflow(
		string $senderName,
		FileService $fileToSign,
		string $signType,
		int $expiryDate
	): CurlEntity {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

		$curlResponse = new CurlEntity();

		try {
			$workflowName = $fileToSign->getName() . ' (' . date('Y-m-d_H:i:s') . ')';

			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$dataPost = $this->curlService->getPostDataArray($workflowName, $senderName, $fileToSign, $signType, $expiryDate);
			$curlResponse = $this->curlService->createWorkflow($dataPost, $workflowName);

			// Check result validity
			if (null === json_decode($curlResponse->getBody()))		throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
			if (isset(json_decode($curlResponse->getBody())->error))	throw new Exception($this->l->t("Error occurred during process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->getBody())->error->code . "\""), 1);

			// Manage missing fields
			$workflow = json_decode($curlResponse->getBody());
			if (!isset($workflow->id))			throw new Exception($this->l->t("Cannot retrieve Workflow ID; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->getBody())->error->code . "\""), 1);
			if (!isset($workflow->envelopeId))	throw new Exception($this->l->t("Cannot retrieve Envelope ID; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->getBody())->error->code . "\""), 1);
			if (!isset($workflow->documents))	throw new Exception($this->l->t("No documents in this transaction; process: \"{$processPrefix}\" / code: \"" . json_decode($curlResponse->getBody())->error->code . "\""), 1);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
			throw $th;
		}

		return $curlResponse;
	}

	private function createWorkspace(
		array $workspaceData
	): CurlEntity {
		$curlResponse = new CurlEntity();

		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->createWorkspace($workspaceData);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
		}

		return $curlResponse;
	}

	private function getRecipients(
		UsersListEntity $recipientsList
	): array {
		$return = [];

		try {
			// Define roles for the recipients
			/** @var UserEntity $uniqueRecipient */
			foreach ($recipientsList->list as $key => $uniqueRecipient) {
				$role = [];
				$role[] = ["type" => "sign", "color" => "#" . $this->randomColorPart() . $this->randomColorPart() . $this->randomColorPart()];
				$uniqueRecipientArray = [
					"name" => (is_null($uniqueRecipient->getId())
						? $uniqueRecipient->getEmailAddress()
						: $uniqueRecipient->getDisplayName()
					),
					"email" => $uniqueRecipient->getEmailAddress(),
					"roles" => $role,
				];

				$return['emails']['recipients'][] = $uniqueRecipientArray;
				if (!empty($uniqueRecipient->getEmailAddress())) {
					$return['idFromEmail'][$uniqueRecipient->getEmailAddress()] = $uniqueRecipient->getId();
				}
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw new Exception(CstException::RECIPIENTS_EMAILS, 0);
		}

		return $return;
	}

	private function getSession(
		int $workflowId
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->getSession($workflowId);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
		}
		return $curlResponse;
	}

	private function getYumiSignSecret(
		CurlEntity $curlEntity
	): string {
		$return = '';

		try {
			$bodyWorkflowPreferences = json_decode($curlEntity->getBody());
			foreach ($bodyWorkflowPreferences as $key => $subArray) {
				if ($subArray->name && Helpers::areEqual($subArray->name, CstRequest::WORKFLOW_CALLBACK_SECRET)) {
					$return = $subArray->value;
					break;
				}
			}
			if (empty($return)) throw new Exception(CstException::NO_SECRET_PARAMETER, 0);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}

		return $return;
	}

	private function randomColorPart()
	{
		return str_pad(dechex(mt_rand(128, 230)), 2, '0', STR_PAD_LEFT);
	}

	private function normalizeUiRefreshScope(
		string $scope
	): string {
		$scope = strtolower(trim($scope));
		switch ($scope) {
			case self::UI_REFRESH_SCOPE_FILES:
			case self::UI_REFRESH_SCOPE_TRANSACTIONS:
			case self::UI_REFRESH_SCOPE_ALL:
				return $scope;
			default:
				return self::UI_REFRESH_SCOPE_ALL;
		}
	}

	private function markUiRefreshForApplicant(
		?string $applicantId,
		string $scope = self::UI_REFRESH_SCOPE_ALL
	): void {
		$applicantId = is_null($applicantId) ? '' : trim($applicantId);
		if ($applicantId === '') {
			return;
		}
		$scope = $this->normalizeUiRefreshScope($scope);

		$this->userConfig->setValueString(
			$applicantId,
			$this->configurationService->getAppId(),
			'ui_refresh_token',
			(string) microtime(true),
		);

		$this->userConfig->setValueString(
			$applicantId,
			$this->configurationService->getAppId(),
			'ui_refresh_scope',
			$scope,
		);
	}

	private function markUiRefreshForApplicants(
		array $applicantIds,
		string $scope = self::UI_REFRESH_SCOPE_ALL
	): void {
		$uniqueApplicantIds = [];
		foreach ($applicantIds as $applicantId) {
			$candidate = trim((string) $applicantId);
			if ($candidate !== '') {
				$uniqueApplicantIds[$candidate] = true;
			}
		}

		foreach (array_keys($uniqueApplicantIds) as $applicantId) {
			$this->markUiRefreshForApplicant($applicantId, $scope);
		}
	}

	private function retrieveEnvelopesIds(
		array $envelopesIds
	): CurlEntity {
		$curlResponse = new CurlEntity();

		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->getEnvelopes($envelopesIds);
		} catch (\Throwable $th) {
			throw $th;
		}

		return $curlResponse;
	}

	private function send(
		UsersListEntity $usersIdsList,
		NotificationEntity $notificationEntity
	): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp($this->configurationService->getAppId())
				->setDateTime(new \DateTime())
				->setObject($notificationEntity->idName, $notificationEntity->id)
				->setSubject($this->configurationService->getApplicationName(), [
					CstReturn::CODE	=> true,
					CstReturn::MESSAGE	=> $notificationEntity->message,
					CstRequest::STATUS	=> $notificationEntity->status,
				])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath($this->configurationService->getAppId(), 'app-dark.svg')))
			;

			/** @var UserEntity $unitUserId */
			foreach ($usersIdsList->list as $unitUserId) {
				if (!empty($unitUserId->getId())) {
					$notification->setUser($unitUserId->getId());
					$this->notificationManager->notify($notification);
				}
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	private function startWorkflow(
		int $workflowId
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			$curlResponse = $this->curlService->startWorkflow($workflowId);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
		}

		return $curlResponse;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function cancelWorkflow(
		int $workflowId
	): array {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Creating YumiSign workflow");

		$return = [];

		$curlResponse = new CurlEntity();

		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->cancelWorkflow($workflowId);

			// Check result validity
			$this->logRCDevs->debug(json_encode($curlResponse), __FUNCTION__);
			$return = $this->checkResultValidity($curlResponse, __FUNCTION__);
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
			throw $th;
		}

		return $return;
	}

	public function checkAsyncSignature()
	{
		$this->logRCDevs->info("checkAsyncSignature : starting process", __FUNCTION__);

		$curlResponse = new CurlEntity();
		try {
			// Get all transactions from YumiSign server
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->getWorkflows();

			// Prepare a comparative array which will contain Envelopes IDs fron YumiSign server.
			$envelopesYumiSign = [];

			// Check retrieved data
			if (!empty(Helpers::getArrayData(json_decode($curlResponse->getBody(), true), 'items', false))) {
				foreach (json_decode($curlResponse->getBody(), true)['items'] as $keyItem => $item) {
					$envelopesYumiSign[$item['envelopeId']] = $item['envelopeId'];
					// Browse each recipient
					if (
						!empty(Helpers::getArrayData($item, 'recipients', false)) &&
						!empty(Helpers::getArrayData($item, 'envelopeId', false)) &&
						!empty(Helpers::getArrayData($item, 'status', false))
					) {
						// Ignore transactions which have to be archived
						if (Helpers::areDifferent($item['status'], CstStatus::TO_BE_ARCHIVED)) {
							// If database does not contain the envelope ID, delete YumiSign transaction on server
							if ($this->mapper->countTransactionsByTransactionId($item['envelopeId'], CstEntity::ENVELOPE_ID) == 0) {
								$this->logRCDevs->info(sprintf("This transaction %s (%s) is missing in DB; should delete it. Status is %s", $item['envelopeId'], $item['name'], $item['status']), __FUNCTION__);
								$workflows = [];
								$workflows['workflows'][] = ['id' => $item['id'], 'workspaceId' => $this->workspaceId];

								// Check if status is canceled; if not, cancel YumiSign transaction before deletion
								if (Helpers::areDifferent($item['status'], CstStatus::CANCELED)) {
									$this->cancelWorkflow($item['id']);
								}
								$this->deleteWorkflows($workflows);
							} else {
								foreach ($item['recipients'] as $recipient) {
									// Get recipient status
									$recipientStatus = $item['status'];
									// Modify it if more intel
									if (!empty(Helpers::getArrayData($item, 'pendingActions', false))) {
										foreach ($item['pendingActions'] as $pendingAction) {
											if (Helpers::areEqual($pendingAction['recipientEmail'], $recipient['email'])) {
												$recipientStatus = $pendingAction['status'];
												break;
											}
										}
									}
									// Update status
									$this->updateStatus($item['envelopeId'], $recipient['email'], $item['status'], $recipientStatus);
								}
							}
						}
					}
				}
			}

			// Now let's clean DB thanks to filled comparative array
			try {
				$yumisignSessions = $this->mapper->findTransactions();
				foreach ($yumisignSessions as $yumisignSession) {
					if (!array_key_exists($yumisignSession->getEnvelopeId(), $envelopesYumiSign)) {
						$this->logRCDevs->info("Delete DB {$yumisignSession->getEnvelopeId()}", __FUNCTION__);
						$this->mapper->deleteTransactions($yumisignSession->getEnvelopeId(), CstEntity::ENVELOPE_ID);
					}
				}
			} catch (DoesNotExistException $e) {
				/**
				 *	YumiSign transaction table is empty
				 *	Not needed to bother admins...
				 */
			}
		} catch (\Throwable $th) {
			$curlResponse = new CurlEntity();
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}
		$this->logRCDevs->info("checkAsyncSignature : well done", __FUNCTION__);

		return $curlResponse;
	}

	public function checkAsyncSignatureTask(
		string $applicantId = null
	) {
		try {
			// Recover downloads independently of the transaction's expiry date.
			foreach ($this->signedDocuments->pendingSources() as $pending) {
				$retried = $this->saveTransactionFiles($pending['source'], '', __FUNCTION__);
				if (($retried[CstRequest::SAVED] ?? false) === true) {
					$this->updateAllStatus($pending['reference'], CstStatus::SIGNED);
				}
			}
			// Retry retained provider results even when their original transaction has expired.
			foreach ($this->signedDocuments->retryPending() as $reference) {
				$this->updateAllStatus($reference, CstStatus::SIGNED);
			}

			$this->logRCDevs->debug("########################################################################", __FUNCTION__);

			$envelopesIds = [];
			$transactionsToUpdate = [];
			$applicantsToRefresh = [];
			$rightNow = intval(time());

			// Update expired transactions status and global status
			$this->mapper->updateTransactionsStatusExpired();

			// Count actives transactions
			$countTransactions = $this->mapper->countTransactions($rightNow, $applicantId);
			if ($countTransactions[CstReturn::CODE] != 1) {
				throw new Exception($countTransactions[CstCommon::ERROR], 1);
			}
			// Just to have a clearer code ...
			$countTransactions = $countTransactions[CstReturn::DATA];

			$realTransactionProcessed = 0;

			$this->logRCDevs->debug("Transactions : {$countTransactions}", __FUNCTION__);

			$nbPages = intdiv($countTransactions, $this->mapper->maxItems) + ($countTransactions % $this->mapper->maxItems > 0 ? 1 : 0);
			$this->logRCDevs->debug("Pages : {$nbPages}", __FUNCTION__);

			$startCheckProcess = new DateTime();
			$this->logRCDevs->debug(sprintf("Start check process at %s", date_format($startCheckProcess, "Y/m/d H:i:s")), __FUNCTION__);

			for ($cptPagesTransactions = 0; $cptPagesTransactions < $nbPages; $cptPagesTransactions++) {

				// get transactions for this page
				$transactionsPage = $this->mapper->findAllTransactionsIds($rightNow, CstEntity::ENVELOPE_ID, $applicantId, $cptPagesTransactions, $this->mapper->maxItems);

				$envelopesIds = [];
				// Get all current page envelopeIds in only one curl call
				foreach ($transactionsPage as $unitRecord) {
					$envelopesIds[$unitRecord->getEnvelopeId()] = [
						CstEntity::ENVELOPE_ID => $unitRecord->getEnvelopeId(),
						CstEntity::APPLICANT_ID => $unitRecord->getApplicantId(),
					];
				}

				// Retrieve all the transactions corresponding to the current page envelopesIds
				$curlSession = $this->retrieveEnvelopesIds($envelopesIds);
				$requestBody = json_decode($curlSession->getBody(), true);

				// Check if return request is without error
				$apiKeyRateLimitReached = false;
				if (array_key_exists(CstReturn::ERROR, $requestBody) && !is_null($requestBody[CstReturn::ERROR])) {
					$apiKeyRateLimitReached = ($requestBody[CstReturn::ERROR][CstReturn::CODE] === 'API_KEY_RATE_LIMIT_REACHED');
					$this->logRCDevs->error(sprintf("Something happened during YumiSign server calls; server sent this message: %s", $requestBody[CstReturn::ERROR][CstReturn::MESSAGE]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				} else {
					// Prepare to update transations from retrieved data
					foreach ($requestBody as $actualTransaction) {

						switch (true) {
							// Ban transactions which are invalid
							case array_key_exists(CstReturn::ERROR, $actualTransaction) && !is_null($actualTransaction[CstReturn::ERROR]):
								switch ($actualTransaction[CstReturn::ERROR][CstReturn::CODE]) {
									case CstException::ENVELOPE_NOT_FOUND:
										$transactionsToUpdate[] = [
											CstEntity::ENVELOPE_ID	=> $actualTransaction[CstRequest::IDENTIFIER],
											CstEntity::APPLICANT_ID	=> $applicantId,
											CstEntity::STATUS	 => CstStatus::NOT_FOUND,
											CstEntity::GLOBAL_STATUS => CstStatus::NOT_FOUND,
										];
										$applicantsToRefresh[] = $envelopesIds[$actualTransaction[CstRequest::IDENTIFIER]][CstEntity::APPLICANT_ID] ?? $applicantId;
										break;

									default: // Set generic status
										$transactionsToUpdate[] = [
											CstEntity::ENVELOPE_ID	=> $actualTransaction[CstRequest::IDENTIFIER],
											CstEntity::APPLICANT_ID	=> $applicantId,
											CstEntity::STATUS	 => CstStatus::NOT_APPLICABLE,
											CstEntity::GLOBAL_STATUS => CstStatus::NOT_APPLICABLE,
										];
										$applicantsToRefresh[] = $envelopesIds[$actualTransaction[CstRequest::IDENTIFIER]][CstEntity::APPLICANT_ID] ?? $applicantId;
										break;
								}
								break;
							// Manage transactions which are valid
							case array_key_exists(CstReturn::ERROR, $actualTransaction) && is_null($actualTransaction[CstReturn::ERROR]):
								// Flag which indicated if we insert the array $currentTransaction after foreach loops
								$isCurrentTransactionInserted = false;
								$currentTransaction = [
									CstEntity::ENVELOPE_ID		=> $actualTransaction[CstRequest::IDENTIFIER],
									CstEntity::APPLICANT_ID		=> $envelopesIds[$actualTransaction[CstRequest::RESPONSE][CstRequest::ID]][CstEntity::APPLICANT_ID] ?? $applicantId,
									CstEntity::GLOBAL_STATUS	=> $actualTransaction[CstRequest::RESPONSE][CstCommon::STATUS],
								];

								// if glpbal status is signed, save signed documents
								if ($currentTransaction[CstEntity::GLOBAL_STATUS] === CstStatus::SIGNED) {
									$resp = $this->saveTransactionFiles(
										$actualTransaction['response'],
										$envelopesIds[$actualTransaction['response']['id']][CstEntity::APPLICANT_ID],
										__FUNCTION__,
									);

									// Change status if file is saved: prevent to lose records in DB
									if (($resp[CstRequest::SAVED] ?? false) === true) {
										// Check status for all recipients (will run if not signed)
										foreach ($actualTransaction[CstRequest::RESPONSE][CstRequest::STEPS] as $key => $step) {
											foreach ($step[CstRequest::ACTIONS] as $key => $action) {

												$currentTransaction[CstEntity::RECIPIENT]	= $action[CstRequest::RECIPIENTEMAIL];
												$currentTransaction[CstEntity::STATUS]		= $action[CstRequest::STATUS];
												$transactionsToUpdate[]						= $currentTransaction; // We insert directly the current transaction inside the global array
												$isCurrentTransactionInserted				= true;
												$applicantsToRefresh[] = $currentTransaction[CstEntity::APPLICANT_ID] ?? '';
											}
										}
									} else {
										// Keep this workflow eligible for another retrieval; do not mark saving as completed.
										break;
									}
								}

								if (!$isCurrentTransactionInserted) {
									$transactionsToUpdate[] = $currentTransaction;
									$applicantsToRefresh[] = $currentTransaction[CstEntity::APPLICANT_ID] ?? '';
								}
								break;

							default:	// Should not happen... humhum...
								$this->logRCDevs->warning(sprintf("This case is not implemented; please report a bug with the following data [%s]", json_encode($actualTransaction)), __FUNCTION__);
								break;
						}

						$realTransactionProcessed++;
					}
				}

				// Leave the loop before planned if YMS server is flooded
				if ($apiKeyRateLimitReached) {
					break;
				}
			}

			$endCheckProcess = new DateTime();

			$sinceStart = $startCheckProcess->diff($endCheckProcess);
			$this->logRCDevs->debug(sprintf("End check process at %s", date_format($endCheckProcess, "Y/m/d H:i:s")), __FUNCTION__);
			$this->logRCDevs->info(sprintf("Data processed: %d records treated in %d d %d H %d m %d s", $realTransactionProcessed, $sinceStart->d, $sinceStart->h, $sinceStart->i, $sinceStart->s), __FUNCTION__);

			// Update data in local DB
			$startUpdatedata = new DateTime();
			$this->logRCDevs->debug(sprintf("Start update process at %s", date_format($startUpdatedata, "Y/m/d H:i:s")), __FUNCTION__);

				$realTransactionUpdated = $this->mapper->updateTransactionsStatus($transactionsToUpdate);
				if ($realTransactionUpdated > 0) {
					$this->markUiRefreshForApplicants($applicantsToRefresh, self::UI_REFRESH_SCOPE_ALL);
				}
			$endUpdatedata = new DateTime();

			$sinceStart = $startUpdatedata->diff($endUpdatedata);

			$this->logRCDevs->debug(sprintf("End update process at %s", date_format($endUpdatedata, "Y/m/d H:i:s")), __FUNCTION__);
			$this->logRCDevs->info(sprintf("Database updated: %d records treated in %d d %d H %d m %d s", $realTransactionUpdated, $sinceStart->d, $sinceStart->h, $sinceStart->i, $sinceStart->s), __FUNCTION__);

			return;
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			return Helpers::warning($th->getMessage());
		}
	}

	// Check result validity
	public function checkResultValidity(
		CurlEntity $curlResponse,
		string $functionName
	): array {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Check YumiSign response");

		if (($curlResponse->getCode() !== 200) && ($curlResponse->getCode() !== 302)) {
			$message = sprintf("cURL returned unwanted code (%s). This process is skipped for function %s.", $curlResponse->getCode(), $functionName);
			$this->logRCDevs->debug($message, __FUNCTION__);
			$return[CstReturn::CODE] = 1;
			$return[CstReturn::DATA] = [
				'api' => [
					'httpCode' => $curlResponse->getCode(),
					'response' => $curlResponse->getBody(),
				],
			];
			$return[CstReturn::ERROR] = $curlResponse->getCode();
			$return[CstReturn::MESSAGE] = $message;
			return $return;
		}

		if (null === json_decode($curlResponse->getBody())) throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\""), 1);
		return $this->analyseYumiSignResponse($curlResponse->getBody());
	}

	public function debriefWorkflow(
		int $workflowId
	): CurlEntity {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Retrieve YumiSign workflow intel");
		$curlResponse = new CurlEntity();

		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->getDebrief($workflowId);
		} catch (\Throwable $th) {
			throw $th;
		}

		return $curlResponse;
	}

	public function deleteWorkflows(
		array $workflowsData
	): array {
		$return = [];
		$curlResponse = new CurlEntity();

		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
			$curlResponse = $this->curlService->deleteWorkflows($workflowsData);

			// Check result validity
			$return = $this->checkResultValidity($curlResponse, __FUNCTION__);
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->error(json_encode($curlResponse), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$curlResponse = new CurlEntity();
			throw $th;
		}

		return $return;
	}

	public function getUserLocalesTimestamp(
		string $userId,
		\DateTime $date
	) {
		$owner = $this->userManager->get($userId);
		$lang = 'en';
		$timeZone = $this->config->getUserValue($owner->getUID(), CstApplication::CORE, CstDate::TIMEZONE, null);
		$timeZone = isset($timeZone) ? new \DateTimeZone($timeZone) : new \DateTimeZone(CstDate::UTC);

		if ($lang) {
			$l10n = $this->l10nFactory->get($this->configurationService->getAppId(), $lang);
			if (!$l10n) {
				$l10n = $this->l10n;
			}
		} else {
			$l10n = $this->l10n;
		}
		$date->setTimezone($timeZone);
		return $date->format('Y-m-d H:i:s');
	}

	public function saveTransactionFiles(
		array $requestBody,
		string $userId,
		string $fromFunction
	): array {
		try {
			// A recipient's individual completion must never distribute an unfinished workflow.
			if (strtolower((string) ($requestBody['status'] ?? '')) !== CstStatus::SIGNED) {
				throw new Exception('The complete signature workflow must succeed before saving documents');
			}
			$envelopeId = (string) ($requestBody['id'] ?? '');
			if ($envelopeId === '') {
				throw new Exception('Missing signed workflow identifier');
			}
			if (!$this->signedDocuments->known($envelopeId)) {
				$this->signedDocuments->rememberSource($envelopeId, $requestBody);
				$session = $this->mapper->findTransaction($envelopeId, CstEntity::ENVELOPE_ID);
				// Use the recorded owner, never the currently logged-in user or a source path at a recipient.
				$owner = (string) $session->getApplicantId();
				$participants = [$owner => [CstSignedFolder::APPLICANT]];
				foreach ($this->mapper->findRecipientsIdsByTransaction($envelopeId, CstEntity::ENVELOPE_ID) as $recipient) {
					$uid = (string) $recipient->getRecipientId();
					if ($uid !== '' && $this->userManager->userExists($uid)) {
						$participants[$uid][] = CstSignedFolder::RECIPIENT;
					}
				}
				$documents = [];
				foreach (($requestBody['documents'] ?? []) as $document) {
					$url = (string) ($document['file']['file'] ?? '');
					$archive = parse_url($this->configurationService->getUrlArchive());
					$target = parse_url($url);
					if (!$target || !$archive || !isset($target['host'], $archive['host'])
						|| strtolower($target['host']) !== strtolower($archive['host'])
						|| ($target['scheme'] ?? '') !== ($archive['scheme'] ?? '')
						|| ($target['port'] ?? null) !== ($archive['port'] ?? null)
						|| !str_starts_with($target['path'] ?? '', rtrim($archive['path'] ?? '', '/') . '/')) {
						throw new Exception('Signed document URL does not match the configured archive');
					}
					$result = $this->curlService->getDocument($url);
					$content = $result->getResponse();
					if ($result->getCode() !== 200 || !is_string($content) || $content === '') {
						throw new Exception('Cannot retrieve the final signed document; saving must be retried');
					}
					$sourceName = basename((string) ($document['name'] ?? $session->getFilePath()));
					$extension = str_starts_with($content, '%PDF-') ? 'pdf' : (strtolower(pathinfo($sourceName, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'p7s');
					$complement = $this->configurationService->textualComplementSign() ?: $this->configurationService->getAppNameSigned();
					$name = pathinfo($sourceName, PATHINFO_FILENAME) . '_' . $complement . '_' . gmdate('Y-m-d_H.i.s') . '.' . $extension;
					$documents[] = ['name' => $name, 'content' => $content];
				}
				$this->signedDocuments->enqueue($envelopeId, $participants, $documents);
			}
			$this->signedDocuments->forgetSource($envelopeId);
			$saved = $this->signedDocuments->deliver($envelopeId);
			return [CstReturn::CODE => $saved ? 0 : 1, CstReturn::ERROR => $saved ? null : 'Signed document delivery pending', CstRequest::SAVED => $saved];
		} catch (\Throwable $e) {
			$this->logRCDevs->error('Signed document saving remains pending: ' . $e->getMessage(), __FUNCTION__);
			return [CstReturn::CODE => 1, CstReturn::ERROR => $e->getMessage(), CstRequest::SAVED => false];
		}
	}

	public function signLocalAsyncPrepare(
		UserEntity $applicant,
		UsersListEntity $recipientsList,
		string $path,
		int $fileId,
		SignatureType $signatureType
	): CDEM {
		$cdem = new CDEM();

		try {
			$this->logRCDevs->debug(vsprintf('Prepare asynchronous Local Signature for file #%s (%s) in [%s] from [%s] to these recipients : %s', [$fileId, json_encode($path), $signatureType->get(), json_encode($applicant), json_encode($recipientsList)]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

				$cdem = $this->commonSign(
					$applicant,
					$recipientsList,
					$path,
				$fileId,
				$signatureType,
			);
				if ($cdem->isFailed()) {
					throw new Exception($cdem->message() ?? CstException::SIGN_PROCESS, $cdem->code());
				}

				/** @var array $response */
				$response = $cdem->data();
				if (!is_array($response) || Helpers::isIssueResponse($response)) {
					throw new Exception($cdem->message() ?? CstException::SIGN_PROCESS, $cdem->code());
				}

			$message = sprintf(
				'Transaction created for %s',
				(count($recipientsList->list) > 1
					? 'several recipients'
					: $recipientsList->list[0]->getEmailAddress())
			);

				$cdem->fromArray([
					CstReturn::CODE		=> 0,
					CstReturn::DATA		=> $response,
					CstReturn::ERROR	=> null,
					CstReturn::MESSAGE	=> $message,
				]);

			$this->logRCDevs->debug(sprintf('signLocalAsyncPrepare returned: [%s]', json_encode($cdem)), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

				$cdem->fromArray([
					CstReturn::CODE => 1,
					CstReturn::DATA => null,
					CstReturn::ERROR => $th->getCode(),
					CstReturn::MESSAGE => $th->getMessage() ?: CstException::SIGN_PROCESS,
				]);
			}

		return $cdem;
	}

	public function signLocalAsyncSubmit(
		UserEntity $applicant,
		int $workspaceId,
		int $workflowId,
		string $envelopeId
	) {
		$resp = [];

		$this->signedFolders->validate((string) $applicant->getId());
		foreach ($this->mapper->findRecipientsIdsByTransaction($envelopeId, CstEntity::ENVELOPE_ID) as $recipient) {
			$uid = (string) $recipient->getRecipientId();
			if ($uid !== '' && $this->userManager->userExists($uid)) {
				$this->signedFolders->validate($uid);
			}
		}

		// Start the workflow
		$curlWorkflow = $this->startWorkflow($workflowId);
		$this->logRCDevs->debug('Start the workflow : ' . json_encode($curlWorkflow), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

		// Have to convert to array due to different returned value types
		$workflow = json_decode($curlWorkflow->getBody(), associative: true);

		// Check the error
		if (Helpers::getIfExists(CstReturn::ERROR, $workflow)) {
			throw new Exception(
				Helpers::getIfExists(CstReturn::MESSAGE, $workflow[CstReturn::ERROR]),
				0,
			);
		}

		if (!isset($workflow[0]->error) || !is_null($workflow[0]->error)) {
			$resp[CstReturn::CODE] = (isset($workflow[0]->error->code) ? $workflow[0]->error->code : "");
			$resp[CstReturn::MESSAGE] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "");

			// The following are filled with "fake" data (this is just to be compliant with conditions in Vue file)
			$resp[CstRequest::SESSION] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "OK");
			$resp[CstRequest::DESIGNERURL] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : false);
			$resp[CstRequest::WORKSPACEID] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workspaceId);
			$resp[CstRequest::WORKFLOWID] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workflowId);
		}

		// if ($workflow[0]->error === null) {
		if (is_null(Helpers::getIfExists(CstReturn::ERROR, $workflow[0], returnNull: true)) || is_null($workflow[0]->error)) {
			// Update status in DB
			$yumisignSessions = $this->mapper->findTransactions($envelopeId, CstEntity::ENVELOPE_ID);
			foreach ($yumisignSessions as $yumisignSession) {
				$yumisignSession->setStatus(CstStatus::STARTED);
				$yumisignSession->setChangeStatus(time());
				$this->mapper->update($yumisignSession);
			}
				$this->markUiRefreshForApplicant($applicant->getId(), self::UI_REFRESH_SCOPE_TRANSACTIONS);
			// Prepare OK notification
			$notificationCode		= true;
			$notificationMessage	= 'YumiSign transaction {status}';
			$notificationStatus		= CstStatus::STARTED;
		} else {
			/**
			 *	Exception returned from YumiSign server
			 *	Prepare Exception notification
			 */
			$notificationCode		= false;
			$notificationMessage	= 'null';
			$notificationStatus		= CstStatus::ERROR;
		}

		// Send notification to applicant to keep him informed the transaction is created
		$usersIdsList = new UsersListEntity(
			$this->config,
			$this->rootFolder,
			$this->userManager,
			userIds: $applicant->getId(),
			emailAddresses: null,
		);
		$notificationEntity = new NotificationEntity(
			code: $notificationCode,
			id: $envelopeId,
			idName: CstRequest::ENVELOPEID,
			message: $notificationMessage,
			status: $notificationStatus
		);

		// $this->notification->send(
		$this->send(
			$usersIdsList,
			$notificationEntity,
		);

		// If no exception, send notifications to all recipients to keep them informed the transaction is created
		if ($notificationCode) {
			$firstRecipientId = '';
			foreach ($this->mapper->findRecipientsIdsByTransaction($envelopeId, CstEntity::ENVELOPE_ID) as $signSession) {
				$candidateId = trim((string) $signSession->getRecipientId());
				if ($candidateId !== '') {
					$firstRecipientId = $candidateId;
					break;
				}
			}

			if ($firstRecipientId !== '') {
				$usersIdsList = new UsersListEntity(
					$this->config,
					$this->rootFolder,
					$this->userManager,
					userIds: [$firstRecipientId],
					emailAddresses: null,
				);
				$notificationEntity = new NotificationEntity(
					code: $notificationCode,
					id: $envelopeId,
					idName: CstRequest::ENVELOPEID,
					message: "You have been requested to sign a document",
					status: $notificationStatus
				);

				$this->send(
					$usersIdsList,
					$notificationEntity,
				);
			}
		}
		return $resp;
	}

	// Get the YumiSign workspace name status (OK/KO) for the Settings page
	public function statusWorkspaceName(
		IRequest $request
	) {
		$returnValue = false;

		return $returnValue;
	}

	public function updateAllStatus(
		string $envelopeId,
		string $status
	) {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Update all YumiSign workflow status");

		try {
			$rightNow = intval(time());

			$yumisignSessions = $this->mapper->findTransactions($envelopeId, CstEntity::ENVELOPE_ID);
			$applicantsToRefresh = [];
			foreach ($yumisignSessions as $yumisignSession) {
				// Check if update is needed according to sent message date
				if ($rightNow > $yumisignSession->getMsgDate()) {
					$yumisignSession->setGlobalStatus($status);
					$yumisignSession->setStatus($status);
					$yumisignSession->setChangeStatus($rightNow);
					$yumisignSession->setMsgDate($rightNow);

					// Update
					$this->mapper->update($yumisignSession);
					$applicantsToRefresh[] = $yumisignSession->getApplicantId();
				}
			}
				$refreshScope = (strtolower(trim($status)) === CstStatus::SIGNED)
					? self::UI_REFRESH_SCOPE_ALL
					: self::UI_REFRESH_SCOPE_TRANSACTIONS;
				$this->markUiRefreshForApplicants($applicantsToRefresh, $refreshScope);
		} catch (\Throwable $th) {
			throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\""), 1);
		}
	}

	public function updateStatus(
		string $envelopeId,
		string $recipient,
		string $globalStatus,
		string $status
	) {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Update YumiSign workflow status");

		try {
			$rightNow = intval(time());

			try {
				$yumisignSession = $this->mapper->findRecipientTransaction($envelopeId, CstEntity::ENVELOPE_ID, $recipient);
			} catch (DoesNotExistException $e) {
				$warningMsg = "YumiSign transaction {$envelopeId} not found for recipient {$recipient}";
				$this->logRCDevs->info($warningMsg, __FUNCTION__);
				return Helpers::warning($warningMsg);
			}

			// Check if update is needed according to sent message date
			if ($rightNow > $yumisignSession->getMsgDate()) {
				$yumisignSession->setGlobalStatus($globalStatus);
				$yumisignSession->setStatus($status);
				$yumisignSession->setChangeStatus(time());
				$yumisignSession->setMsgDate($rightNow);

				// Update
				$this->mapper->update($yumisignSession);

				$normalizedStatus = strtolower(trim($status));
				$normalizedGlobalStatus = strtolower(trim($globalStatus));
				$refreshScope = (
					$normalizedStatus === CstStatus::SIGNED
					|| $normalizedGlobalStatus === CstStatus::SIGNED
				)
					? self::UI_REFRESH_SCOPE_ALL
					: self::UI_REFRESH_SCOPE_TRANSACTIONS;

				$this->markUiRefreshForApplicant($yumisignSession->getApplicantId(), $refreshScope);
				}
		} catch (\Throwable $th) {
			$exceptionMsg = $this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\" / {$envelopeId} / {$recipient} / {$globalStatus} / {$status}");
			$this->logRCDevs->error($exceptionMsg, __FUNCTION__, true);
			throw new Exception($exceptionMsg, 1);
		}
	}

}
