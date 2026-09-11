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

class CstDatabase
{
	public const _ALL				= '*';
	public const COLUMN_CLASS		= 'class';
	public const COLUMN_LAST_RUN	= 'last_run';
	public const COLUMN_RESERVED_AT	= 'reserved_at';
	public const COUNT				= 'count';
	public const COUNTALL			= 'count(*)';
	public const QRY_UPDATED_ROWS	= 'updatedRows';
	public const REQUESTS			= 'requests';
	public const TABLE_JOBS			= 'jobs';
	public const TRANSACTIONS		= 'transactions';
}
