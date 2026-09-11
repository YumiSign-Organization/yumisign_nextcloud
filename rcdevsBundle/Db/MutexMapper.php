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
use OCA\YumiSignNxtC\RCDevs\Enum\CDEMLogLevel;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Throwable;

class MutexMapper extends CommonMapper
{
	public function __construct(
		IDBConnection		$db,
		IAppConfig			$config,
		protected LogRCDevs $logRCDevs,
	) {
		parent::__construct($db, $config, $logRCDevs);
	}

	/**
	 * Initialize all records Mutex according to the input parameters
	 * 
	 * @param string|null $mutex 
	 * @param string $transactionId 
	 * @param string|null $recipient 
	 * @return CDEM 
	 */
	public function updateMutex(
		string|null	$mutex,
		string		$transactionId,
		string|null	$recipient = null
	): CDEM {

		$cdem = new CDEM();

		try {
			/** @var IResult $updatedRecords */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set(CstTransaction::MUTEX, $queryBuilder->createParameter(CstTransaction::MUTEX))
				->setParameter(CstTransaction::MUTEX, $mutex)
			;

			$this->whereIfExistsTransaction($transactionId, $queryBuilder);
			$this->whereMutex(null, $queryBuilder);

			if (!is_null($recipient)) {
				$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
			}

			$updatedRecords = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::QRY_UPDATED_ROWS => $updatedRecords,
				],
				message: sprintf('Mutex initialized: %d', $updatedRecords),
				logLevel:CDEMLogLevel::DEBUG,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Reset all records Mutex according to the input parameters
	 * 
	 * @param string|null $mutex 
	 * @param string $transactionId 
	 * @param string|null $recipient 
	 * @return CDEM 
	 */
	public function resetMutex(
		string|null	$mutex,
		string		$transactionId,
		string|null	$recipient = null
	): CDEM {

		$cdem = new CDEM();

		try {
			/** @var IResult $updatedRecords */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set(CstTransaction::MUTEX, $queryBuilder->createParameter(CstTransaction::MUTEX))
				->setParameter(CstTransaction::MUTEX, null)

				->where($queryBuilder->expr()->eq(CstTransaction::TRANSACTION_ID, $queryBuilder->createNamedParameter($transactionId)))
				->andWhere($queryBuilder->expr()->eq(CstTransaction::MUTEX, $queryBuilder->createNamedParameter($mutex)));

			if (!is_null($recipient)) {
				$queryBuilder->andWhere($queryBuilder->expr()->eq(CstTransaction::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
			}

			$updatedRecords = $queryBuilder->executeStatement();

			$cdem->setOk(
				data: [
					CstDatabase::QRY_UPDATED_ROWS => $updatedRecords,
				],
				message: sprintf('Mutex reset: %d', $updatedRecords),
				logLevel:CDEMLogLevel::DEBUG,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}
}
