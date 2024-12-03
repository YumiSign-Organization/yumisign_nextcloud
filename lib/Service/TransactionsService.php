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
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Utility\Constantes\CstCommon;
use OCA\YumiSignNxtC\Utility\Constantes\CstDatabase;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCA\YumiSignNxtC\Utility\Constantes\CstException;
use OCA\YumiSignNxtC\Utility\Constantes\CstRequest;
use OCA\YumiSignNxtC\Utility\Constantes\CstStatus;
use OCP\IL10N;

class TransactionsService
{
	const CNX_TIME_OUT = 3;

	public function __construct(
		private							$UserId,
		private		IL10N				$l10n,
		private		SignService			$signService,
		private		SignSessionMapper	$mapper,
		protected	LogRCDevs			$logRCDevs,
	) {}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	private function commonGetTransactions(string $userId, int $page, int $nbItems, string $status)
	{
		try {
			$status = ucfirst($status);
			$functionToRun = "findTransactionsByApplicantByStatus{$status}";

			$databaseResponse = $this->mapper->$functionToRun($userId, $page, $nbItems);

			$transactions = [];
			foreach ($databaseResponse as $databaseRecord) {
				$signatureType = new SignatureType(advanced: $databaseRecord->getAdvanced(), qualified: $databaseRecord->getQualified());

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

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function cancelTransaction(string $envelopeId, string $userId, bool $forceDeletion = false, string $recipient = '')
	{
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
						CstRequest::CODE	=> 1,
						CstRequest::MESSAGE	=> CstStatus::CANCELED,
					];
					break;
				case strcasecmp($check[CstEntity::STATUS], CstStatus::CANCELED) === 0:
					$this->signService->updateAllStatus($envelopeId, CstStatus::CANCELED);

					$returned = [
						CstRequest::CODE	=> 1,
						CstRequest::MESSAGE	=> CstStatus::CANCELED,
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
			// 		CstRequest::CODE	=> 1,
			// 		CstRequest::MESSAGE	=> CstCommon::ALREADY_CANCELLED,
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
					CstRequest::CODE	=> 1,
					CstRequest::MESSAGE	=> CstCommon::DELETED,
				];
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> CstException::DELETE_PROCESS,
			];
		}

		return $returned;
	}

	public function getTransactionsCompleted(string $userId, int $page, int $nbItems)
	{
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::COMPLETED);
	}

	public function getTransactionsDeclined(string $userId, int $page, int $nbItems)
	{
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::DECLINED);
	}

	public function getTransactionsExpired(string $userId, int $page, int $nbItems)
	{
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::EXPIRED);
	}

	public function getTransactionsFailed(string $userId, int $page, int $nbItems)
	{
		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::FAILED);
	}

	public function getTransactionsPending(string $userId, int $page, int $nbItems)
	{
		try {
			// Retrieve all WFW from YMS and update global_status and status according to the records
			$this->signService->checkAsyncSignatureTask($userId); // Nothing to return, the records will be gathered with the next DB request
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}

		return $this->commonGetTransactions($userId, $page, $nbItems, CstStatus::PENDING);
	}
}
