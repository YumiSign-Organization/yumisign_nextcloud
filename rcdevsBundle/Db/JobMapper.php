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

use Exception;
use OCA\YumiSignNxtC\RCDevs\Constant\CstJob;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use PDO;
use Throwable;

class JobMapper extends CommonMapper
{
	public function __construct(
		IDBConnection		$db,
		IAppConfig			$config,
		protected LogRCDevs $logRCDevs,
	) {
		parent::__construct($db, $config, $logRCDevs);
	}

	/**
	 * Find in DB the row corresponding to the Check Cron
	 *
	 * @return array	With this format: [reserved_at => value1, last_run => value2] where values can be 0 if no cron found
	 * @throws Throwable
	 * @throws DBException
	 * @throws RuntimeException
	 */
	public function findLastRun(): CDEM
	{
		$cdem = new CDEM();

		try {
			/** @var IResult $result */
			/** @var IQueryBuilder $queryBuilder */

			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->select(CstJob::RESERVED_AT, CstJob::LAST_RUN)
				->from(CstJob::_JOBS)
				->where($queryBuilder->expr()->eq(CstJob::CCLASS, $queryBuilder->createNamedParameter($this->configurationService->getCheckCron())));

			$result = $queryBuilder->executeQuery();
			$row = $result->fetch(PDO::FETCH_ASSOC);
			$result->closeCursor();

			/**
			 * Not row in database
			 * Not an error, the cron just has never run
			 */
			if (!$row) {
				$row = [
					CstJob::RESERVED_AT	=> 0,
					CstJob::LAST_RUN	=> 0,
				];
			}
			$cdem->setOk(
				data: $row,
			);
		} catch (\Throwable $th) {
			$cdem->setError($th, $this->logRCDevs);
		}

		return $cdem;
	}

	/**
	 * Reset Cron job by updating field "reserved_at" in jobs DB Table
	 * 
	 * @return int 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function reset(): int
	{
		try {
			$queryBuilder = $this->db->getQueryBuilder();

			$queryBuilder->update(CstJob::_JOBS)
				->set(CstJob::RESERVED_AT, $queryBuilder->createParameter(CstJob::RESERVED_AT))
				->setParameter(CstJob::RESERVED_AT, 0)
				->where($queryBuilder->expr()->like(CstJob::CCLASS, $queryBuilder->createNamedParameter(
					'%' . $this->db->escapeLikeParameter("\\{$this->configurationService->getAppNamespace()}") . '%'
				)));

			return $queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(
				$th->getMessage(),
				$this->logRCDevs->format(exceptionFunction: __FUNCTION__, exceptionClass: __CLASS__, exceptionFile: __FILE__, exceptionLine: __LINE__),
				throw: true,
			);
			return -1;	// But this value will never be returned because the log must throw an exception.
		}
	}
}
