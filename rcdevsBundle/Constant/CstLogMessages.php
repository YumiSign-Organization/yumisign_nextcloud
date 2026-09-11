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

class CstLogMessages
{
	public const CANNOT_SIGN_DISABLED			= 'Cannot sign with disabled Sign type';
	public const CRITICAL_ERROR_PROCESS			= 'Critical error during process. Error is "%s"';
	public const CUSTOM_LOG_UNAVAILABLE			= 'Custom log unavailable: %s';
	public const DIR_CREATION_FAILED			= 'Unable to create log directory: %s';
	public const FILE_CREATION_FAILED			= 'Unable to create log file: %s';
	public const FILE_NOT_WRITABLE				= 'Log file is not writable: %s';
	public const FILE_OPEN_FAILED				= 'Failed to open log file: %s';
	public const NOT_MANAGED                    = 'Not managed case';
	public const PATH_EMPTY						= 'Log path is empty or not configured';
	public const SEAL_DISABLED					= 'Seal process is disabled';
	public const SIGN_DISABLED					= 'Sign process is disabled';
	public const SOMETHING_WRONG				= 'Something went wrong during this process';
}
