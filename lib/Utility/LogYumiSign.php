<?php

/**
 *
 * @copyright Copyright (c) 2021, RCDevs (info@rcdevs.com)
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

namespace OCA\YumiSignNxtC\Utility;

use Exception;
use OC\Config;
use OCA\Activity\AppInfo\Application;
use OCP\IConfig;

class LogYumiSign
{
	private static $nxcDataDirectory;

	private static function __constructStatic()
	{
		// $__config = new \OC\Config('config/');
		// self::$nxcDataDirectory = rtrim($__config->getValue('datadirectory'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		// $__config = new IConfig();

		// self::$nxcDataDirectory = $__config->getSystemValue('datadirectory');
	}

	public static function write($logMsg, $functionName, $throw = false)
	{
		// if (empty(self::$nxcDataDirectory)) self::__constructStatic();

		// error_log(sprintf("[%s] [YumiSign-Nextcloud] [%s] %s\n", date("Y-m-d H:i:s"), $functionName, $logMsg), 3, self::$nxcDataDirectory . "YumiSignNxtC.log");
		if ($throw) throw new Exception($logMsg, 1);
	}
}
