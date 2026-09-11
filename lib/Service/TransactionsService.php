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
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;
use OCA\YumiSignNxtC\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Constant\CstCommon;
use OCA\YumiSignNxtC\Constant\CstDatabase;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstException;
use OCA\YumiSignNxtC\Constant\CstLogMessages;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstStatus;
use OCA\YumiSignNxtC\Constant\CstTransactionType;
use OCA\YumiSignNxtC\Db\TransactionMapper;

// Nextcloud Core
use DateTime;
use Exception;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\Notification\IManager;

class TransactionsService
{
	const CNX_TIME_OUT = 3;

	public function __construct(
		private $UserId,
		private IL10N $l10n,
		private ConfigurationService $configurationService,
		private SignService $signService,
		private TransactionMapper $mapper,
		protected LogRCDevs $logRCDevs
	) {
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function commonGetTransactions(
		string $userId,
		int $page,
		int $nbItems,
		string $status
	) {
		try {
			$status = ucfirst($status);
			$functionToRun = "findTransactionsByApplicantByStatus{$status}";

			$databaseResponse = $this->mapper->$functionToRun($userId, $page, $nbItems);

			$transactions = [];
			foreach ($databaseResponse as $databaseRecord) {
				$signatureType = new SignatureType(
					advanced: Helpers::isAdvanced($databaseRecord->getAdvanced()),
					qualified: Helpers::isQualified($databaseRecord->getQualified())
				);

				$transactions[] = [
					CstEntity::CHANGE_STATUS	=> $databaseRecord->getChangeStatus(),
					CstEntity::CREATED			=> $databaseRecord->getCreated(),
					CstEntity::ENVELOPE_ID		=> $databaseRecord->getEnvelopeId(),
					CstEntity::EXPIRY_DATE		=> $databaseRecord->getExpiryDate(),
					CstEntity::FILE_PATH		=> basename($databaseRecord->getFilePath()),
					CstEntity::GLOBAL_STATUS	=> $databaseRecord->getGlobalStatus(),
					CstEntity::ID				=> $databaseRecord->getId(),
					CstEntity::RECIPIENT		=> $databaseRecord->getRecipient(),
					CstEntity::SIGNATURE_TYPE	=> $signatureType->getNormalized(),
					CstEntity::STATUS			=> $databaseRecord->getStatus(),
				];
			}

			$functionToRun = "countTransactionsByApplicantByStatus{$status}";
			$returned = [
				CstDatabase::COUNT => $this->mapper->$functionToRun($userId),
				'transactions' => $transactions,
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstDatabase::COUNT => 0,
				'transactions' => [],
			];
		}

		return $returned;
	}

	private function notifyNextPendingRecipient(
		string $envelopeId
	): void {
		try {
			$transactions = $this->mapper->findTransactions($envelopeId, CstEntity::ENVELOPE_ID);
			usort($transactions, static fn($left, $right): int => $left->getId() <=> $right->getId());

			$nextRecipientId = '';
			foreach ($transactions as $transaction) {
				$status = strtolower(trim((string) $transaction->getStatus()));
				if ($status !== CstStatus::PENDING) {
					continue;
				}
				$candidate = trim((string) $transaction->getRecipientId());
				if ($candidate !== '') {
					$nextRecipientId = $candidate;
					break;
				}
			}

			if ($nextRecipientId === '') {
				return;
			}

			$manager = \OC::$server->get(IManager::class);
			$notification = $manager->createNotification();
			$notification
				->setApp($this->configurationService->getAppId())
				->setUser($nextRecipientId)
				->setDateTime(new DateTime())
				->setObject(CstRequest::ENVELOPEID, $envelopeId)
				->setSubject($this->configurationService->getApplicationName(), [
					CstReturn::CODE => true,
					CstReturn::MESSAGE => 'You have been requested to sign a document',
					CstRequest::STATUS => CstStatus::PENDING,
				]);
			$manager->notify($notification);
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__);
		}
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function cancelTransaction(
		string $envelopeId,
		string $userId,
		bool $forceDeletion = false,
		string $recipient = ''
	) {
		$returned = [];

		try {
			$signSession = $this->mapper->findTransaction($envelopeId, CstEntity::ENVELOPE_ID);

			if ($signSession->getApplicantId() !== $userId) {
				throw new Exception($this->l10n->t('YumiSign transaction not found'), 403);
			}

			// Check on YumiSign only if not "Forcing process"
			// First, check the real state of this YumiSign transaction
			$check = json_decode($this->signService->debriefWorkflow($signSession->getWorkflowId())->getBody(), true);
			$this->logRCDevs->debug('Debrief Workflow returned : ' . json_encode($check), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			switch (true) {
				case !Helpers::getIfExists(CstEntity::STATUS, $check):
					$this->signService->updateAllStatus($envelopeId, CstStatus::CANCELED);

					$returned = [
						CstReturn::CODE	=> 1,
						CstReturn::MESSAGE	=> CstStatus::CANCELED,
					];
					break;
				case strcasecmp($check[CstEntity::STATUS], CstStatus::CANCELED) === 0:
					$this->signService->updateAllStatus($envelopeId, CstStatus::CANCELED);

					$returned = [
						CstReturn::CODE	=> 1,
						CstReturn::MESSAGE	=> CstStatus::CANCELED,
					];
					break;
				default:
					$returned = $this->signService->cancelWorkflow($signSession->getWorkflowId());
					break;
			}
			// if (Helpers::getIfExists(CstEntity::STATUS, $check) && strcasecmp($check[CstEntity::STATUS], CstStatus::CANCELED) !== 0) {
			// 	$returned = $this->signService->cancelWorkflow($signSession->getWorkflowId());
			// } else {
			// 	// Transaction already cancelled on YUmiSign server, not on DB (weird...)
			// 	$returned = [
			// 		CstReturn::CODE	=> 1,
			// 		CstReturn::MESSAGE	=> CstCommon::ALREADY_CANCELLED,
			// 	];

			// 	// Update DB status to match with YUmiSign server
			// 	$this->signService->updateAllStatus($envelopeId, CstStatus::CANCELED);
			// }
			// Delete DB Issue Request if "Forcing process"
			if ($forceDeletion) {
				$this->mapper->deleteTransactions($envelopeId, CstEntity::ENVELOPE_ID, $recipient);
				// Delete YumiSign transaction on server if no DB row left or if no recipient defined (means all rows for this Envelope ID)
				// if ($this->mapper->countIssuesByTransactionId($envelopeId, CstEntity::ENVELOPE_ID) === 0 || strcasecmp($recipient, '') === 0) {
				if ($this->mapper->countIssuesByTransactionId($envelopeId, CstEntity::ENVELOPE_ID) === 0 || empty($recipient)) {
					$workflows = [];
					$workflows['workflows'][] = ['id' => $signSession->getWorkflowId(), 'workspaceId' => $signSession->getWorkspaceId()];
					$this->signService->deleteWorkflows($workflows);
				}

				$returned = [
					CstReturn::CODE	=> 1,
					CstReturn::MESSAGE	=> CstCommon::DELETED,
				];
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf(CstLogMessages::CRITICAL_ERROR_PROCESS, $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> CstException::DELETE_PROCESS,
			];
		}

		return $returned;
	}

	public function getTransactionsCompleted(
		string $userId,
		int $page,
		int $nbItems
	) {
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::COMPLETED);
	}

	public function getTransactionsDeclined(
		string $userId,
		int $page,
		int $nbItems
	) {
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::DECLINED);
	}

	public function getTransactionsExpired(
		string $userId,
		int $page,
		int $nbItems
	) {
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::EXPIRED);
	}

	public function getTransactionsFailed(
		string $userId,
		int $page,
		int $nbItems
	) {
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::FAILED);
	}

	public function getTransactionsPending(
		string $userId,
		int $page,
		int $nbItems
	) {
		try {
			// Retrieve all WFW from YMS and update global_status and status according to the records
			$this->signService->checkAsyncSignatureTask($userId); // Nothing to return, the records will be gathered with the next DB request
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}

		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::PENDING);
	}

	public function webhook(
		string $headerYumiSign,
		array $requestBody,
		?string $applicantIdFromController = null
	): array {
		$processStatus = [];
		$applicantId = $applicantIdFromController ?? '';
		$envelopeId = '';

		try {
			if (empty($headerYumiSign)) {
				$warningMsg = $this->l10n->t('YumiSign signature is missing');
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			$headerData = [];
			foreach (explode(',', $headerYumiSign) as $part) {
				$kv = explode('=', trim($part), 2);
				if (count($kv) === 2) {
					$headerData[$kv[0]] = $kv[1];
				}
			}
			$headerTimestamp = trim((string) ($headerData['t'] ?? ''));
			$headerV1 = trim((string) ($headerData['v1'] ?? ''));
			if ($headerTimestamp === '' || $headerV1 === '') {
				throw new Exception('YumiSign signature format is invalid', 1);
			}

			if (array_key_exists('_route', $requestBody)) {
				unset($requestBody['_route']);
			}

			$envelopeId = Helpers::getArrayData($requestBody, CstRequest::ID, true, 'YumiSign transaction ID field is missing');
			$status = strtolower(trim((string) Helpers::getArrayData($requestBody, CstEntity::STATUS, true, 'YumiSign transaction status field is missing')));

			try {
				$yumisignSession = $this->mapper->findTransaction($envelopeId, CstEntity::ENVELOPE_ID);
			} catch (DoesNotExistException $e) {
				$warningMsg = "YumiSign transaction {$envelopeId} not found";
				$this->logRCDevs->warning($warningMsg, __FUNCTION__);
				throw new Exception($warningMsg, 1);
			}

			$applicantId = $applicantId !== '' ? $applicantId : (string) $yumisignSession->getApplicantId();
			$secret = trim((string) $yumisignSession->getSecret());
			if ($secret === '') {
				throw new Exception('YumiSign transaction secret not found', 1);
			}

			$payload = $headerTimestamp . '.' . json_encode($requestBody, JSON_UNESCAPED_SLASHES);
			$expectedSignature = hash_hmac('sha256', $payload, $secret);
			if (!hash_equals($expectedSignature, $headerV1)) {
				throw new Exception('YumiSign transaction bad key', 1);
			}

			switch ($status) {
				case CstStatus::NOT_STARTED:
				case CstStatus::APPROVED:
				case CstStatus::CANCELED:
				case CstStatus::DECLINED:
				case CstStatus::EXPIRED:
				case CstStatus::TO_BE_ARCHIVED:
				case CstStatus::STARTED:
					$steps = Helpers::getArrayData($requestBody, CstRequest::STEPS, true, 'YumiSign transaction steps fields are missing');
					foreach ($steps as $step) {
						foreach (($step['actions'] ?? []) as $action) {
							$recipient = (string) ($action['recipientEmail'] ?? '');
							$recipientStatus = strtolower(trim((string) ($action['status'] ?? $status)));
							if ($recipient !== '') {
								$this->signService->updateStatus($envelopeId, $recipient, $status, $recipientStatus);
							}
						}
					}
					break;
				case CstStatus::SIGNED:
					if (!array_key_exists('documents', $requestBody) || !is_array($requestBody['documents'])) {
						$workflow = json_decode((string) $this->signService->debriefWorkflow((int) $yumisignSession->getWorkflowId())->getBody(), true);
						if (is_array($workflow) && isset($workflow['documents']) && is_array($workflow['documents'])) {
							$requestBody['documents'] = $workflow['documents'];
						}
					}

					$resp = $this->signService->saveTransactionFiles($requestBody, (string) $yumisignSession->getApplicantId(), __FUNCTION__);
					if (($resp[CstRequest::SAVED] ?? false) === true) {
						$this->signService->updateAllStatus($envelopeId, $status);
						$this->notifyNextPendingRecipient($envelopeId);
					}
					break;
				default:
					$this->logRCDevs->debug("Webhook status ignored: {$status}", __FUNCTION__);
					break;
			}

			$processStatus[CstReturn::CODE] = true;
			$processStatus[CstReturn::MESSAGE] = 'YumiSign transaction updated';
			$processStatus[CstRequest::STATUS] = $status;
		} catch (\Throwable $th) {
			$warningMsg = "{$th->getMessage()} / Envelope ID : {$envelopeId}";
			$this->logRCDevs->error($warningMsg, __FUNCTION__);
			$processStatus = Helpers::warning($warningMsg);
		}

		try {
			$manager = \OC::$server->get(IManager::class);
			$notification = $manager->createNotification();
			if (!empty($applicantId)) {
				$notification
					->setApp($this->configurationService->getAppId())
					->setUser($applicantId)
					->setDateTime(new DateTime())
					->setObject(CstRequest::ENVELOPEID, $envelopeId)
					->setSubject($this->configurationService->getApplicationName(), [
						CstReturn::CODE => true,
						CstReturn::MESSAGE => 'YumiSign transaction updated',
						CstRequest::STATUS => $status,
					]);
				$manager->notify($notification);
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__);
		}

		return $processStatus;
	}
}
