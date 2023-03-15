<?php

namespace OCA\YumiSignNxtC\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class SignSession extends Entity implements JsonSerializable
{

    protected $applicantId;
    protected $filePath;
    protected $workspaceId;
    protected $workflowId;
    protected $workflowName;
    protected $envelopeId;
    protected $secret;
    protected $expiryDate;
    protected $status;
    protected $created;
    protected $changeStatus;
    protected $recipient;
    protected $globalStatus;
    protected $msgDate;

    public function __construct()
    {
        // Define class Properties Types
        $this->addType('id', 'integer');
    }

    public function jsonSerialize()
    {
        return [
            'id'            => $this->id,
            'applicant_id'  => $this->applicantId,
            'file_path'     => $this->filePath,
            'workspace_id'  => $this->workspaceId,
            'workflow_id'   => $this->workflowId,
            'workflow_name' => $this->workflowName,
            'envelope_id'   => $this->envelopeId,
            'secret'        => $this->secret,
            'expiry_date'   => $this->expiryDate,
            'status'        => $this->status,
            // Update DB with Migration process
            'created'       => $this->created,
            'change_status' => $this->changeStatus,
            'recipient'     => $this->recipient,
            // Update DB with Migration process
            'global_status'     => $this->globalStatus,
            // Update DB with Migration process
            'msg_date'     => $this->msgDate,
        ];
    }
}
