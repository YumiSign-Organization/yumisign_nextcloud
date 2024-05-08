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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Db;

use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCA\YumiSignNxtC\Service\Entity;
use OCA\YumiSignNxtC\Service\Status;
use OCA\YumiSignNxtC\Utility\Utility;
use OCA\YumiSignNxtC\Utility\LogYumiSign;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;

class SignSessionMapper extends QBMapper
{

    public int $maxItems = 50;
    public int $backInTime = 3600; // Use to find users' last activity and to reduce the number of retrieved transactions
    private string $tableAlias = 'ymsSess';
    private string $nxcToken = '';

    public function __construct(
        IDBConnection $db,
        private LogYumiSign $logYumiSign,
    ) {
        parent::__construct(
            $db,
            'yumisign_nxtc_sess',
            SignSession::class
        );
        $this->db = $db;
    }

    /**
     * Query completions
     */
    private function commonSetParameter(array|null $unitTransactionToUpdate, string $paramName, IQueryBuilder &$queryBuilder)
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

    private function setApplicantIdIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
    {
        $this->commonSetParameter($unitTransactionToUpdate, Constante::entity(Entity::APPLICANT_ID), $queryBuilder);
    }

    private function setEnvelopeIdIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
    {
        $this->commonSetParameter($unitTransactionToUpdate, Constante::entity(Entity::ENVELOPE_ID), $queryBuilder);
    }

    private function setStatusIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
    {
        $this->commonSetParameter($unitTransactionToUpdate, Constante::entity(Entity::STATUS), $queryBuilder);
    }

    private function setGlobalStatusIfExists(array|null $unitTransactionToUpdate, IQueryBuilder &$queryBuilder)
    {
        $this->commonSetParameter($unitTransactionToUpdate, Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder);
    }

    private function whereApplicantIfExists(string|null $applicantId, IQueryBuilder &$queryBuilder)
    {
        if (!is_null($applicantId)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId, IQueryBuilder::PARAM_STR)));
        }
    }

    private function whereRecipientIfExists(string|null $recipient, IQueryBuilder &$queryBuilder)
    {
        if (!is_null($recipient)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient, IQueryBuilder::PARAM_STR)));
        }
    }

    private function whereGlobalStatusActive(IQueryBuilder &$queryBuilder)
    {
        // AND condition
        $queryBuilder->andWhere(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->neq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::CANCELED))),
                $queryBuilder->expr()->neq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::NOT_APPLICABLE))),
                $queryBuilder->expr()->neq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::NOT_FOUND))),
                $queryBuilder->expr()->neq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::SIGNED))),
            )
        );
    }

    private function whereGlobalStatusPending(IQueryBuilder &$queryBuilder)
    {
        // OR condition
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->eq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::APPROVED))),
                $queryBuilder->expr()->eq(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createNamedParameter(Constante::status(Status::STARTED))),
            )
        );
    }

    private function whereExpiryDate(int $rightNow, IQueryBuilder &$queryBuilder)
    {
        $queryBuilder->andWhere($queryBuilder->expr()->gte(Constante::entity(Entity::EXPIRY_DATE), $queryBuilder->createNamedParameter(strval($rightNow), IQueryBuilder::PARAM_INT)));
    }

    private function joinActivity(IQueryBuilder &$queryBuilder)
    {
        $queryBuilder->join(
            $this->tableAlias,
            'authtoken',
            $this->nxcToken,
            'uid = applicant_id',
        );
    }

    private function whereLastActivity(int $rightNow, IQueryBuilder &$queryBuilder)
    {
        $queryBuilder->andWhere('last_activity >= :paramLastActivityBackInTime')
            ->setParameter('paramLastActivityBackInTime', intval($rightNow) - $this->backInTime, IQueryBuilder::PARAM_INT);
    }

    private function whereChangeStatus(int $rightNow, IQueryBuilder &$queryBuilder)
    {
        $queryBuilder->andWhere($queryBuilder->expr()->lt(Constante::entity(Entity::CHANGE_STATUS), $queryBuilder->createNamedParameter(strval($rightNow), IQueryBuilder::PARAM_INT)));
    }

    /**
     * DB Ops
     */

    public function countTransactions(int $rightNow, string $applicantId = null): array
    {
        $returned = [];

        try {
            /** @var IResult $result */
            /** @var IQueryBuilder $queryBuilder */

            $queryBuilder = $this->db->getQueryBuilder();

            $queryBuilder->selectAlias($queryBuilder->createFunction('COUNT(*)'), 'count')
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
                Constante::get(Cst::CODE)   => 1,
                Constante::get(Cst::DATA)   => $count,
                Constante::get(Cst::ERROR)  => null,
            ];
        } catch (\Throwable $th) {
            $returned = [
                Constante::get(Cst::CODE)   => 0,
                Constante::get(Cst::DATA)   => null,
                Constante::get(Cst::ERROR)  => $th->getMessage(),
            ];
            $this->logYumiSign->error("Query building failed : {$th->getMessage()}", __FUNCTION__);
        }

        return $returned;
    }

    public function countIssuesByApplicant(string $applicantId)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::DECLINED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::CANCELED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::EXPIRED))),
                )
            );

        $result = $queryBuilder->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function countIssuesByEnvelopeId(string $envelopeId)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)))
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::DECLINED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::CANCELED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::EXPIRED))),
                )
            );

        $result = $queryBuilder->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function countTransactionsByEnvelopeId(string $envelopeId)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select($queryBuilder->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)));

        $result = $queryBuilder->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function deleteTransactions(string $envelopeId, $recipient = ''): void
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->delete('yumisign_nxtc_sess')
            ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)));

        if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

        $queryBuilder->executeStatement();
    }

    public function deleteTransactionByApplicant(string $envelopeId, string $applicant)
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->delete('yumisign_nxtc_sess')
            ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)))
            ->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicant)));

        $queryBuilder->executeStatement();
    }

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
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::APPROVED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::STARTED))),
                )
            );

        if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

        return $this->findEntity($queryBuilder);
    }

    public function findIssuesByApplicant(string $applicantId, int $page = 0, int $nbItems = 20)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)))
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::DECLINED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::CANCELED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::EXPIRED))),
                    $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::NOT_APPLICABLE))),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::STARTED))),
                        $queryBuilder->expr()->lt('expiry_date',   $queryBuilder->createNamedParameter(time())),
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::NOT_STARTED))),
                        $queryBuilder->expr()->lt('expiry_date',   $queryBuilder->createNamedParameter(time())),
                    ),
                )
            )
            ->orderBy('change_status', 'desc')
            ->addOrderBy('created', 'desc');

        $queryBuilder->setFirstResult($page * $nbItems);
        $queryBuilder->setMaxResults($nbItems);

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
                'reserved_at'   => 0,
                'last_run'      => 0,
            ];
        }
        $cursor->closeCursor();

        return $ymsJob;
    }

    public function findAll(int $page = -1, int $nbItems = -1): array
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('id', 'applicant_id', 'file_path', 'workspace_id', 'workflow_id', 'workflow_name', 'envelope_id', 'status', 'expiry_date', 'created', 'change_status', 'recipient', 'global_status', 'msg_date', 'file_id')
            ->from($this->getTableName())
            ->orderBy('change_status', 'desc')
            ->addOrderBy('created', 'desc');

        if ($page !== -1 && $nbItems !== -1) {
            $queryBuilder->setFirstResult($page * $nbItems);
            $queryBuilder->setMaxResults($nbItems);
        }

        return $this->findEntities($queryBuilder);
    }

    public function findAllEnvelopesIds(int $rightNow, string $applicantId = null, int $page = -1, int $nbItems = -1): array
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('applicant_id', 'envelope_id')
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

    public function findAllActiveEnvelopesIds(string $applicantId = null, int $limit = null)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('id', 'applicant_id', 'file_path', 'workspace_id', 'envelope_id')
            ->from($this->getTableName())
            ->setMaxResults($limit)
            ->orderBy('change_status', 'desc')
            ->addOrderBy('created', 'desc')
            ->where(
                $queryBuilder->expr()->neq('global_status', $queryBuilder->createNamedParameter(Constante::get(Cst::YMS_ARCHIVED)))
            )
            ->andWhere($queryBuilder->expr()->neq('global_status', $queryBuilder->createNamedParameter(Constante::status(Status::NOT_APPLICABLE))));

        if (!is_null($applicantId)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicantId)));
        }

        return $this->findEntities($queryBuilder);
    }

    public function findPendingsByApplicant(int $rightNow, string $applicantId, int $page = 0, int $nbItems = 20)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('id', 'applicant_id', 'file_path', 'workspace_id', 'workflow_id', 'workflow_name', 'envelope_id', 'status', 'expiry_date', 'created', 'change_status', 'recipient', 'global_status', 'msg_date', 'file_id')
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

    public function findRecipientTransaction(string $envelopeId, string $recipient)
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('envelope_id',  $queryBuilder->createNamedParameter($envelopeId)))
            ->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

        return $this->findEntity($queryBuilder);
    }

    public function findTransaction(string $envelopeId, string $recipient = '')
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('envelope_id',      $queryBuilder->createNamedParameter($envelopeId)))
            ->setMaxResults(1);

        if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq('recipient', $queryBuilder->createNamedParameter($recipient)));

        return $this->findEntity($queryBuilder);
    }

    public function findTransactions(string $envelopeId = '', string $applicant = ''): array
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->getTableName());

        $whereCommand = 'where';

        if ($envelopeId !== '') {
            $queryBuilder->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)));
            $whereCommand = 'andWhere';
        }
        if ($applicant !== '') {
            $queryBuilder->$whereCommand($queryBuilder->expr()->eq('applicant_id', $queryBuilder->createNamedParameter($applicant)));
        }

        return $this->findEntities($queryBuilder);
    }

    public function resetJob()
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->update('jobs')
            ->set('reserved_at', $queryBuilder->createParameter('reserved_at'))
            ->setParameter('reserved_at', 0)
            ->where($queryBuilder->expr()->like('class', $queryBuilder->createNamedParameter(
                '%' . $this->db->escapeLikeParameter('\YumiSignNxtC\\') . '%'
            )));

        $queryBuilder->executeStatement();
    }

    /**
     * UPDATES
     */
    public function updateTransactionAllStatuses(string $envelopeId, string $newStatus)
    {
        try {
            $queryBuilder = $this->db->getQueryBuilder();
            $queryBuilder->update('yumisign_nxtc_sess')
                ->set('global_status', $queryBuilder->createParameter('global_status'))
                ->setParameter('global_status', $newStatus)
                ->set('status', $queryBuilder->createParameter('status'))
                ->setParameter('status', $newStatus)
                ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)));
            $queryBuilder->executeStatement();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updateTransactionStatus(string $envelopeId, string $newStatus)
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->update('yumisign_nxtc_sess')
            ->set('status', $queryBuilder->createParameter('status'))
            ->setParameter('status', $newStatus)
            ->where($queryBuilder->expr()->eq('envelope_id', $queryBuilder->createNamedParameter($envelopeId)));
        $queryBuilder->executeStatement();
    }

    public function updateTransactionsStatus(array $transactionsToUpdate): int
    {
        $realTransactionUpdated = 0;
        try {
            foreach ($transactionsToUpdate as $unitTransactionToUpdate) {
                $queryBuilder = $this->db->getQueryBuilder();
                $queryBuilder->update('yumisign_nxtc_sess')
                    ->set(Constante::entity(Entity::CHANGE_STATUS), $queryBuilder->createParameter(Constante::entity(Entity::CHANGE_STATUS)))
                    ->setParameter(Constante::entity(Entity::CHANGE_STATUS), time())

                    ->where($queryBuilder->expr()->eq(Constante::entity(Entity::ENVELOPE_ID), $queryBuilder->createNamedParameter($unitTransactionToUpdate[Constante::entity(Entity::ENVELOPE_ID)])));

                // Add set parameters
                $this->setStatusIfExists($unitTransactionToUpdate, $queryBuilder);
                $this->setGlobalStatusIfExists($unitTransactionToUpdate, $queryBuilder);


                $this->whereApplicantIfExists(Utility::getIfExists(Constante::entity(Entity::APPLICANT_ID), $unitTransactionToUpdate), $queryBuilder);

                $this->whereRecipientIfExists(Utility::getIfExists(Constante::entity(Entity::RECIPIENT), $unitTransactionToUpdate), $queryBuilder);

                $queryBuilder->executeStatement();
                $realTransactionUpdated++;
            }
        } catch (\Throwable $th) {
            $this->logYumiSign->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . DIRECTORY_SEPARATOR . __LINE__);

            throw $th;
        }

        return $realTransactionUpdated;
    }

    public function updateTransactionsStatusExpired(): void
    {
        try {
            $queryBuilder = $this->db->getQueryBuilder();
            $queryBuilder->update('yumisign_nxtc_sess')
                // status
                ->set(Constante::entity(Entity::STATUS), $queryBuilder->createParameter(Constante::entity(Entity::STATUS)))
                ->setParameter(Constante::entity(Entity::STATUS), Constante::status(Status::EXPIRED))
                // global status
                ->set(Constante::entity(Entity::GLOBAL_STATUS), $queryBuilder->createParameter(Constante::entity(Entity::GLOBAL_STATUS)))
                ->setParameter(Constante::entity(Entity::GLOBAL_STATUS), Constante::status(Status::EXPIRED))
                // change_status
                ->set(Constante::entity(Entity::CHANGE_STATUS), $queryBuilder->createParameter(Constante::entity(Entity::CHANGE_STATUS)))
                ->setParameter(Constante::entity(Entity::CHANGE_STATUS), time())

                ->where($queryBuilder->expr()->lt(Constante::entity(Entity::EXPIRY_DATE), $queryBuilder->createNamedParameter(intval(time()))));

            $queryBuilder->executeStatement();
        } catch (\Throwable $th) {
            $this->logYumiSign->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . DIRECTORY_SEPARATOR . __LINE__);

            throw $th;
        }
    }

    public function updateTransactionsMutex(string $threadId, string $envelopeId, string $recipient = ''): void
    {
        // Not needed to use transactional query
        try {
            $queryBuilder = $this->db->getQueryBuilder();
            $queryBuilder->select('*')
                ->from($this->getTableName())
                ->where($queryBuilder->expr()->eq(Constante::entity(Entity::ENVELOPE_ID), $queryBuilder->createNamedParameter($envelopeId)))
                ->andWhere($queryBuilder->expr()->eq(Constante::entity(Entity::MUTEX), $queryBuilder->createNamedParameter('')));

            if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(Constante::entity(Entity::RECIPIENT), $queryBuilder->createNamedParameter($recipient)));

            $resp = $this->findEntities($queryBuilder);

            $queryBuilder = $this->db->getQueryBuilder();
            $queryBuilder->update('yumisign_nxtc_sess')
                // mutex
                ->set(Constante::entity(Entity::MUTEX), $queryBuilder->createParameter(Constante::entity(Entity::MUTEX)))
                ->setParameter(Constante::entity(Entity::MUTEX), $threadId)

                ->where($queryBuilder->expr()->eq(Constante::entity(Entity::ENVELOPE_ID), $queryBuilder->createNamedParameter($envelopeId)))
                ->andWhere($queryBuilder->expr()->eq(Constante::entity(Entity::MUTEX), $queryBuilder->createNamedParameter('')));

            if ($recipient !== '') $queryBuilder->andWhere($queryBuilder->expr()->eq(Constante::entity(Entity::RECIPIENT), $queryBuilder->createNamedParameter($recipient)));

            $resp = $queryBuilder->executeStatement();

            $this->logYumiSign->debug(sprintf('UPD response : %d', $resp), __FUNCTION__);
        } catch (\Throwable $th) {
            $this->logYumiSign->error(sprintf("Exception during updating status with message: %s", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . DIRECTORY_SEPARATOR . __LINE__);

            throw $th;
        }
    }
}
