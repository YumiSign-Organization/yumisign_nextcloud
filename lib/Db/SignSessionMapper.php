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

namespace OCA\YumiSignNxtC\Db;

use OCA\RCDevs\Db\SignSessionMapper as RCDevsSignSessionMapper;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCA\YumiSignNxtC\Utility\Constantes\CstStatus;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

class SignSessionMapper extends RCDevsSignSessionMapper
{
	private		ConfigurationService	$configurationService;
	protected	string					$nxcToken = '';
	protected	string					$tableAlias = 'rcdevsSess';
	public		int						$backInTime = 3600; // Use to find users' last activity and to reduce the number of retrieved transactions
	public		int						$maxItems = 50;

	public function __construct(
		IDBConnection		$db,
		IConfig				$config,
		protected LogRCDevs $logRCDevs,
	) {
		$this->configurationService = new ConfigurationService($config);

		parent::__construct(
			$db,
			$config,
			$logRCDevs,
		);
		$this->db = $db;
	}

	/**
	 * Query completions
	 */
	protected function setEnvelopeIdIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
	{
		$this->commonSetParameter($unitTransactionToUpdate, CstEntity::ENVELOPE_ID, $queryBuilder);
	}

	protected function setStatusIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
	{
		$this->commonSetParameter($unitTransactionToUpdate, CstEntity::STATUS, $queryBuilder);
	}

	// protected function whereGlobalStatusPending(IQueryBuilder &$queryBuilder)
	// {
	// 	// OR condition
	// 	$queryBuilder->andWhere(
	// 		$queryBuilder->expr()->orX(
	// 			$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::APPROVED)),
	// 			$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::STARTED)),
	// 		)
	// 	);
	// }

	/**
	 * DB Ops
	 */

	public function findActiveTransaction(string $envelopeId, string $recipient = '')
	{
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->selectDistinct(
			['applicant_id', 'workspace_id', 'workflow_id', 'envelope_id', 'global_status']
		)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::APPROVED)),
					$queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(CstStatus::STARTED)),
				)
			);

		if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

		return $this->findEntity($queryBuilder);
	}

	public function findAllActiveEnvelopesIds(string $applicantId = null, int $limit = null)
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select('id', 'applicant_id', 'file_path', 'workspace_id', 'envelope_id')
			->from($this->getTableName())
			->setMaxResults($limit)
			->orderBy('change_status', 'desc')
			->addOrderBy('created', 'desc')
			->where(
				$queryBuilder->expr()->neq('global_status', $queryBuilder->createNamedParameter(CstStatus::ARCHIVED))
			)
			->andWhere($queryBuilder->expr()->neq('global_status', $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)));

		if (!is_null($applicantId)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)));
		}

		return $this->findEntities($queryBuilder);
	}

	public function findTransactionsActiveByTransaction(string $transactionId, string $transactionColumnName, string $recipient = '')
	{
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->selectDistinct(
			[
				CstEntity::APPLICANT_ID,
				CstEntity::WORKSPACE_ID,
				CstEntity::WORKFLOW_ID,
				CstEntity::ENVELOPE_ID,
				CstEntity::GLOBAL_STATUS,
			]
		)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq($transactionColumnName, $queryBuilder->createNamedParameter($transactionId)))
			->setMaxResults(1);
		// Add more filters
		$this->whereGlobalStatusPending($queryBuilder);

		if (!empty($recipient)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));
		}

		return $this->findEntity($queryBuilder);
	}

	/**
	 * UPDATES
	 */
	public function updateTransactionsStatus(array $transactionsToUpdate): int
	{
		$realTransactionUpdated = 0;
		try {
			foreach ($transactionsToUpdate as $unitTransactionToUpdate) {
				$queryBuilder = $this->db->getQueryBuilder();
				$queryBuilder->update($this->configurationService->getAppTableNameSessions())
					->set(CstEntity::CHANGE_STATUS, $queryBuilder->createParameter(CstEntity::CHANGE_STATUS))
					->setParameter(CstEntity::CHANGE_STATUS, time())

					->where($queryBuilder->expr()->eq(CstEntity::ENVELOPE_ID, $queryBuilder->createNamedParameter($unitTransactionToUpdate[CstEntity::ENVELOPE_ID])));

				// Add set parameters
				$this->setStatusIfExists($unitTransactionToUpdate, $queryBuilder);
				$this->setGlobalStatusIfExists($unitTransactionToUpdate, $queryBuilder);


				$this->whereApplicantIfExists(Helpers::getIfExists(CstEntity::APPLICANT_ID, $unitTransactionToUpdate), $queryBuilder);

				$this->whereRecipientIfExists(Helpers::getIfExists(CstEntity::RECIPIENT, $unitTransactionToUpdate), $queryBuilder);

				$queryBuilder->executeStatement();
				$realTransactionUpdated++;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			throw $th;
		}

		return $realTransactionUpdated;
	}

	public function updateTransactionsStatusExpired(): void
	{
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				// status
				->set(CstEntity::STATUS, $queryBuilder->createParameter(CstEntity::STATUS))
				->setParameter(CstEntity::STATUS, CstStatus::EXPIRED)
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
}
