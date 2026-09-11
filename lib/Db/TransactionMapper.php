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

namespace OCA\YumiSignNxtC\Db;

// RCDevs App
use OCA\YumiSignNxtC\Constant\CstDatabase;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstStatus;
use OCA\YumiSignNxtC\Constant\CstTransaction;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Db\SignSession;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Db\TransactionMapper as RCDevsTransactionMapper;
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

class TransactionMapper extends RCDevsTransactionMapper
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

		// Force YMS entity mapping for DB rows (workspaceId, status, etc.)
		$this->entityClass = SignSession::class;
	}

	public function findActiveTransaction(
		string $envelopeId,
		string $recipient = ''
	): mixed {
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
			->where($queryBuilder->expr()->eq(CstEntity::ENVELOPE_ID, $queryBuilder->createNamedParameter($envelopeId)))
			->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::APPROVED)),
					$queryBuilder->expr()->eq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::STARTED)),
				)
			);

		if ($recipient !== '') {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
		}

		return $this->findEntity($queryBuilder);
	}

	public function findAllActiveEnvelopesIds(
		?string $applicantId = null,
		?int $limit = null
	): array {
		$queryBuilder = $this->db->getQueryBuilder();

		$queryBuilder->select(CstEntity::ID, CstEntity::APPLICANT_ID, CstEntity::FILE_PATH, CstEntity::WORKSPACE_ID, CstEntity::ENVELOPE_ID)
			->from($this->getTableName())
			->setMaxResults($limit)
			->orderBy(CstEntity::CHANGE_STATUS, 'desc')
			->addOrderBy(CstEntity::CREATED, 'desc')
			->where($queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::ARCHIVED)))
			->andWhere($queryBuilder->expr()->neq(CstEntity::GLOBAL_STATUS, $queryBuilder->createNamedParameter(CstStatus::NOT_APPLICABLE)));

		if (!is_null($applicantId)) {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($applicantId)));
		}

		return $this->findEntities($queryBuilder);
	}

	public function getUserIdByEnvelopeId(
		string $envelopeId
	): ?string {
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder->select(CstEntity::APPLICANT_ID)
			->from($this->getTableName())
			->where($queryBuilder->expr()->eq(CstEntity::ENVELOPE_ID, $queryBuilder->createNamedParameter($envelopeId)))
			->setMaxResults(1);

		$result = $queryBuilder->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!is_array($row) || !isset($row[CstEntity::APPLICANT_ID])) {
			return null;
		}

		$userId = trim((string) $row[CstEntity::APPLICANT_ID]);
		return $userId === '' ? null : $userId;
	}

	public function findTransactionsActiveByTransaction(
		string $transactionId,
		string $transactionColumnName,
		string $recipient = ''
	): mixed {
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

		$this->whereGlobalStatusPending($queryBuilder);

		if ($recipient !== '') {
			$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($recipient)));
		}

		return $this->findEntity($queryBuilder);
	}

	public function updateTransactionsStatus(
		array $transactionsToUpdate
	): int {
		$realTransactionUpdated = 0;

		try {
			foreach ($transactionsToUpdate as $unitTransactionToUpdate) {
				$queryBuilder = $this->db->getQueryBuilder();
				$queryBuilder->update($this->configurationService->getAppTableNameSessions())
					->set(CstEntity::CHANGE_STATUS, $queryBuilder->createParameter(CstEntity::CHANGE_STATUS))
					->setParameter(CstEntity::CHANGE_STATUS, time())
					->where($queryBuilder->expr()->eq(CstEntity::ENVELOPE_ID, $queryBuilder->createNamedParameter($unitTransactionToUpdate[CstEntity::ENVELOPE_ID])));

				if (array_key_exists(CstEntity::STATUS, $unitTransactionToUpdate) && !is_null($unitTransactionToUpdate[CstEntity::STATUS])) {
					if (is_string($unitTransactionToUpdate[CstEntity::STATUS])) {
						$unitTransactionToUpdate[CstEntity::STATUS] = strtolower(trim($unitTransactionToUpdate[CstEntity::STATUS]));
					}
					$queryBuilder->set(CstEntity::STATUS, $queryBuilder->createParameter(CstEntity::STATUS))
						->setParameter(CstEntity::STATUS, $unitTransactionToUpdate[CstEntity::STATUS]);
				}

				if (array_key_exists(CstEntity::GLOBAL_STATUS, $unitTransactionToUpdate) && !is_null($unitTransactionToUpdate[CstEntity::GLOBAL_STATUS])) {
					if (is_string($unitTransactionToUpdate[CstEntity::GLOBAL_STATUS])) {
						$unitTransactionToUpdate[CstEntity::GLOBAL_STATUS] = strtolower(trim($unitTransactionToUpdate[CstEntity::GLOBAL_STATUS]));
					}
					$queryBuilder->set(CstEntity::GLOBAL_STATUS, $queryBuilder->createParameter(CstEntity::GLOBAL_STATUS))
						->setParameter(CstEntity::GLOBAL_STATUS, $unitTransactionToUpdate[CstEntity::GLOBAL_STATUS]);
				}

				if (array_key_exists(CstEntity::APPLICANT_ID, $unitTransactionToUpdate) && !is_null($unitTransactionToUpdate[CstEntity::APPLICANT_ID])) {
					$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::APPLICANT_ID, $queryBuilder->createNamedParameter($unitTransactionToUpdate[CstEntity::APPLICANT_ID])));
				}

				if (array_key_exists(CstEntity::RECIPIENT, $unitTransactionToUpdate) && !is_null($unitTransactionToUpdate[CstEntity::RECIPIENT])) {
					$queryBuilder->andWhere($queryBuilder->expr()->eq(CstEntity::RECIPIENT, $queryBuilder->createNamedParameter($unitTransactionToUpdate[CstEntity::RECIPIENT])));
				}

				$queryBuilder->executeStatement();
				$realTransactionUpdated++;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf('Exception during updating status with message: %s', $th->getMessage()), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			throw $th;
		}

		return $realTransactionUpdated;
	}

	public function updateTransactionsStatusExpired(): void
	{
		try {
			$queryBuilder = $this->db->getQueryBuilder();
			$queryBuilder->update($this->configurationService->getAppTableNameSessions())
				->set(CstEntity::STATUS, $queryBuilder->createParameter(CstEntity::STATUS))
				->setParameter(CstEntity::STATUS, CstStatus::EXPIRED)
				->set(CstEntity::GLOBAL_STATUS, $queryBuilder->createParameter(CstEntity::GLOBAL_STATUS))
				->setParameter(CstEntity::GLOBAL_STATUS, CstStatus::EXPIRED)
				->set(CstEntity::CHANGE_STATUS, $queryBuilder->createParameter(CstEntity::CHANGE_STATUS))
				->setParameter(CstEntity::CHANGE_STATUS, time())
				->where($queryBuilder->expr()->lt(CstEntity::EXPIRY_DATE, $queryBuilder->createNamedParameter(intval(time()))));

			$this->whereGlobalStatusPending($queryBuilder);

			$queryBuilder->executeStatement();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf('Exception during updating status with message: %s', $th->getMessage()), $this->logRCDevs->format(__FUNCTION__, __CLASS__, __FILE__, __LINE__));
			throw $th;
		}
	}
}
