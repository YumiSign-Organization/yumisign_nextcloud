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

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class SignSession extends Entity implements JsonSerializable
{

    protected $applicantId;
    protected $changeStatus;
    protected $created;
    protected $envelopeId;
    protected $expiryDate;
    protected $fileId;
    protected $filePath;
    protected $globalStatus;
    protected $msgDate;
    protected $recipient;
    protected $secret;
    protected $status;
    protected $workflowId;
    protected $workflowName;
    protected $workspaceId;

    public function __construct()
    {
        // Define class Properties Types
        $this->addType('id', 'integer');
    }

    public function jsonSerialize()
    {
        return [
            'applicant_id'  => $this->applicantId,
            'change_status' => $this->changeStatus,
            'created'       => $this->created,
            'envelope_id'   => $this->envelopeId,
            'expiry_date'   => $this->expiryDate,
            'file_id'       => $this->fileId,
            'file_path'     => $this->filePath,
            'global_status' => $this->globalStatus,
            'id'            => $this->id,
            'msg_date'      => $this->msgDate,
            'recipient'     => $this->recipient,
            'secret'        => $this->secret,
            'status'        => $this->status,
            'workflow_id'   => $this->workflowId,
            'workflow_name' => $this->workflowName,
            'workspace_id'  => $this->workspaceId,
        ];
    }
}
