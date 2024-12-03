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

class CstEntity
{
	// Types used in migration scripts
	public const BIGINT						= 'bigint';
	public const INTEGER					= 'integer';
	public const SMALLINT					= 'smallint';
	public const STRING						= 'string';

	// DB table Columns name
	public const ADVANCED					= 'advanced';
	public const API_KEY					= 'api_key';
	public const APPLICANT_ID				= 'applicant_id';
	public const ASYNC_TIMEOUT				= 'async_timeout';
	public const CHANGE_STATUS				= 'change_status';
	public const CLIENT_ID					= 'client_id';
	public const CLIENT_SECRET				= 'client_secret';
	public const CREATED					= 'created';
	public const CRON_INTERVAL				= 'cron_interval';
	public const ENABLE_SIGN				= 'enable_sign';
	public const ENVELOPE_ID				= 'envelope_id';
	public const EXPIRY_DATE				= 'expiry_date';
	public const FILE_ID					= 'fileId';
	public const FILE_PATH					= 'file_path';
	public const GLOBAL_STATUS				= 'global_status';
	public const ID							= 'id';
	public const MESSAGE					= 'message';
	public const MSG_DATE					= 'msg_date';
	public const MUTEX						= 'mutex';
	public const NAME						= 'name';
	public const OVERWRITE					= 'overwrite';
	public const PROXY_HOST					= 'proxy_host';
	public const PROXY_PASSWORD				= 'proxy_password';
	public const PROXY_PORT					= 'proxy_port';
	public const PROXY_USERNAME				= 'proxy_username';
	public const QUALIFIED					= 'qualified';
	public const RECIPIENT					= 'recipient';
	public const RECIPIENT_ID				= 'recipient_id';
	public const SERVER_URL					= 'server_url';
	public const SIGN_TYPE_ADVANCED			= 'sign_type_advanced';
	public const SIGN_TYPE_QUALIFIED		= 'sign_type_qualified';
	public const SIGN_TYPE_STANDARD			= 'sign_type_standard';
	public const SIGNATURE_TYPE				= 'signature_type';
	public const TEXTUAL_COMPLEMENT_SIGN	= 'textual_complement_sign';
	public const TRANSACTION				= 'transaction';
	public const USE_PROXY					= 'use_proxy';
}
