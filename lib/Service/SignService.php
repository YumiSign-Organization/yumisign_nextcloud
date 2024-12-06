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

use DateTime;
use Exception;
use OC\URLGenerator;
use OCA\RCDevs\Entity\CurlEntity;
use OCA\RCDevs\Entity\NotificationEntity;
use OCA\RCDevs\Entity\UserEntity;
use OCA\RCDevs\Entity\UsersListEntity;
use OCA\RCDevs\Service\FileService;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\RCDevs\Utility\Notification;
use OCA\RCDevs\Utility\RequestResponse;
use OCA\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\CurlService;
use OCA\YumiSignNxtC\Utility\Constantes\CstCommon;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCA\YumiSignNxtC\Utility\Constantes\CstException;
use OCA\YumiSignNxtC\Utility\Constantes\CstFile;
use OCA\YumiSignNxtC\Utility\Constantes\CstRequest;
use OCA\YumiSignNxtC\Utility\Constantes\CstStatus;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;

class SignService
{
	const CNX_TIME_OUT = 3;

	// Settings
	private int			$asyncTimeout;
	private int			$workspaceId;
	// private string		$apiKey;
	// private string		$description;
	// private string		$serverUrl;
	private string		$userId;
	// private string		$workspaceName;
	private UserEntity	$applicant;

	public function __construct(
		private	ConfigurationService	$configurationService,
		private	CurlService				$curlService,
		private	IConfig					$config,
		private	IDateTimeFormatter		$formatter,
		private	IFactory				$l10nFactory,
		private	IFilesMetadataManager	$filesMetadataManager,
		private	IL10N					$l,
		private	IL10N					$l10n,
		private	IRootFolder				$rootFolder,
		private	IUserManager			$userManager,
		private	IUserSession			$userSession,
		private	LogRCDevs				$logRCDevs,
		private	SignSessionMapper		$mapper,
		private	URLGenerator			$urlGenerator,
		private Notification			$notification,
		string							$UserId,
	) {
		// $this->apiKey			= $this->configurationService->getApiKey();
		$this->asyncTimeout		= (int) $this->config->getAppValue($this->configurationService->getAppId(), 'async_timeout'); // in days
		// $this->description		= $this->config->getAppValue($this->configurationService->getAppId(), 'description');
		// $this->serverUrl		= $this->configurationService->getUrlApp();
		$this->workspaceId		= intval($this->config->getAppValue($this->configurationService->getAppId(), 'workspace_id'));
		// $this->workspaceName	= $this->config->getAppValue($this->configurationService->getAppId(), 'workspace_name');
		$this->userId = $UserId;

		$this->applicant = new UserEntity(
			$this->config,
			$this->rootFolder,
			$this->userManager,
			$this->userId,
			null,
		);

		$accessToken = $config->getUserValue($this->userId, $this->configurationService->getAppId(), CstEntity::ACCESS_TOKEN, default: null);

		if (is_null($accessToken)) { // Use API key
			$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		} else { // Use Token instead
			$_credentialKey = "{$this->configurationService->getTokenName()}:{$accessToken}";
		}
		$this->curlService = new CurlService($config, $this->logRCDevs);
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
		int				$workflowId,
		CurlEntity		$curlRecipients,
		SignatureType	$signatureType,
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			$this->logRCDevs->info('Add steps : ' . json_encode($curlRecipients), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$roles = json_decode($curlRecipients->getBody());

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
				array_key_exists(CstRequest::ERROR, $response)
			):
				// Throw an exception because the YumiSign response structure is abnormal
				throw new Exception($this->l->t("YumiSign response is invalid; process: \"{$processPrefix}\""), false);
				break;

			case !$subArray && !(array_key_exists(CstRequest::IDENTIFIER, $response) &&
				array_key_exists(CstRequest::RESULT, $response) &&
				array_key_exists(CstRequest::RESPONSE, $response) &&
				array_key_exists(CstRequest::ERROR, $response)
			):
				// Multidimensional array
				foreach ($response as $key => $item) {
					$return = $this->analyseYumiSignResponse($item, true);
				}
				break;

			case (array_key_exists(CstRequest::IDENTIFIER, $response) &&
				array_key_exists(CstRequest::RESULT, $response) &&
				array_key_exists(CstRequest::RESPONSE, $response) &&
				array_key_exists(CstRequest::ERROR, $response)):
				/**
				 *	Simple array
				 *	YumiSign returns error or bad result
				 *	WARNING: this is not an exception but a Business Logic valid response
				 */
				if ($response[CstRequest::ERROR]	== true) {
					$return[CstRequest::CODE]		= $response[CstRequest::ERROR][CstRequest::CODE];
					$return[CstRequest::MESSAGE]	= $response[CstRequest::ERROR][CstRequest::MESSAGE];
					break;
				}
				// Just in case error intel not filled and result is wrong...
				if ($response[CstRequest::RESULT]	== false) {
					$return[CstRequest::CODE]		= false;
					$return[CstRequest::MESSAGE]	= "Error occurred during process";
				}
				// Here, the response is OK
				$return[CstRequest::CODE]		= true;
				$return[CstRequest::MESSAGE]	= "OK";
				break;

			default:
				throw new Exception($this->l->t("Function not implemented: you should contact RCDevs at netadm@rcdevs.com; process: \"{$processPrefix}\""), false);
				break;
		}

		return $return;
	}

	private function commonSign(
		UserEntity		$applicant,
		UsersListEntity	$recipientsList,
		string			$path,
		int				$fileId,
		SignatureType	$signatureType,
	): array {
		$returned = [];

		try {
			$this->logRCDevs->info(vsprintf('Common Signature for file #%s: [%s]', [$fileId, json_encode($path)]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$fileToSign = new FileService($this->configurationService, $this->filesMetadataManager, $this->logRCDevs, $applicant, $fileId);

			// Create a workflow
			$expiryDate = strtotime("+{$this->asyncTimeout} days");
			$curlWorkflow = $this->createWorkflow(
				$applicant->getDisplayName(),
				$fileToSign,
				$signatureType->get(),
				$expiryDate
			);
			$workflow = json_decode($curlWorkflow->getBody(), associative: false);

			// Add workflow preferences
			$appUrl = $this->urlGenerator->getBaseUrl();
			$curlWorkflowPreferences = $this->addPreferences(
				$workflow->id,
				["preferences" => [
					["name" => "WorkflowNotificationCallbackUrlPreference", "value" => "{$appUrl}"]
				]]
			);

			// Retrieve the secret from YumiSign
			$secret = $this->getYumiSignSecret($curlWorkflowPreferences);

			// Just for debug
			$webhook = $curlWorkflowPreferences->getBody();
			$this->logRCDevs->debug(sprintf('WEBHOOK : [%s]', $webhook),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Add recipients and retrieve roles
			$recipientsData = $this->getRecipients($recipientsList);
			$curlRecipients = $this->addRecipients($workflow->id, $recipientsData['emails']);

			// Retrieve Workflow values and insert data in DB
			$curlDebriefWorkflow = $this->debriefWorkflow($workflow->id);
			$debriefWorkflow = json_decode($curlDebriefWorkflow->getBody());

			// Save data in DB for each recipient
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
			$curlSteps = $this->addSteps($workflow->id, $curlRecipients, $signatureType);
			if (Helpers::isIssueResponse($curlSteps)) {
				throw new Exception(CstException::STEPS);
			}

			// Add fields
			$curlSession = $this->getSession($workflow->id);
			$session = json_decode($curlSession->getBody(), associative: false);

			if (Helpers::isIssueResponse($curlSession)) {
				$this->logRCDevs->error(sprintf("Critical error when getting session. cUrl body is \"%s\"", $curlSession->getBody()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				throw new Exception(CstException::INTERNAL_SERVER_ERROR, 1);
			}

			// Prepare YMS Designer
			if (!empty($session->session) && !empty($session->designerUrl)) {
				$resp = array_merge(
					json_decode(json_encode($session), true),
					[
						CstRequest::CODE		=> 2,
						CstRequest::DATA		=> null,
						CstRequest::ERROR		=> null,
						CstRequest::MESSAGE		=> CstRequest::OPENDESIGNER,
						CstRequest::WORKSPACEID	=> $this->workspaceId,
						CstRequest::WORKFLOWID	=> $workflow->id,
						CstRequest::ENVELOPEID	=> $workflow->envelopeId,
					],
				);
			} else {
				$this->logRCDevs->error(sprintf("Critical error before running YumiSign Designer. Session value is \"%s\"", json_encode($session)), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				throw new Exception(CstException::INTERNAL_SERVER_ERROR, 1);
			}

			$returned = $resp;
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
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
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
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}

		return $return;
	}

	private function randomColorPart()
	{
		return str_pad(dechex(mt_rand(128, 230)), 2, '0', STR_PAD_LEFT);
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

	private function startWorkflow(
		int $workflowId
	): CurlEntity {
		$curlResponse = new CurlEntity();
		try {
			// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
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
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}
		$this->logRCDevs->info("checkAsyncSignature : well done", __FUNCTION__);

		return $curlResponse;
	}

	public function checkAsyncSignatureTask(
		string $applicantId = null
	) {
		try {
			$this->logRCDevs->debug("########################################################################", __FUNCTION__);

			$envelopesIds = [];
			$transactionsToUpdate = [];
			$rightNow = intval(time());

			// Update expired transactions status and global status
			$this->mapper->updateTransactionsStatusExpired();

			// Count actives transactions
			$countTransactions = $this->mapper->countTransactions($rightNow, $applicantId);
			if ($countTransactions[CstRequest::CODE] != 1) {
				throw new Exception($countTransactions[CstCommon::ERROR], 1);
			}
			// Just to have a clearer code ...
			$countTransactions = $countTransactions[CstRequest::DATA];

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
				if (array_key_exists(CstRequest::ERROR, $requestBody) && !is_null($requestBody[CstRequest::ERROR])) {
					$apiKeyRateLimitReached = ($requestBody[CstRequest::ERROR][CstRequest::CODE] === 'API_KEY_RATE_LIMIT_REACHED');
					$this->logRCDevs->error(sprintf("Something happened during YumiSign server calls; server sent this message: %s", $requestBody[CstRequest::ERROR][CstRequest::MESSAGE]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
				} else {
					// Prepare to update transations from retrieved data
					foreach ($requestBody as $actualTransaction) {

						switch (true) {
								// Ban transactions which are invalid
							case array_key_exists(CstRequest::ERROR, $actualTransaction) && !is_null($actualTransaction[CstRequest::ERROR]):
								switch ($actualTransaction[CstRequest::ERROR][CstRequest::CODE]) {
									case CstException::ENVELOPE_NOT_FOUND:
										$transactionsToUpdate[] = [
											CstEntity::ENVELOPE_ID	=> $actualTransaction[CstRequest::IDENTIFIER],
											CstEntity::APPLICANT_ID	=> $applicantId,
											CstEntity::STATUS	 => CstStatus::NOT_FOUND,
											CstEntity::GLOBAL_STATUS => CstStatus::NOT_FOUND,
										];
										break;

									default: // Set generic status
										$transactionsToUpdate[] = [
											CstEntity::ENVELOPE_ID	=> $actualTransaction[CstRequest::IDENTIFIER],
											CstEntity::APPLICANT_ID	=> $applicantId,
											CstEntity::STATUS	 => CstStatus::NOT_APPLICABLE,
											CstEntity::GLOBAL_STATUS => CstStatus::NOT_APPLICABLE,
										];
										break;
								}
								break;
								// Manage transactions which are valid
							case array_key_exists(CstRequest::ERROR, $actualTransaction) && is_null($actualTransaction[CstRequest::ERROR]):
								// Flag which indicated if we insert the array $currentTransaction after foreach loops
								$isCurrentTransactionInserted = false;
								$currentTransaction = [
									CstEntity::ENVELOPE_ID		=> $actualTransaction[CstRequest::IDENTIFIER],
									CstEntity::APPLICANT_ID		=> $applicantId,
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
									if (Helpers::isValidResponse($resp)) {
										// Check status for all recipients (will run if not signed)
										foreach ($actualTransaction[CstRequest::RESPONSE][CstRequest::STEPS] as $key => $step) {
											foreach ($step[CstRequest::ACTIONS] as $key => $action) {

												$currentTransaction[CstEntity::RECIPIENT]	= $action[CstRequest::RECIPIENTEMAIL];
												$currentTransaction[CstEntity::STATUS]		= $action[CstRequest::STATUS];
												$transactionsToUpdate[]						= $currentTransaction; // We insert directly the current transaction inside the global array
												$isCurrentTransactionInserted				= true;
											}
										}
									} else {
										$currentTransaction[CstEntity::GLOBAL_STATUS] = CstRequest::ERROR;
									}
								}

								if (!$isCurrentTransactionInserted) {
									$transactionsToUpdate[] = $currentTransaction;
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
			$return[CstRequest::CODE] = false;
			$return[CstRequest::MESSAGE] = $message;
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
		$timeZone = $this->config->getUserValue($owner->getUID(), 'core', 'timezone', null);
		$timeZone = isset($timeZone) ? new \DateTimeZone($timeZone) : new \DateTimeZone('UTC');

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
		$created = null;
		$envelopeId = '';

		try {
			$threadId = bin2hex(random_bytes(8));
			$this->logRCDevs->info("Saving transaction files for user [{$userId}] / Sent from fct [{$fromFunction}]", __FUNCTION__);
			$warningMsg = '';

			// Get current document full path (filesystem)
			$user = $this->userManager->get($userId);

			if (array_key_exists('documents', $requestBody)) {
				foreach ($requestBody['documents'] as $key => $document) {
					$this->logRCDevs->debug(sprintf('Foreach Doc [%s]', json_encode($document)), __FUNCTION__);

					if (array_key_exists('file', $document) && array_key_exists('file', $document['file'])) {
						$envelopeId = Helpers::getArrayData($requestBody, 'id', true, 'YumiSign transaction ID field is missing');
						$this->logRCDevs->debug(sprintf('EnvelopeId [%s]', $envelopeId), __FUNCTION__);

						try {
							// If the envelope Id exists,  update it with this Thread Id as Mutex
							$this->mapper->updateTransactionsMutex($threadId, $envelopeId, CstEntity::ENVELOPE_ID);
							// Get the envelope Id and check if the Mutex === thread Id; if OK, this thread will save the file
							$yumisignSession = $this->mapper->findTransaction($envelopeId, CstEntity::ENVELOPE_ID);
						} catch (DoesNotExistException $e) {
							$warningMsg = "YumiSign transaction {$envelopeId} not found";
							$this->logRCDevs->info($warningMsg, __FUNCTION__);
							return [
								CstRequest::CODE		=> 0,
								CstRequest::ERROR		=> Helpers::warning($warningMsg),
								CstRequest::FILENODE	=> null,
								CstRequest::SAVED		=> false,
							];
						}

						// If Mutex is not empty and not equal to Thread Id, it means another process handles it
						$this->logRCDevs->debug(sprintf('Thread is %s', $threadId), __FUNCTION__);
						$this->logRCDevs->debug(sprintf('Mutex is %s', $yumisignSession->getMutex()), __FUNCTION__);
						if (
							$yumisignSession->getMutex() !== ''
							&& !is_null($yumisignSession->getMutex())
							&& $yumisignSession->getMutex() !== $threadId
						) {
							$this->logRCDevs->debug(sprintf('Ignore this EnvId',), __FUNCTION__);
							return [
								CstRequest::CODE		=> 1,
								CstRequest::ERROR		=> 0,
								CstRequest::FILENODE	=> null,
								CstRequest::SAVED		=> false,
							];
						}

						$this->logRCDevs->debug(sprintf('Save this EnvId',), __FUNCTION__);

						/**
						 *	Here, this Thread will handle the transaction file
						 *	Only one session in the result
						 */
						if ($yumisignSession->getFileId())	$fileId	 = $yumisignSession->getFileId();

						$url = str_replace('\\', '', $document['file']['file']);

						$ymsUrlArchive = $this->configurationService->getUrlArchive();

						$this->logRCDevs->debug($document['file']['file'], __FUNCTION__);
						$this->logRCDevs->debug($url, __FUNCTION__);
						$this->logRCDevs->debug($ymsUrlArchive, __FUNCTION__);

						$this->logRCDevs->debug(substr(parse_url($url, PHP_URL_PATH), 0, strlen(parse_url($ymsUrlArchive, PHP_URL_PATH))), __FUNCTION__);
						$this->logRCDevs->debug(parse_url($ymsUrlArchive, PHP_URL_PATH), __FUNCTION__);

						if (
							Helpers::areDifferent(
								substr(parse_url($url, PHP_URL_PATH), 0, strlen(parse_url($ymsUrlArchive, PHP_URL_PATH))),
								parse_url($ymsUrlArchive, PHP_URL_PATH)
							)
						) {
							$this->logRCDevs->warning(
								'EnvelopeID mismatch : ' .
									parse_url(substr($url, 0, strlen($ymsUrlArchive))) .
									' versus ' .
									parse_url($ymsUrlArchive),
								__FUNCTION__
							);
							$this->logRCDevs->warning($warningMsg, __FUNCTION__);
							throw new Exception("This url does not match to the Archives", 1);
						}

						// $curlService = new CurlService($this->config, $this->logRCDevs, $this->configurationService->getApiKey());
						$temporaryFile = $this->curlService->getDocument($url)->getResponse();

						/** @var File $createdFile */
						$fileToSign = new FileService(
							$this->configurationService,
							$this->filesMetadataManager,
							$this->logRCDevs,
							$this->applicant,
							$fileId,
							toSeal: false
						);
						$createdFile = $fileToSign->create($temporaryFile, $this->configurationService->doyouOverwrite());

						$data = [
							CstEntity::FILE_ID		=> $createdFile->getId(),
							CstEntity::NAME			=> ltrim($createdFile->getInternalPath(), 'files/'),
							CstEntity::OVERWRITE	=> $this->configurationService->doyouOverwrite(),
							CstFile::INTERNAL_PATH	=> $createdFile->getInternalPath(),
							CstFile::PATH			=> $createdFile->getPath(),
							CstFile::SIZE			=> $createdFile->getSize(),
						];

						$this->logRCDevs->debug(vsprintf('Returned data : [%s]', [json_encode($data)]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

						return [
							CstRequest::CODE		=> 1,
							CstRequest::ERROR		=> 0,
							CstRequest::FILENODE	=> $created,
							CstRequest::SAVED		=> true,
						];
					}
				}
			}
		} catch (\Throwable $th) {
			// Restore empty Mutex
			$this->mapper->updateTransactionsMutexReset($threadId, $envelopeId, CstEntity::ENVELOPE_ID);

			$envelopeId = $envelopeId === '' ? 'undefined' : $envelopeId;
			$this->logRCDevs->error("Issue on envelopeId {$envelopeId}: {$th->getMessage()}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			return [
				CstRequest::CODE		=> 0,
				CstRequest::ERROR		=> $th->getCode(),
				CstRequest::FILENODE	=> $created,
				CstRequest::SAVED		=> false,
			];
		}
	}

	public function signLocalAsyncPrepare(
		UserEntity		$applicant,
		UsersListEntity	$recipientsList,
		string			$path,
		int				$fileId,
		SignatureType	$signatureType,
	): array {
		$returned = [];

		try {
			$this->logRCDevs->debug(vsprintf('Prepare asynchronous Local Signature for file #%s (%s) in [%s] from [%s] to these recipients : %s', [$fileId, json_encode($path), $signatureType->get(), json_encode($applicant), json_encode($recipientsList)]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$resp = new RequestResponse($this->commonSign(
				$applicant,
				$recipientsList,
				$path,
				$fileId,
				$signatureType,
			));
			if ($resp->isFailed()) {
				throw new Exception($resp[CstRequest::MESSAGE]);
			}

			$returned = [
				CstRequest::CODE			=> $resp->getCode(),
				CstRequest::DATA			=> null,
				CstRequest::ERROR			=> null,
				CstRequest::DESIGNERURL		=> Helpers::getArrayData($resp->getArray(), CstRequest::DESIGNERURL, false),
				CstRequest::ENVELOPEID		=> Helpers::getArrayData($resp->getArray(), CstRequest::ENVELOPEID, false),
				CstRequest::MESSAGE			=> sprintf(
					'Transaction created for %s',
					(count($recipientsList->list) > 1
						? 'several recipients'
						: $recipientsList->list[0]->getEmailAddress())
				),
				CstRequest::SESSION			=> Helpers::getArrayData($resp->getArray(), CstRequest::SESSION,	false),
				CstRequest::WORKFLOWID		=> Helpers::getArrayData($resp->getArray(), CstRequest::WORKFLOWID, false),
				CstRequest::WORKSPACEID		=> Helpers::getArrayData($resp->getArray(), CstRequest::WORKSPACEID, false),
			];

			$this->logRCDevs->debug(sprintf('signLocalAsyncPrepare returned: [%s]', json_encode($returned)), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE => 0,
				CstRequest::DATA => null,
				CstRequest::ERROR => $th->getCode(),
				CstRequest::MESSAGE => $th->getMessage(),
			];
		}

		return $returned;
	}

	public function signLocalAsyncSubmit(
		UserEntity	$applicant,
		int			$workspaceId,
		int			$workflowId,
		string		$envelopeId
	) {
		$resp = [];

		// Start the workflow
		$curlWorkflow = $this->startWorkflow($workflowId);
		$this->logRCDevs->debug('Start the workflow : ' . json_encode($curlWorkflow), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

		// Have to convert to array due to different returned value types
		$workflow = json_decode($curlWorkflow->getBody(), associative: true);

		// Check the error
		if (Helpers::getIfExists(CstRequest::ERROR, $workflow)) {
			throw new Exception(
				Helpers::getIfExists(CstRequest::MESSAGE, $workflow[CstRequest::ERROR]),
				0,
			);
		}

		if (!isset($workflow[0]->error) || !is_null($workflow[0]->error)) {
			$resp[CstRequest::CODE] = (isset($workflow[0]->error->code) ? $workflow[0]->error->code : "");
			$resp[CstRequest::MESSAGE] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "");

			// The following are filled with "fake" data (this is just to be compliant with conditions in Vue file)
			$resp[CstRequest::SESSION] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : "OK");
			$resp[CstRequest::DESIGNERURL] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : false);
			$resp[CstRequest::WORKSPACEID] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workspaceId);
			$resp[CstRequest::WORKFLOWID] = (isset($workflow[0]->error->message) ? $workflow[0]->error->message : $workflowId);
		}

		if ($workflow[0]->error === null) {
			// Update status in DB
			$yumisignSessions = $this->mapper->findTransactions($envelopeId, CstEntity::ENVELOPE_ID);
			foreach ($yumisignSessions as $yumisignSession) {
				$yumisignSession->setStatus(CstStatus::STARTED);
				$yumisignSession->setChangeStatus(time());
				$this->mapper->update($yumisignSession);
			}
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

		$this->notification->send(
			$usersIdsList,
			$notificationEntity,
		);

		// If no exception, send notifications to all recipients to keep them informed the transaction is created
		if ($notificationCode) {
			//* @var SignSession $signSession */
			foreach ($this->mapper->findRecipientsIdsByTransaction($envelopeId, CstEntity::ENVELOPE_ID) as $key => $signSession) {
				$userIds[] = $signSession->getRecipientId();
			}
			$usersIdsList = new UsersListEntity(
				$this->config,
				$this->rootFolder,
				$this->userManager,
				userIds: $userIds,
				emailAddresses: null,
			);
			$notificationEntity = new NotificationEntity(
				code: $notificationCode,
				id: $envelopeId,
				idName: CstRequest::ENVELOPEID,
				message: "You have been requested to sign a document",
				status: $notificationStatus
			);

			$this->notification->send(
				$usersIdsList,
				$notificationEntity,
			);
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
		string $status,
	) {
		$processPrefix = sprintf("%s/%s/%s", basename(__FILE__, '.php'), __FUNCTION__, "Update all YumiSign workflow status");

		try {
			$rightNow = intval(time());

			$yumisignSessions = $this->mapper->findTransactions($envelopeId, CstEntity::ENVELOPE_ID);
			foreach ($yumisignSessions as $yumisignSession) {
				// Check if update is needed according to sent message date
				if ($rightNow > $yumisignSession->getMsgDate()) {
					$yumisignSession->setGlobalStatus($status);
					$yumisignSession->setStatus($status);
					$yumisignSession->setChangeStatus($rightNow);
					$yumisignSession->setMsgDate($rightNow);

					// Update
					$this->mapper->update($yumisignSession);
				}
			}
		} catch (\Throwable $th) {
			throw new Exception($this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\""), 1);
		}
	}

	public function updateStatus(
		string $envelopeId,
		string $recipient,
		string $globalStatus,
		string $status,
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
			}
		} catch (\Throwable $th) {
			$exceptionMsg = $this->l->t("Critical error during process : \"{$processPrefix}\" / message: \"" . $th->getMessage() . "\" / {$envelopeId} / {$recipient} / {$globalStatus} / {$status}");
			$this->logRCDevs->error($exceptionMsg, __FUNCTION__, true);
			throw new Exception($exceptionMsg, 1);
		}
	}

	public function webhook(
		$headerYumiSign,
		$requestBody
	) {
		$processStatus = [];
		$applicantId = "";
		$envelopeId = '';

		try {
			// Get header data
			if (empty($headerYumiSign)) {
				$warningMsg = $this->l->t('YumiSign signature is missing');
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			$headerdata = explode(',', str_replace('=', ',', $headerYumiSign));

			if (array_key_exists('_route', $requestBody)) unset($requestBody['_route']);

			$envelopeId = Helpers::getArrayData($requestBody, 'id',	 true, 'YumiSign transaction ID field is missing');
			$status	= Helpers::getArrayData($requestBody, 'status',	true, 'YumiSign transaction status field is missing');

			$secret = "";

			try {
				$yumisignSession = $this->mapper->findTransaction($envelopeId, CstEntity::ENVELOPE_ID);
			} catch (DoesNotExistException $e) {
				$warningMsg = "YumiSign transaction {$envelopeId} not found";
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			if ($yumisignSession->getApplicantId()) $applicantId = $yumisignSession->getApplicantId();
			if ($yumisignSession->getSecret()) $secret = $yumisignSession->getSecret();
			else {
				$warningMsg = "YumiSign transaction secret not found";
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			$payload = $headerdata[1] . "." . json_encode($requestBody);

			// Check if Keys match
			$match = (strcmp(hash_hmac('sha256', $payload, $secret), $headerdata[3]) === 0);

			// KO if not match
			if (!$match) {
				$warningMsg = "YumiSign transaction bad key";
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			// Valid transaction, do what you need to do...
			switch ($status) {
				case CstStatus::NOT_STARTED:
				case CstStatus::APPROVED:
				case CstStatus::CANCELED:
				case CstStatus::DECLINED:
				case CstStatus::EXPIRED:
				case CstStatus::TO_BE_ARCHIVED:
				case CstStatus::STARTED:
					// Update each recipient status
					$steps = Helpers::getArrayData($requestBody, 'steps',	true, 'YumiSign transaction steps fields are missing');
					foreach ($steps as $step) {
						foreach ($step['actions'] as $action) {
							$resp = $this->updateStatus($envelopeId, $action['recipientEmail'], $status, $action['status'], $headerdata[1]);
						}
					}
					break;
				case CstStatus::SIGNED:
					/**
					 *	Only transaction status is available; the recipients status are not written in this Signed transaction
					 *	So all status are updated as Signed (recipients + global)
					 *	Save the files of this transaction (all recipients have signed)
					 */
					$resp = $this->saveTransactionFiles($requestBody, $yumisignSession->getApplicantId(), __FUNCTION__);
					// Change status if file is saved: prevent to lose records in DB
					if ($resp['code'] === 1) {
						$resp = $this->updateAllStatus($envelopeId, $status);
					}
					break;
				default:
					#code...
					break;
			}

			$processStatus[CstRequest::CODE] = true;
			$processStatus[CstRequest::MESSAGE] = 'YumiSign transaction {status}';
			$processStatus[CstRequest::STATUS] = $status;
		} catch (\Throwable $th) {
			$warningMsg = "{$th->getMessage()} / Envelope ID : {$envelopeId}";
			$this->logRCDevs->error($warningMsg, __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$processStatus = Helpers::warning($warningMsg);
		}

		try {
			/**
			 *	Send notification to applicant whatever the YumiSign transaction status EXCEPT for status == NULL (This is the WebHook initialization)
			 *	However, if there was an exception, keep display it
			 */
			$manager = \OC::$server->get(IManager::class);
			$notification = $manager->createNotification();

			if (!empty($applicantId)) {
				$notification->setApp($this->configurationService->getAppId())
					->setUser($applicantId)
					->setDateTime(new \DateTime())
				;

				$manager->notify($notification);
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			return Helpers::warning($th->getMessage());
		}
	}
}
