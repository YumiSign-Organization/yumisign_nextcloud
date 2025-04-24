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

namespace OCA\YumiSignNxtC\AppInfo;

use OCA\RCDevs\Utility\Notification;
use OCA\YumiSignNxtC\FilesLoader;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

use OCP\Notification\IManager;
use OCP\IServerContainer;

class Application extends App implements IBootstrap
{
	// From info.xml
	protected string $appId;
	protected string $appNamespace;

	public function __construct(
		array $urlParams = [],
	) {
		$currentPathFile = pathinfo(__FILE__, PATHINFO_DIRNAME);

		// Integrate RCDevs bundle files
		$this->require_all_files("{$currentPathFile}/../../rcdevsBundle");

		/**
		 * Read info.xml file
		 */
		$infoXml = self::readInfo();

		$this->appId		= $infoXml['id'];
		$this->appNamespace	= $infoXml['namespace'];
		parent::__construct($this->appId, $urlParams);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	private static function readInfo(): array
	{
		$currentPathFile = pathinfo(__FILE__, PATHINFO_DIRNAME);

		$infoXml = json_decode(json_encode(simplexml_load_string(file_get_contents("{$currentPathFile}/../../appinfo/info.xml"))), true);

		return $infoXml;
	}

	private function require_all_files($dir)
	{
		foreach (glob("$dir/*") as $path) {
			if (preg_match('/\.php$/', $path)) {
				require_once $path;	// it's a PHP file so just require it
			} elseif (is_dir($path)) {
				$this->require_all_files($path);	// it's a subdir, so call the same function for this subdir
			}
		}
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public static function APP_ID()
	{
		$infoXml = self::readInfo();

		return ($infoXml === false) ? null : (string)$infoXml['id'];
	}

	public function register(IRegistrationContext $context): void
	{
		// $context->registerCapability(Capabilities::class);
		$context->registerNotifierService(Notification::class);
	}

	public function boot(IBootContext $context): void
	{
		$server = $context->getServerContainer();
		$this->registerNotifier($server);

		// TODO	deprecatedd fcts: TBR
		$server->getNavigationManager()->add(function () use ($server) {
			// /** @var IUser $user */
			// $user = $server->getUserSession()->getUser();
			return [
				'id' => $this->appId,
				'name' => $server->getL10N($this->appId)->t($this->appNamespace),
				'href' => $server->getURLGenerator()->linkToRouteAbsolute($this->appId . '.Page.index'),
				'icon' => $server->getURLGenerator()->imagePath($this->appId, 'app.svg'),
				'order' => 3,
				'type' => 'link',
			];
		});

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $server->get(IEventDispatcher::class);

		FilesLoader::register($dispatcher);
	}

	protected function registerNotifier(IServerContainer $server): void
	{
		$manager = $server->get(IManager::class);
		$manager->registerNotifierService(Notification::class);
	}
}
