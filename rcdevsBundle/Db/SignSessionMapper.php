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

namespace OCA\RCDevs\Db;

use Exception;
use OCA\RCDevs\Service\ConfigurationService;
use OCA\RCDevs\Utility\Constantes\CstDatabase;
use OCA\RCDevs\Utility\Constantes\CstEntity;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use OCA\RCDevs\Utility\Constantes\CstStatus;
use OCA\RCDevs\Utility\LogRCDevs;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;

class SignSessionMapper extends QBMapper
{
	private		ConfigurationService	$configurationService;
	protected	string	$nxcToken = '';
	protected	string	$tableAlias = 'rcdevsSess';
	public		int		$backInTime = 3600; // Use to find users' last activity and to reduce the number of retrieved transactions
	public		int		$maxItems = 50;

	public function __construct(
		IDBConnection		$db,
		IConfig				$config,
		protected LogRCDevs $logRCDevs,
	) {
		$this->configurationService = new ConfigurationService($config);

		parent::__construct(
			$db,
			$this->configurationService->getAppTableNameSessions(),
			entityClass: null
		);
		$this->db = $db;
	}

	/**
	 * Query completions
	 */

	protected function commonSetParameter(array|null $unitTransactionToUpdate, string $paramName, IQueryBuilder &$queryBuilder)
	{
		if (
			!is_null($unitTransactionToUpdate) &&
			array_key_exists($paramName, $unitTransactionToUpdate) &&
			!is_null($unitTransactionToUpdate[$paramName])
		)
			$queryBuilder
				->set($paramName, $queryBuilder->createParameter($paramName))
				->setParameter($paramName, $unitTransactionToUpdate[$paramName]);
	}

	protected function joinActivity(IQueryBuilder &$queryBuilder)
	{
		$queryBuilder->join(
			$this->tableAlias,
			'authtoken',
			$this->nxcToken,
			'uid = applicant_id',
		);
	}

	protected function setApplicantIdIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
	{
		$this->commonSetParameter($unitTransactionToUpdate, CstEntity::APPLICANT_ID, $queryBuilder);
	}

	protected function setGlobalStatusIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
	{
		$this->commonSetParameter($unitTransactionToUpdate, CstEntity::GLOBAL_STATUS, $queryBuilder);
	}

	protected function whereApplicantIfExists(string|null $applicantId, IQueryBuilder &$queryBuilder)
	{
		if (!is_null($applicantId)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId, IQueryBuilder::PARAM_STR)));
		}
	}

	protected function whereChangeStatus(int $rightNow, IQueryBuilder &$queryBuilder)
	{
		$queryBuilder->andWhere($queryBuilder->expr()->lt(CstEntity::CHANGE_STATUS, $queryBuilder->createNamedParameter(strval($rightNow), IQueryBuilder::PARAM_INT)));
	}

	protected function whereExpiryDate(int $rightNow, IQueryBuilder &$queryBuilder)
	{
		$queryBuilder->andWhere($queryBuilder->expr()->gte(CstEntity::EXPIRY_DATE, $queryBuilder->createNamedParameter(strval($rightNow), IQueryBuilder::PARAM_INT)));
	}

	protected function whereGlobalStatusActive(IQueryBuilder &$queryBuilder)
	{
		// AND condition
		$queryBuilder->andWhere(
			$queryBuilder->expr()->andX(
				//	TODO	Add these status in config.xml like pending status
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_FOUND)),
				$queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::SIGNED)),
			)
		);
	}

	protected function whereGlobalStatusPending(IQueryBuilder &$queryBuilder)
	{
		$orStatus = [];
		$pendingStatus = $this->configurationService->getStatusPending();

		// OR condition
		foreach ($pendingStatus as $unitPendingStatusKey => $unitPendingStatusValue) {
			$orStatus[] = $queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter($unitPendingStatusValue));
		}

		$queryBuilder->andWhere(
			$queryBuilder->expr()->orX(...$orStatus)
		);
		$this->logRCDevs->debug(sprintf('Pending status : [%s]', json_encode($orStatus)));
	}

	protected function whereLastActivity(int $rightNow, IQueryBuilder &$queryBuilder)
	{
		$queryBuilder->andWhere('last_activity >= :paramLastActivityBackInTime')
			->setParameter('paramLastActivityBackInTime', intval($rightNow) - $this->backInTime, IQueryBuilder::PARAM_INT);
	}

	protected function whereRecipientIfExists(string|null $recipient, IQueryBuilder &$queryBuilder)
	{
		if (!is_null($recipient)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient, IQueryBuilder::PARAM_STR)));
		}
	}

	/**
	 * DB Ops
	 */

	public function countIssuesByApplicant(string $applicantId)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::DECLINED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::EXPIRED)),
				)
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countIssuesByTransactionId(string $transactionId, string $transactionColumnName)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::DECLINED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::EXPIRED)),
				)
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusCompleted(string $applicantId)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::SIGNED)),
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusDeclined(string $applicantId)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::DECLINED)),
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusExpired(string $applicantId)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::EXPIRED)),
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusFailed(string $applicantId)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::FAILED)),
			);

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactionsByApplicantByStatusPending(string $applicantId)
	{
		$rightNow = intval(time());

		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName());
		// Add more filters
		$this->whereApplicantIfExists($applicantId, $queryBuilder);
		$this->whereGlobalStatusPending($queryBuilder);
		$this->whereExpiryDate($rightNow, $queryBuilder);


		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function countTransactions(int $rightNow, string $applicantId = null): array
	{
		$returned = [];

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->selectAlias($queryBuilder->createFunction('COUNT(*)'), CstDatabase::COUNT)
				->from($this->getTableName(), $this->tableAlias)
				->where('1 = 1'); // Permits to use functions with andWhere conditions

			// Add more filters to prevent huge data
			$this->joinActivity($queryBuilder);

			$this->whereApplicantIfExists($applicantId, $queryBuilder);
			$this->whereLastActivity($rightNow, $queryBuilder);
			$this->whereGlobalStatusActive($queryBuilder);
			$this->whereExpiryDate($rightNow, $queryBuilder);
			$this->whereChangeStatus($rightNow, $queryBuilder);

			$result = $queryBuilder->executeQuery();
			$count = $result->fetchOne();
			$result->closeCursor();

			$returned = [
				CstRequest::CODE	=> 1,
				CstRequest::DATA	=> $count,
				CstRequest::ERROR	=> null,
			];
		} catch (\Throwable $th) {
			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getMessage(),
			];
			$this->logRCDevs->error("Query building failed : {$th->getMessage()}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		}

		return $returned;
	}

	public function countTransactionsByTransactionId(string $transactionId, string $transactionColumnName)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));

		$result = $queryBuilder->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function deleteTransactionByApplicant(string $transactionId, string $transactionColumnName, string $applicant)
	{
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->delete($this->configurationService->getAppTableNameSessions())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicant)));

		$queryBuilder->executeStatement();
	}

	public function deleteTransactions(string $transactionId, string $transactionColumnName, $recipient = ''): void
	{
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->delete($this->configurationService->getAppTableNameSessions())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));

		if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

		$queryBuilder->executeStatement();
	}

	public function findAll(int $page = -1, int $nbItems = -1): array
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		if ($page !== -1 && $nbItems !== -1) {
			$queryBuilder->setFirstResult($page * $nbItems);
			$queryBuilder->setMaxResults($nbItems);
		}

		return $this->findEntities($queryBuilder);
	}

	public function findAllTransactionsIds(int $rightNow, string $transactionColumnName, string $applicantId = null, int $page = -1, int $nbItems = -1): array
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('applicant_id', $transactionColumnName)
			->from($this->getTableName(), $this->tableAlias)
			// Add order by to display on WebUI
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc')
			->where('1 = 1'); // Permits to use functions with andWhere conditions

		// Junctions
		$this->joinActivity($queryBuilder);

		// Add more filters to prevent huge data
		$this->whereApplicantIfExists($applicantId, $queryBuilder);
		$this->whereLastActivity($rightNow, $queryBuilder);
		$this->whereGlobalStatusActive($queryBuilder);
		$this->whereExpiryDate($rightNow, $queryBuilder);
		$this->whereChangeStatus($rightNow, $queryBuilder);

		// Set pages
		if ($page !== -1 && $nbItems !== -1) {
			$queryBuilder->setFirstResult($page * $nbItems);
			$queryBuilder->setMaxResults($nbItems);
		}

		return $this->findEntities($queryBuilder);
	}

	public function findJob()
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('reserved_at', 'last_run')
			->from('jobs')
			->where($queryBuilder->expr()->eq('class', $queryBuilder->createNamedParameter('OCA\YumiSignNxtC\Cron\CheckAsyncSignatureTask')));

		$cursor = $queryBuilder->executeQuery();
		$ymsJob = $cursor->fetch();
		if (!$ymsJob) { // Not row in database => not an error, the cron just has never run
			$ymsJob = [
				'reserved_at'	=> 0,
				'last_run'		=> 0,
			];
		}
		$cursor->closeCursor();

		return $ymsJob;
	}

	public function findRecipientTransaction(string $transactionId, string $transactionColumnName, string $recipient)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

		return $this->findEntity($queryBuilder);
	}

	public function findRecipientsIdsByTransaction(string $transactionId, string $transactionColumnName): array
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstEntity::RECIPIENT_ID)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
		;

		return $this->findEntities($queryBuilder);
	}

	public function findCompletedByApplicant(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id',	$queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq('global_status',	$queryBuilder->createNamedParameter(CstStatus::SIGNED)))
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransaction(string $transactionId, string $transactionColumnName, string $recipient = '')
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->setMaxResults(1);

		if (!empty($recipient)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));
		}

		return $this->findEntity($queryBuilder);
	}

	public function findTransactions(string $transactionId = '', string $transactionColumnName = '', string $applicant = ''): array
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName());

		$whereCommand = 'where';

		if ($transactionId !== '') {
			if (empty($transactionColumnName)) {
				throw new Exception("Transaction column name cannot be empty");
			}
			$queryBuilder->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));
			$whereCommand = 'andWhere';
		}
		if ($applicant !== '') {
			$queryBuilder->$whereCommand($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicant)));
		}

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusCompleted(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::SIGNED)))
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusDeclined(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::DECLINED)))
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusExpired(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere($queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::EXPIRED)))
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		$returned = $this->findEntities($queryBuilder);
		return $returned;
	}

	public function findTransactionsByApplicantByStatusFailed(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::CANCELED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::ERROR)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)),
					$queryBuilder->expr()->andX(
						$queryBuilder->expr()->eq('global_status',	$queryBuilder->createNamedParameter(CstStatus::STARTED)),
						$queryBuilder->expr()->lt('expiry_date',	$queryBuilder->createNamedParameter(time())),
					),
					$queryBuilder->expr()->andX(
						$queryBuilder->expr()->eq('global_status',	$queryBuilder->createNamedParameter(CstStatus::NOT_STARTED)),
						$queryBuilder->expr()->lt('expiry_date',	$queryBuilder->createNamedParameter(time())),
					),
				)
			)
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc');

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsByApplicantByStatusPending(string $applicantId, int $page = 0, int $nbItems = 20)
	{
		$rightNow = intval(time());

		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('*')
			->from($this->getTableName(), $this->tableAlias)
			// Add order by to display on WebUI
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc')
			->where('1 = 1'); // Permits to use functions with andWhere conditions

		// Add more filters
		$this->whereApplicantIfExists($applicantId, $queryBuilder);
		$this->whereGlobalStatusPending($queryBuilder);
		$this->whereExpiryDate($rightNow, $queryBuilder);

		$queryBuilder->setFirstResult($page * $nbItems);
		$queryBuilder->setMaxResults($nbItems);

		return $this->findEntities($queryBuilder);
	}

	public function resetJob()
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->update('jobs')
			->set('reserved_at', $queryBuilder->createParameter('reserved_at'))
			->setParameter('reserved_at', 0)
			->where($queryBuilder->expr()->like('class', $queryBuilder->createNamedParameter(
				'%' . $this->db->escapeLikeParameter("\\{$this->configurationService->getAppNamespace()}") . '%'
			)));

		$queryBuilder->executeStatement();
	}

	/**
	 * UPDATES
	 */
	public function updateTransactionAllStatuses(string $transactionId, string $transactionColumnName, string $newStatus)
	{
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set('global_status', $queryBuilder->createParameter('global_status'))
				->setParameter('global_status', $newStatus)
				->set('status', $queryBuilder->createParameter('status'))
				->setParameter('status', $newStatus)
				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));
			$queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	public function updateTransactionStatus(string $transactionId, string $transactionColumnName, string $newStatus)
	{
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->update($this->configurationService->getAppTableNameSessions())
			->set('status', $queryBuilder->createParameter('status'))
			->setParameter('status', $newStatus)
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)));
		$queryBuilder->executeStatement();
	}

	public function updateTransactionsStatusExpired(): void
	{
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				// global status
				->set(CstEntity::GLOBAL_STATUS, $queryBuilder->createParameter(CstEntity::GLOBAL_STATUS))
				->setParameter(CstEntity::GLOBAL_STATUS, CstStatus::EXPIRED)
				// change_status
				->set(CstEntity::CHANGE_STATUS, $queryBuilder->createParameter(CstEntity::CHANGE_STATUS))
				->setParameter(CstEntity::CHANGE_STATUS, time())

				->where($queryBuilder->expr()->lt(CstEntity::EXPIRY_DATE, $queryBuilder->createNamedParameter(intval(time()))));

			$queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			throw $th;
		}
	}

	public function updateTransactionsMutex(string|null $threadId, string $transactionId, string $transactionColumnName, string $recipient = ''): void
	{
		// Not needed to use transactional query
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->select('*')
				->from($this->getTableName())
				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->eq(CstEntity::MUTEX, $queryBuilder->createNamedParameter('')));

			if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));

			$resp = $this->findEntities($queryBuilder);

			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				// mutex
				->set(CstEntity::MUTEX, $queryBuilder->createParameter(CstEntity::MUTEX))
				->setParameter(CstEntity::MUTEX, $threadId)

				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->eq(CstEntity::MUTEX, $queryBuilder->createNamedParameter('')));

			if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));

			$resp = $queryBuilder->executeStatement();

			$this->logRCDevs->debug(sprintf('UPD response : %d', $resp), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			throw $th;
		}
	}

	public function updateTransactionsMutexReset(string|null $threadId, string $transactionId, string $transactionColumnName, string $recipient = ''): void
	{
		// Not needed to use transactional query
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->select('*')
				->from($this->getTableName())
				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			;

			if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));

			$resp = $this->findEntities($queryBuilder);

			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				// mutex
				->set(CstEntity::MUTEX, $queryBuilder->createParameter(CstEntity::MUTEX))
				->setParameter(CstEntity::MUTEX, '')

				->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->eq(CstEntity::MUTEX, $queryBuilder->createNamedParameter($threadId)));

			if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));

			$resp = $queryBuilder->executeStatement();

			$this->logRCDevs->debug(sprintf('UPD response : %d', $resp), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			throw $th;
		}
	}
}
