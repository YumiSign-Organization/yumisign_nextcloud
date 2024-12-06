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

namespace OCA\RCDevs\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class SignSession extends Entity
{
	protected int		$changeStatus;
	protected int		$created;
	protected int		$expiryDate;
	protected int		$fileId;
	protected int		$msgDate;
	protected string	$applicantId;
	protected string	$filePath;
	protected string	$globalStatus;
	protected string	$mutex;
	protected string	$recipient;

	public function __construct()
	{
		// Define class Properties Types
		$this->addType('id',			'integer');
		$this->addType('changeStatus',	'integer');
		$this->addType('created',		'integer');
		$this->addType('expiryDate',	'integer');
		$this->addType('fileId',		'integer');
		$this->addType('msgDate',		'integer');
		$this->addType('applicantId',	'string');
		$this->addType('filePath',		'string');
		$this->addType('globalStatus',	'string');
		$this->addType('mutex',			'string');
		$this->addType('recipient',		'string');
	}

	// public function commonJsonSerialize(): array
	// {
	//	return [
	//		'applicant_id'	=> $this->applicantId,
	//		'change_status'	=> $this->changeStatus,
	//		'created'		=> $this->created,
	//		'expiry_date'	=> $this->expiryDate,
	//		'file_id'		=> $this->fileId,
	//		'file_path'		=> $this->filePath,
	//		'global_status'	=> $this->globalStatus,
	//		'id'			=> $this->id,
	//		'msg_date'		=> $this->msgDate,
	//		'mutex'			=> $this->mutex,
	//		'recipient'		=> $this->recipient,
	//	];
	// }
}
