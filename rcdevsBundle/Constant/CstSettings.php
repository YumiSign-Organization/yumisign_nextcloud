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
 * Constants for Settings fields stored in database
 * 
 * @package OCA\YumiSignNxtC\RCDevs\Constant
 */
class CstSettings
{
	public const API_KEY					= 'api_key';
	public const ASYNC_TIMEOUT				= 'async_timeout';
	public const CLIENT_ID					= 'client_id';
	public const CRON_INTERVAL				= 'cron_interval';
	public const ENABLE_SIGN				= 'enable_sign';
	public const ENABLESIGN					= 'enableSign';
	public const OVERWRITE					= 'overwrite';
	public const PROXY_HOST					= 'proxy_host';
	public const PROXY_PASSWORD				= 'proxy_password';
	public const PROXY_PORT					= 'proxy_port';
	public const PROXY_USERNAME				= 'proxy_username';
	public const SIGN_FORMAT_CADES			= 'sign_format_cades';
	public const SIGN_FORMAT_PADES			= 'sign_format_pades';
	public const SIGN_TYPE_ADVANCED			= 'sign_type_advanced';
	public const SIGN_TYPE_QUALIFIED		= 'sign_type_qualified';
	public const SIGN_TYPE_STANDARD			= 'sign_type_standard';
	public const SYNC_TIMEOUT				= 'sync_timeout';
	public const TEXTUAL_COMPLEMENT_SIGN	= 'textual_complement_sign';
	public const USE_PROXY					= 'use_proxy';
}
