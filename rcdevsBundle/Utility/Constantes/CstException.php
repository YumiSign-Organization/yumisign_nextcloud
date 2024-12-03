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

namespace OCA\RCDevs\Utility\Constantes;

class CstException
{
	// public const EXCEPTION				= 'Exception';
	public const ARRAY_NULL					= 'ArrayNullException';
	public const CONNECTION_ERROR			= 'ConnectionErrorException';
	public const DELETE_PROCESS				= 'DeleteProcessException';
	public const FILE_CREATION				= 'FileCreationException';
	public const INTERNAL_SERVER_ERROR		= 'InternalServerErrorException';
	public const INVALID_RECIPIENT_TYPE		= 'InvalidRecipientTypeException';
	public const INVALID_SERVER_RESPONSE	= 'InvalidServerResponseException';
	public const INVALID_USER_ID			= 'InvalidUserIdException';
	public const MISSING_KEY				= 'MissingKeyException';
	public const NO_RECIPIENT				= 'NoRecipientException';
	public const NOT_SERVERS_ARRAY			= 'NotServersArrayException';
	public const POST_DATA_ARRAY			= 'PostDataArrayException';
	public const QUERY_TRANSACTION			= 'QueryTransactionException';
	public const RECIPIENTS_EMAILS			= 'RecipientsEmailsException';
	public const SIGN_PROCESS				= 'SignProcessException';
	public const SIGN_TYPES_DISABLED		= 'SignTypesDisabledException';
	public const TOKEN_RETRIEVAL			= 'TokenRetrievalException';
	public const TYPE_NOT_FILE				= 'TypeNotFileException';
}
