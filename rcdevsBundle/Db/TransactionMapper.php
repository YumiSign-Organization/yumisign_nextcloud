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

namespace OCA\YumiSignNxtC\RCDevs\Db;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstDatabase;
use OCA\YumiSignNxtC\RCDevs\Constant\CstEntity;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstStatus;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransaction;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use Exception;
use OCP\DB\Exception as DBException;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use RuntimeException;
use Throwable;

class TransactionMapper extends CommonMapper
{
	public function __construct(
		IDBConnection		$db,
		IAppConfig			$config,
		protected LogRCDevs $logRCDevs,
	) {
		$this->configurationService = new ConfigurationService($config);

		parent::__construct(
			$db,
			$config,
			$this->logRCDevs,
		);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	/**
	 * 	 * A global Transaction may have several sub-transactions with the same Transaction ID
	 * eg. in the case of multiple Recipients

	 * @param string $transactionId 
	 * @return CDEM 
	 */
	public function countByTransactionId(
		string $transactionId
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->select($queryBuilder->createFunction(CstDatabase::COUNTALL))
				->from($this->getTableName());

			$this->whereIfExistsTransaction($transactionId, $queryBuilder);

			$result = $queryBuilder->executeQuery();
			$count = $result->fetchOne();
			$result->closeCursor();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				]
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Delete all Applicant's transactions with the same Transaction ID
	 * 
	 * @param string $transactionId 
	 * @param string $applicantId 
	 * @return CDEM 
	 */
	public function deleteByApplicantId(
		string $transactionId,
		string $applicantId
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->delete($this->configurationService->getAppTableNameSessions());
			$this->whereIfExistsTransaction($transactionId, $queryBuilder);
			$this->whereIfExistsApplicant($applicantId, $queryBuilder);

			$count = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				]
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Delete all Transactions according to the given Transaction ID
	 * 
	 * @param string $transactionId 
	 * @param ?string $recipient 
	 * @return CDEM 
	 */
	public function deleteTransaction(
		string		$transactionId,
		?string	$recipient = null
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->delete($this->configurationService->getAppTableNameSessions());
			$this->whereIfExistsTransaction($transactionId, $queryBuilder);
			$this->whereIfExistsRecipient($recipient, $queryBuilder);

			$count = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				]
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Retrieve all Transactions in DB
	 * 
	 * @param ?int $page 
	 * @param ?int $nbItems 
	 * @return CDEM 
	 */
	public function findAll(
		?int	$page		= null,
		?int	$nbItems	= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var array $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->select(CstDatabase::_ALL)
				->from($this->getTableName())
				->orderBy(CstTransaction::CHANGE_STATUS,	'desc')
				->addOrderBy(CstTransaction::CREATED,		'desc')
			;

			$this->focusPageItems($page, $nbItems, $queryBuilder);

			$result = $this->findEntities($queryBuilder);

			$cdem->setOk(
				data: $result,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Find all pairs Applicant/TransactionID
	 * Multiple pairs can be found if the Applicant created a multi-recipient transactions

	 * @param ?string $transactionId 
	 * @param ?string $applicantId 
	 * @param ?int $page 
	 * @param ?int $nbItems 
	 * @return CDEM 
	 */
	public function findByApplicant(
		?string	$transactionId	= null,
		?string	$applicantId	= null,
		?int	$page			= null,
		?int	$nbItems		= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
			;

			$this->whereIfExistsTransaction($transactionId, $queryBuilder);
			$this->whereIfExistsApplicant($applicantId, $queryBuilder);

			$this->focusPageItems($page, $nbItems, $queryBuilder);

			$result = $this->findEntities($queryBuilder);

			$cdem->setOk(
				data: $result,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Find all pairs Recipient/TransactionID
	 * Not restricted to only one pair technically speaking.
	 * In theory, should be only one pair in the result data array

	 * @param string $transactionId 
	 * @param string $recipient 
	 * @param null|int $page 
	 * @param null|int $nbItems 
	 * @return CDEM 
	 */
	public function findByRecipient(
		string		$transactionId,
		string		$recipient,
		?int	$page			= null,
		?int	$nbItems		= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
			;

			// Add more filters
			$this->whereIfExistsRecipient($recipient, $queryBuilder);
			$this->whereIfExistsTransaction($transactionId, $queryBuilder);

			$this->focusPageItems($page, $nbItems, $queryBuilder);

			$result = $this->findEntities($queryBuilder);

			$cdem->setOk(
				data: $result,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Find all Transactions with the given status
	 * 
	 * @param null|string $globalStatus 
	 * @param null|int $page 
	 * @param null|int $nbItems 
	 * @return CDEM 
	 */
	public function findByGlobalStatus(
		?string	$globalStatus	= null,
		?int	$page			= null,
		?int	$nbItems		= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
			;

			// Add more filters
			$this->whereIfExistsGlobalStatus($globalStatus, $queryBuilder);

			$this->focusPageItems($page, $nbItems, $queryBuilder);

			$result = $this->findEntities($queryBuilder);

			$cdem->setOk(
				data: $result,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Update Global Status for the given Transaction
	 * 
	 * @param string $transactionId 
	 * @param string $newStatus 
	 * @return array 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function updateAllStatuses(
		string $transactionId,
		string $newStatus,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->update($this->configurationService->getAppTableNameSessions());
			$this->setGlobalStatus($newStatus, $queryBuilder);
			$this->whereIfExistsTransaction($transactionId, $queryBuilder);

			$count = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				]
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Update Global Status for all expired transactions
	 * The update can focus on a specific Applicant
	 * 
	 * @param ?string $applicantId 
	 * @return array 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function updateGlobalStatusByExpiryDate(
		?string	$applicantId	= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->update($this->configurationService->getAppTableNameSessions());
			
			$this->setGlobalStatus(CstStatus::EXPIRED, $queryBuilder);
			$this->setChangeStatus($queryBuilder);

			$this->whereIfExistsApplicant($applicantId, $queryBuilder);
			$this->whereExpiryDateLower($queryBuilder);

			$count = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				]
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/** ******************************************************************************************
	 * LEGACY COMPATIBILITY (former SignSessionMapper API)
	 ****************************************************************************************** */
	protected function joinActivity(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->join(
			$this->tableAlias,
			'authtoken',
			$this->nxcToken,
			'uid = applicant_id',
		);
	}

	protected function whereLastActivity(
		int $rightNow,
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere('last_activity >= :paramLastActivityBackInTime')
			->setParameter('paramLastActivityBackInTime', intval($rightNow) - $this->backInTime, IQueryBuilder::PARAM_INT);
	}

	protected function whereGlobalStatusActive(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere(
			$queryBuilder->expr()->andX(
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_FOUND)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::SIGNED)),
			)
		);
	}

	public function countIssuesByTransactionId(
		string $transactionId,
		string $transactionColumnName
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::DECLINED)),
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::EXPIRED)),
				)
			);

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusCompleted(
		string $applicantId
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::SIGNED)));

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusDeclined(
		string $applicantId
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::DECLINED)));

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusExpired(
		string $applicantId
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::EXPIRED)));

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusFailed(
		string $applicantId
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::FAILED)));

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusPending(
		string $applicantId
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)));

		$this->whereGlobalStatusPending($queryBuilder);
		$this->whereExpiryDateGreaterEqual($queryBuilder);

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function countTransactions(
		int $rightNow,
		?string $applicantId = null
	): array {
		try {
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->selectAlias($queryBuilder->createFunction('COUNT(*)'), CstDatabase::COUNT)
				->from($this->getTableName(), $this->tableAlias)
				->where('1 = 1');

			$this->joinActivity($queryBuilder);
			$this->whereIfExistsApplicant($applicantId, $queryBuilder);
			$this->whereLastActivity($rightNow, $queryBuilder);
			$this->whereGlobalStatusActive($queryBuilder);
			$this->whereExpiryDateGreaterEqual($queryBuilder);
			$this->whereChangeStatus($queryBuilder);

			$result = $queryBuilder->executeQuery();
			$count = intval($result->fetchOne());
			$result->closeCursor();

			return [
				CstReturn::CODE		=> 1,
				CstReturn::DATA		=> $count,
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> null,
			];
		} catch (\Throwable $th) {
			$this->logRCDevs->error("Query building failed : {$th->getMessage()}", $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			return [
				CstReturn::CODE		=> 0,
				CstReturn::DATA		=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}
	}

	public function countTransactionsByTransactionId(
		string $transactionId,
		string $transactionColumnName
	): int {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));

		$result = $queryBuilder->executeQuery();
		$count = intval($result->fetchOne());
		$result->closeCursor();

		return $count;
	}

	public function deleteTransactions(
		string $transactionId,
		string $transactionColumnName,
		string $recipient = ''
	): void {
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->delete($this->configurationService->getAppTableNameSessions())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));

		if ($recipient !== '') {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
		}

		$queryBuilder->executeStatement();
	}

	public function findAllTransactionsIds(
		int $rightNow,
		string $transactionColumnName,
		?string $applicantId = null,
		int $page = -1,
		int $nbItems = -1
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstEntity::APPLICANT_ID, $transactionColumnName)
			->from($this->getTableName(), $this->tableAlias)
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->where('1 = 1');

		$this->joinActivity($queryBuilder);
		$this->whereIfExistsApplicant($applicantId, $queryBuilder);
		$this->whereLastActivity($rightNow, $queryBuilder);
		$this->whereGlobalStatusActive($queryBuilder);
		$this->whereExpiryDateGreaterEqual($queryBuilder);
		$this->whereChangeStatus($queryBuilder);

		if ($page !== -1 && $nbItems !== -1) {
			$queryBuilder->setFirstResult($page * $nbItems);
			$queryBuilder->setMaxResults($nbItems);
		}

		return $this->findEntities($queryBuilder);
	}

	public function findRecipientTransaction(
		string $transactionId,
		string $transactionColumnName,
		string $recipient
	): mixed {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));

		return $this->findEntity($queryBuilder);
	}

	public function findRecipientsIdsByTransaction(
		string $transactionId,
		string $transactionColumnName
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstEntity::RECIPIENT_ID)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));

		return $this->findEntities($queryBuilder);
	}

	public function findTransaction(
		string $transactionId,
		string $transactionColumnName,
		string $recipient = ''
	): mixed {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->setMaxResults(1);

		if ($recipient !== '') {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
		}

		return $this->findEntity($queryBuilder);
	}

	public function findTransactions(
		string $transactionId = '',
		string $transactionColumnName = '',
		string $applicant = ''
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)->from($this->getTableName());
		$whereCommand = 'where';

		if ($transactionId !== '') {
			if ($transactionColumnName === '') {
				throw new Exception('Transaction column name cannot be empty');
			}
			$queryBuilder->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));
			$whereCommand = 'andWhere';
		}

		if ($applicant !== '') {
			$queryBuilder->{$whereCommand}($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicant)));
		}

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusCompleted(
		string $applicantId,
		int $page = 0,
		int $nbItems = 20
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::SIGNED)))
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->setFirstResult($page * $nbItems)
			->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusDeclined(
		string $applicantId,
		int $page = 0,
		int $nbItems = 20
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::DECLINED)))
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->setFirstResult($page * $nbItems)
			->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusExpired(
		string $applicantId,
		int $page = 0,
		int $nbItems = 20
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::EXPIRED)))
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->setFirstResult($page * $nbItems)
			->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusFailed(
		string $applicantId,
		int $page = 0,
		int $nbItems = 20
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::ERROR)),
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)),
					$queryBuilder->expr()->andX(
						$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::STARTED)),
						$queryBuilder->expr()->lt(CstEntity::EXPIRY_DATE, $queryBuilder->createNamedParameter(time())),
					),
					$queryBuilder->expr()->andX(
						$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_STARTED)),
						$queryBuilder->expr()->lt(CstEntity::EXPIRY_DATE, $queryBuilder->createNamedParameter(time())),
					),
				)
			)
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->setFirstResult($page * $nbItems)
			->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusPending(
		string $applicantId,
		int $page = 0,
		int $nbItems = 20
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstDatabase::_ALL)
			->from($this->getTableName(), $this->tableAlias)
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->where('1 = 1');

		$this->whereIfExistsApplicant($applicantId, $queryBuilder);
		$this->whereGlobalStatusPending($queryBuilder);
		$this->whereExpiryDateGreaterEqual($queryBuilder);

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function updateTransactionsMutex(
		?string $threadId,
		string $transactionId,
		string $transactionColumnName,
		string $recipient = ''
	): void {
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set(CstEntity::MUTEX, $queryBuilder->createParameter(CstEntity::MUTEX))
				->setParameter(CstEntity::MUTEX, $threadId)
				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->orX(
					$queryBuilder->expr()->isNull(CstEntity::MUTEX),
					$queryBuilder->expr()->eq(CstEntity::MUTEX, $queryBuilder->createNamedParameter(''))
				));

			if ($recipient !== '') {
				$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
			}

			$queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf('Exception during updating mutex with message: %s', $th->getMessage()), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			throw $th;
		}
	}

	public function updateTransactionsMutexReset(
		?string $threadId,
		string $transactionId,
		string $transactionColumnName,
		string $recipient = ''
	): void {
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set(CstEntity::MUTEX, $queryBuilder->createParameter(CstEntity::MUTEX))
				->setParameter(CstEntity::MUTEX, '')
				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->eq(CstEntity::MUTEX, $queryBuilder->createNamedParameter($threadId)));

			if ($recipient !== '') {
				$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
			}

			$queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf('Exception during resetting mutex with message: %s', $th->getMessage()), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			throw $th;
		}
	}
}
