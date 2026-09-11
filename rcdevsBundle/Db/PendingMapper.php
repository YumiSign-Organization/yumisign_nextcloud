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
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransaction;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use Exception;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Throwable;

class PendingMapper extends TransactionMapper
{
	protected string $className = Pending::class;

	public function __construct(
		IDBConnection		$db,
		IAppConfig			$config,
		LogRCDevs $logRCDevs,
	) {
		parent::__construct(
			$db,
			$config,
			$logRCDevs,
		);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	/**
	 * Count Signatures Pendings stored in DB according to given parameter
	 * 
	 * @param string $id 
	 * @param string $filterColumnName 
	 * @return CDEM 
	 */
	private function _commonCount(
		string $id,
		string $filterColumnName
	): CDEM {
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->select($queryBuilder->createFunction(CstDatabase::COUNTALL))
				->from($this->getTableName())
				->where($queryBuilder->expr()->eq($filterColumnName, $queryBuilder->createNamedParameter($id)))
			;

			// Add more filters
			$this->whereGlobalStatusPending($queryBuilder);
			$this->whereExpiryDateGreaterEqual($queryBuilder);
			$this->whereChangeStatus($queryBuilder);

			$result = $queryBuilder->executeQuery();
			$count = $result->fetchOne();
			$result->closeCursor();

			$cdem->setOk(
				data: [
					CstDatabase::COUNT => $count,
				],
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	/**
	 * Count Signatures Pendings stored in DB for a specific Applicant
	 * 
	 * @param string $applicantId 
	 * @return CDEM 
	 */
	public function countByApplicant(
		string $applicantId
	): CDEM {
		return $this->_commonCount(
			$applicantId,
			CstTransaction::APPLICANT_ID,
		);
	}

	/**
	 * 	 * Count the Transactions Pendings according to the TransactionID
	 * Can have several DB records for unique TransactionID according to the number of Repicients

	 * @param string $transactionId 
	 * @return CDEM 
	 */
	public function countByTransactionId(
		string $transactionId
	): CDEM {
		$cdem = $this->_commonCount(
			$transactionId,
			CstTransaction::TRANSACTION_ID,
		);

		return $cdem;
	}

	/**
	 * Retrieve all Applicant's Transactions
	 * 
	 * @param string|null $applicantId 
	 * @param int $page 
	 * @param int $nbItems 
	 * @return CDEM 
	 */
	public function findByApplicant(
		?string		$transactionId	= null,
		?string		$applicantId	= null,
		?int		$page			= 0,
		?int		$nbItems		= 0
	): CDEM {
		$cdem = new CDEM();

		try {
			// Backward compatibility: legacy callers passed applicantId in first argument.
			if (!is_null($transactionId) && is_null($applicantId)) {
				$applicantId = $transactionId;
				$transactionId = null;
			}

			$nbItems = $this->getNbItems($nbItems ?? 0);

			// Query
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
				// Add order by to display on WebUI
				->orderBy(CstTransaction::CHANGE_STATUS,	'desc')
				->addOrderBy(CstTransaction::CREATED,		'desc')
				->addOrderBy(CstTransaction::RECIPIENT,		'asc')
				//
				;

			// Add more filters
			$this->whereIfExistsTransaction($transactionId, $queryBuilder);
			$this->whereIfExistsApplicant($applicantId, $queryBuilder);
			$this->whereGlobalStatusPending($queryBuilder);
			$this->whereExpiryDateGreaterEqual($queryBuilder);
			$this->whereChangeStatus($queryBuilder);

			$queryBuilder->setFirstResult(($page ?? 0) * $nbItems);
			$queryBuilder->setMaxResults($nbItems);

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

	/**
	 * Retrieve all Transactions in DB
	 * 
	 * @param string|null $applicantId 
	 * @param bool $ignoreExpiryDate 
	 * @return CDEM 
	 */
	public function findByApplicantAll(
		string|null $applicantId		= null,
		bool		$ignoreExpiryDate	= false,
	): CDEM {
		$cdem = new CDEM();

		try {
			$nbItems = $this->getNbItemsAll();

			// Query
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder
				->select(CstDatabase::_ALL)
				->from($this->getTableName())
				// Add order by to display on WebUI
				->orderBy(CstTransaction::CHANGE_STATUS,	'desc')
				->addOrderBy(CstTransaction::CREATED,		'desc')
				->addOrderBy(CstTransaction::RECIPIENT,		'asc')
			;

			// Add more filters
			$this->whereIfExistsApplicant($applicantId, $queryBuilder);
			$this->whereGlobalStatusPending($queryBuilder);
			if (!$ignoreExpiryDate) {
				$this->whereExpiryDateGreaterEqual($queryBuilder);
				$this->whereChangeStatus($queryBuilder);
			}

			$queryBuilder->setMaxResults($nbItems);

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
}
