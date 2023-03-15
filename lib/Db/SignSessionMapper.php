<?php

namespace OCA\YumiSignNxtC\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class SignSessionMapper extends QBMapper
{

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'yumisign_nxtc_sess', SignSession::class);
    }

    // public function findDocumentSession(string $envelopeId, string $name)
    // {
    //     $qb = $this->db->getQueryBuilder();

    //     $qb->select('*')
    //         ->from($this->getTableName())
    //         ->where($qb->expr()->eq('workflow_name',    $qb->createNamedParameter($name)))
    //         ->andWhere($qb->expr()->eq('envelope_id',   $qb->createNamedParameter($envelopeId)));

    //     return $this->findEntities($qb);
    // }

    // public function findSignedTransactions(string $envelopeId)
    // {
    //     return $this->findTransactionsWithStatus($envelopeId, YMS_SIGNED);
    // }

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

    public function findTransactions(string $envelopeId)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

        return $this->findEntities($qb);
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

    // public function findTransactionsWithStatus(string $envelopeId, string $status)
    // {
    //     $qb = $this->db->getQueryBuilder();

    //     $qb->select('*')
    //         ->from($this->getTableName())
    //         ->where($qb->expr()->eq('envelope_id',      $qb->createNamedParameter($envelopeId)))
    //         ->andWhere($qb->expr()->eq('status',        $qb->createNamedParameter($status)));

    //     return $this->findEntities($qb);
    // }

    // public function findMiscTransactions(string $envelopeId)
    // {
    //     $qb = $this->db->getQueryBuilder();

    //     $qb->select('*')
    //         ->from($this->getTableName())
    //         ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

    //     return $this->findEntities($qb);
    // }

    public function findRecipientTransaction(string $envelopeId, string $recipient)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('envelope_id',  $qb->createNamedParameter($envelopeId)))
            ->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        return $this->findEntity($qb);
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

    public function findPendingsByApplicant(string $applicantId, int $page = 0, int $nbItems = 20)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
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

    public function deleteTransactions(string $envelopeId, $recipient)
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('yumisign_nxtc_sess')
            ->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($envelopeId)));

        if ($recipient !== '') $qb->andWhere($qb->expr()->eq('recipient', $qb->createNamedParameter($recipient)));

        $qb->executeStatement();
    }
}
