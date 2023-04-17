<?php

namespace OCA\YumiSignNxtC\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class SignSessionMapper extends QBMapper
{

    private IDBConnection $dbJobs;

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'yumisign_nxtc_sess', SignSession::class);
        $this->db = $db;
    }

    public function countIssuesByApplicant(string $applicantId)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('applicant_id', $qb->createNamedParameter($applicantId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_DECLINED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_CANCELED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_EXPIRED)),
                )
            );

        $result = $qb->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function countIssuesByEnvelopeId(string $envelopeId)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_DECLINED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_CANCELED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_EXPIRED)),
                )
            );

        $result = $qb->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function countPendingsByApplicant(string $applicantId)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('applicant_id', $qb->createNamedParameter($applicantId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_APPROVED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_STARTED)),
                )
            );

        $result = $qb->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function countTransactionsByEnvelopeId(string $envelopeId)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

        $result = $qb->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return $count;
    }

    public function deleteTransactions(string $envelopeId, $recipient = '')
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('yumisign_nxtc_sess')
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

        if ($recipient !== '') $qb->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        $qb->executeStatement();
    }

    public function findActiveTransaction(string $envelopeId, string $recipient = '')
    {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct(
            ['applicant_id', 'workspace_id', 'workflow_id', 'envelope_id', 'global_status']
        )
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_APPROVED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_STARTED)),
                )
            );

        if ($recipient !== '') $qb->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        return $this->findEntity($qb);
    }

    public function findIssuesByApplicant(string $applicantId, int $page = 0, int $nbItems = 20)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('applicant_id', $qb->createNamedParameter($applicantId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_DECLINED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_CANCELED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_EXPIRED)),
                    $qb->expr()->andX(
                        $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_STARTED)),
                        $qb->expr()->lt('expiry_date',   $qb->createNamedParameter(time())),
                    ),
                    $qb->expr()->andX(
                        $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_NOT_STARTED)),
                        $qb->expr()->lt('expiry_date',   $qb->createNamedParameter(time())),
                    ),
                )
            )
            ->orderBy('change_status', 'desc')
            ->addOrderBy('created', 'desc');

        $qb->setFirstResult($page * $nbItems);
        $qb->setMaxResults($nbItems);

        return $this->findEntities($qb);
    }

    public function findJob()
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('jobs')
            ->where($qb->expr()->like('class', $qb->createNamedParameter(
                '%' . $this->db->escapeLikeParameter('\YumiSignNxtC\\') . '%'
                // '%\\YumiSignNxtC\\%'
            )));

        $cursor = $qb->executeQuery();
        $ymsJob = $cursor->fetch();
        $cursor->closeCursor();

        return $ymsJob;
    }

    public function findPendingsByApplicant(string $applicantId, int $page = 0, int $nbItems = 20)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id', 'applicant_id', 'file_path', 'workspace_id', 'workflow_id', 'workflow_name', 'envelope_id', 'status', 'expiry_date', 'created', 'change_status', 'recipient', 'global_status', 'msg_date')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('applicant_id', $qb->createNamedParameter($applicantId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_APPROVED)),
                    $qb->expr()->eq('global_status', $qb->createNamedParameter(YMS_STATUS_STARTED)),
                )
            )
            ->orderBy('change_status', 'desc')
            ->addOrderBy('created', 'desc');

        $qb->setFirstResult($page * $nbItems);
        $qb->setMaxResults($nbItems);

        return $this->findEntities($qb);
    }

    public function findRecipientTransaction(string $envelopeId, string $recipient)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id',  $qb->createNamedParameter($envelopeId)))
            ->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        return $this->findEntity($qb);
    }

    public function findTransaction(string $envelopeId, string $recipient = '')
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id',      $qb->createNamedParameter($envelopeId)))
            ->setMaxResults(1);

        if ($recipient !== '') $qb->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        return $this->findEntity($qb);
    }

    public function findTransactions(string $envelopeId = '')
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName());

        if ($envelopeId !== '') $qb->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

        return $this->findEntities($qb);
    }

    public function resetJob()
    {
        $qb = $this->db->getQueryBuilder();

        $qb->update('jobs')
            ->set('reserved_at', $qb->createParameter('reserved_at'))
            ->setParameter('reserved_at', 0)
            ->where($qb->expr()->like('class', $qb->createNamedParameter(
                '%' . $this->db->escapeLikeParameter('\YumiSignNxtC\\') . '%'
                // '%\\YumiSignNxtC\\%'
            )));

        $qb->executeStatement();
    }
}
