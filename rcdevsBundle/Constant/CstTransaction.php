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

namespace OCA\YumiSignNxtC\RCDevs\Constant;

/**
 * Constants for Signatures Transactions fields stored in Sessions table database
 * 
 * @package OCA\YumiSignNxtC\RCDevs\Constant
 */
class CstTransaction
{
	public const ADVANCED					= 'advanced';
	public const APPLICANT_ID				= 'applicant_id';
	public const APPLICANTID				= 'applicantId';
	public const CHANGE_STATUS				= 'change_status';
	public const CHANGESTATUS				= 'changeStatus';
	public const CREATED					= 'created';
	public const EXPIRY_DATE				= 'expiry_date';
	public const EXPIRYDATE					= 'expiryDate';
	public const FILE_ID					= 'file_id';
	public const FILE_PATH					= 'file_path';
	public const FILEID						= 'fileId';
	public const FILEPATH					= 'filePath';
	public const GLOBAL_STATUS				= 'global_status';
	public const GLOBALSTATUS				= 'globalStatus';
	public const ID							= 'id';
	public const MSG_DATE					= 'msg_date';
	public const MSGDATE					= 'msgDate';
	public const MUTEX						= 'mutex';
	public const OVERWRITE					= 'overwrite';
	public const RECIPIENT					= 'recipient';
	public const TRANSACTION_ID				= 'transaction_id';
}
