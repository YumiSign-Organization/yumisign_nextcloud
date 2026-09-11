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
use OCA\YumiSignNxtC\RCDevs\Constant\CstStatus;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransaction;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use Exception;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Throwable;

class CommonMapper extends QBMapper
{
	protected	ConfigurationService	$configurationService;
	protected	string	$nxcToken				= '';
	protected	string	$tableAlias				= 'rcdevsSess';
	public		int		$backInTime				= 3600; // Use to find users' last activity and to reduce the number of retrieved transactions
	public		int		$maxItems				= 50;
	public		int 	$rightNow;

	public function __construct(
		IDBConnection		$db,
		IAppConfig			$config,
		protected LogRCDevs $logRCDevs,
		string|null		$entityClass = null,
	) {
		$this->configurationService = new ConfigurationService($config);

		parent::__construct(
			$db,
			$this->configurationService->getAppTableNameSessions(),
			entityClass: $entityClass
		);

		$this->rightNow = intval(time());
	}

	/**
	 * Common count
	 *
	 * @param array $person
	 * @param array $status
	 * @return CDEM
	 */
	protected function __countTransactionsByPersonByStatus(
		array $person,
		array $status
	): CDEM {
		$cdem = new CDEM();

		try {
			// Get parameters to filter SQL query
			$keyPerson		= array_key_first($person);
			$valuePerson	= $person[$keyPerson];
			$keyStatus		= array_key_first($status);
			$valueStatus	= $status[$keyStatus];

			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select($queryBuilder->createFunction(CstDatabase::COUNTALL))
				->from($this->getTableName())
				->where($queryBuilder->expr()->eq($keyPerson, $queryBuilder->createNamedParameter($valuePerson)))
				->andWhere(
					$queryBuilder->expr()->eq($keyStatus, $queryBuilder->createNamedParameter($valueStatus)),
				);

			// Run query
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
	 * Common research
	 * 
	 * @param array $person 
	 * @param array $status 
	 * @param int|null $page 
	 * @param int|null $nbItems 
	 * @return CDEM 
	 */
	protected function __findTransactionsByPersonByStatus(
		array	$person,
		array	$status,
		?int	$page		= null,
		?int	$nbItems	= null,
	): CDEM {
		$cdem = new CDEM();

		try {
			// Get parameters to filter SQL query
			$keyPerson		= array_key_first($person);
			$valuePerson	= $person[$keyPerson];
			$keyStatus		= array_key_first($status);
			$valueStatus	= $status[$keyStatus];

			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
				->where($queryBuilder->expr()->eq($keyStatus, $queryBuilder->createNamedParameter($valueStatus)))
				->orderBy(CstTransaction::CHANGE_STATUS,	'desc')
				->addOrderBy(CstTransaction::CREATED,		'desc')
				->addOrderBy(CstTransaction::RECIPIENT,		'asc')
			;

			switch ($keyPerson) {
				case CstTransaction::APPLICANT_ID:
					$this->whereIfExistsApplicant($valuePerson, $queryBuilder);
					break;

				case CstTransaction::RECIPIENT:
					$this->whereIfExistsRecipient($valuePerson, $queryBuilder);
					break;

				default:
					# No WHERE condition to add
					break;
			}

			$this->focusPageItems($page, $nbItems, $queryBuilder);

			$result = $this->findEntities($queryBuilder);

			$cdem->setOk(
				data: [
					CstDatabase::TRANSACTIONS => $result,
				],
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	protected function commonSetParameter(
		string			$fieldName,
		mixed			$fieldValue,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($fieldValue)) {
			$queryBuilder
				->set($fieldName, $queryBuilder->createParameter($fieldName))
				->setParameter($fieldName, $fieldValue);
		}
	}

	/**
	 * Set the page to focus on and number of items to retrieve
	 *
	 * @param int $page
	 * @param int $nbItems
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function focusPageItems(
		int|null		$page			= null,
		int|null		$nbItems		= null,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($page) && !is_null($nbItems)) {
			$queryBuilder->setFirstResult($page * $nbItems);
			$queryBuilder->setMaxResults($nbItems);
		}
	}

	/**
	 * Get the number of items to display
	 * If input is empty, then data from configuration is used
	 *
	 * @param int $nbItems
	 * @return int
	 * @throws Throwable
	 * @throws Exception
	 */
	protected function getNbItems(
		int $nbItems
	): int {
		$returned = 0;

		try {
			$returned = ($nbItems === 0)
				? $this->configurationService->getUiItemsPerPage()
				: $nbItems;
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			$returned = 0;
		}

		return $returned;
	}

	/**
	 * Get the number of maximum items to display in full listings
	 * If input is empty, then data from configuration is used
	 *
	 * @param int $nbItems
	 * @return int
	 * @throws Throwable
	 * @throws Exception
	 */
	protected function getNbItemsAll(): int
	{
		$returned = 0;

		try {
			$returned = $this->configurationService->getItemsListing();
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			$returned = 0;
		}

		return $returned;
	}

	protected function setTransactionId(
		string $value,
		IQueryBuilder &$queryBuilder
	): void {
		$this->commonSetParameter(CstTransaction::TRANSACTION_ID, $value, $queryBuilder);
	}

	protected function setApplicantId(
		string $value,
		IQueryBuilder &$queryBuilder
	): void {
		$this->commonSetParameter(CstTransaction::APPLICANT_ID, $value, $queryBuilder);
	}

	protected function setChangeStatus(
		IQueryBuilder &$queryBuilder,
		int|null $value = null,
	): void {
		$value = $value ?? $this->rightNow;
		$this->commonSetParameter(CstTransaction::CHANGE_STATUS, $value, $queryBuilder);
	}

	protected function setGlobalStatus(
		string $value,
		IQueryBuilder &$queryBuilder
	): void {
		$this->commonSetParameter(CstTransaction::GLOBAL_STATUS, $value, $queryBuilder);
	}

	/**
	 * Filter Transactions where CHANGE_STATUS field is lower than current time.
	 * Just a security.
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereChangeStatus(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere($queryBuilder->expr()->lt(CstTransaction::CHANGE_STATUS, $queryBuilder->createNamedParameter(strval($this->rightNow), IQueryBuilder::PARAM_INT)));
	}

	/**
	 * Filter Transactions where EXPIRY_DATE field is greater than current time.
	 * Not needed to get expired Transactions
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereExpiryDateGreaterEqual(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere($queryBuilder->expr()->gte(CstTransaction::EXPIRY_DATE, $queryBuilder->createNamedParameter(strval($this->rightNow), IQueryBuilder::PARAM_INT)));
	}

	/**
	 * Filter Transactions where EXPIRY_DATE field is lower than current time.
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereExpiryDateLower(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere($queryBuilder->expr()->lt(CstTransaction::EXPIRY_DATE, $queryBuilder->createNamedParameter(strval($this->rightNow), IQueryBuilder::PARAM_INT)));
	}

	/**
	 * Add WHERE condition to the query; Global Status field is set to ISSUE (Issues list in config.xml)
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 * @throws Throwable
	 * @throws Exception
	 */
	protected function whereGlobalStatusIssue(
		IQueryBuilder &$queryBuilder
	): void {
		$orStatus = [];
		$issueStatus = $this->configurationService->getStatusIssue();

		foreach ($issueStatus as $unitIssueStatusKey => $unitIssueStatusValue) {
			$orStatus[] = $queryBuilder->expr()->eq(CstTransaction::GLOBAL_STATUS, $queryBuilder->createNamedParameter($unitIssueStatusValue));
		}

		$queryBuilder->andWhere(
			$queryBuilder->expr()->orX(...$orStatus)
		);
		$this->logRCDevs->debug(sprintf('Issue status: [%s]', json_encode($orStatus)));
	}

	/**
	 * Add WHERE condition to the query; Global Status field is set to PENDING (Pendings list in config.xml)
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 * @throws Throwable
	 * @throws Exception
	 */
	protected function whereGlobalStatusPending(
		IQueryBuilder &$queryBuilder
	): void {
		$orStatus = [];
		$pendingStatus = $this->configurationService->getStatusPending();
		$globalStatusSql = $queryBuilder->createFunction(sprintf('LOWER(%s)', CstTransaction::GLOBAL_STATUS));

		foreach ($pendingStatus as $unitPendingStatusKey => $unitPendingStatusValue) {
			$orStatus[] = $queryBuilder->expr()->eq(
				$globalStatusSql,
				$queryBuilder->createNamedParameter(strtolower($unitPendingStatusValue))
			);
		}

		$queryBuilder->andWhere(
			$queryBuilder->expr()->orX(...$orStatus)
		);
		$this->logRCDevs->debug(sprintf('Pending status : [%s]', json_encode($orStatus)));
	}

	/**
	 * Add WHERE condition to the query; Global Status field is set to SIGNED
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereGlobalStatusSigned(
		IQueryBuilder &$queryBuilder
	): void {
		$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::SIGNED)));
	}

	/**
	 * Add WHERE condition for the field MUTEX
	 *
	 * @param mixed $mutexValue
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereMutex(
		string|null		$mutexValue,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (is_null($mutexValue)) {
			$queryBuilder->andWhere($queryBuilder->expr()->isNull(CstTransaction::MUTEX));
		} else {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::MUTEX, $queryBuilder->createNamedParameter(CstStatus::SIGNED)));
		}
	}

	/**
	 * Add WHERE condition for the field APPLICANT_ID
	 *
	 * @param string|null $applicantId
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereIfExistsApplicant(
		string|null		$applicantId,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($applicantId)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId, IQueryBuilder::PARAM_STR)));
		}
	}

	/**
	 * Add WHERE condition for the field TRANSACTION_ID
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereIfExistsGlobalStatus(
		string|null		$globalStatus,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($globalStatus)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::GLOBAL_STATUS, $queryBuilder->createNamedParameter($globalStatus)));
		}
	}

	/**
	 * Add WHERE condition for the field TRANSACTION_ID
	 *
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereIfExistsTransaction(
		string|null		$transactionId,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($transactionId)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::TRANSACTION_ID, $queryBuilder->createNamedParameter($transactionId)));
		}
	}

	/**
	 * * Add WHERE condition for the field RECIPIENT
	 *
	 * @param string|null $recipient
	 * @param IQueryBuilder &$queryBuilder
	 * @return void
	 */
	protected function whereIfExistsRecipient(
		string|null		$recipient,
		IQueryBuilder	&$queryBuilder,
	): void {
		if (!is_null($recipient)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::RECIPIENT, $queryBuilder->createNamedParameter($recipient, IQueryBuilder::PARAM_STR)));
		}
	}
}
