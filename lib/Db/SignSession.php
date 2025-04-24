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

// use JsonSerializable;

use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCP\AppFramework\Db\Entity;

class SignSession extends Entity
{
	// Common Sign Apps fields
	protected	$advanced;
	protected	$applicantId;
	protected	$changeStatus;
	protected	$created;
	protected	$expiryDate;
	protected	$fileId;
	protected	$filePath;
	protected	$globalStatus;
	protected	$msgDate;
	protected	$mutex;
	protected	$overwrite;
	protected	$qualified;
	protected	$recipient;
	protected	$recipientId;
	
	// Custom curent App fields
	protected	$envelopeId;
	protected	$secret;
	protected	$status;
	protected	$workflowId;
	protected	$workflowName;
	protected	$workspaceId;

	public function __construct()
	{
		$this->addType('advanced',		CstEntity::INTEGER);
		$this->addType('applicantId',	CstEntity::STRING);
		$this->addType('created',		CstEntity::INTEGER);
		$this->addType('expiryDate',	CstEntity::INTEGER);
		$this->addType('fileId',		CstEntity::INTEGER);
		$this->addType('filePath',		CstEntity::STRING);
		$this->addType('globalStatus',	CstEntity::STRING);
		$this->addType('id',			CstEntity::INTEGER);
		$this->addType('msgDate',		CstEntity::INTEGER);
		$this->addType('mutex',			CstEntity::STRING);
		$this->addType('overwrite',		CstEntity::INTEGER);
		$this->addType('qualified',		CstEntity::INTEGER);
		$this->addType('recipient',		CstEntity::STRING);
		$this->addType('recipientId',	CstEntity::STRING);
		
		$this->addType('changeStatus',	CstEntity::INTEGER);
		$this->addType('envelopeId',	CstEntity::STRING);
		$this->addType('secret',		CstEntity::STRING);
		$this->addType('status',		CstEntity::STRING);
		$this->addType('workflowId',	CstEntity::INTEGER);
		$this->addType('workflowName',	CstEntity::STRING);
		$this->addType('workspaceId',	CstEntity::INTEGER);
	}
}
