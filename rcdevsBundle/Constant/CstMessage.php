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

class CstMessage
{
	public const CRON_JOB_ACTIVATED				= 'Cron job activated';
	public const CRON_JOB_ACTIVATED_LAST_TIME	= 'Cron job activated; the last time the job ran was at %s';
	public const CRON_JOB_ALREADY_ACTIVATED		= 'Cron job already reactivated as %s';
	public const CRON_JOB_BEEN_ACTIVATED		= 'Cron job activated at %s';
	public const CRON_JOB_DISABLED				= 'Cron job disabled at %s';
}
