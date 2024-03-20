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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\YumiSignNxtC\AppInfo;

use OC\SystemConfig;
use OCA\YumiSignNxtC\Activity\Listener as ActivityListener;
// use OCA\YumiSignNxtC\Capabilities;
use OCA\YumiSignNxtC\CSPSetter;
use OCA\YumiSignNxtC\DeleteListener;
use OCA\YumiSignNxtC\FilesLoader;
use OCA\YumiSignNxtC\Manager;
use OCA\YumiSignNxtC\Notification\Listener as NotificationListener;
use OCA\YumiSignNxtC\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

class Application extends App implements IBootstrap {
	public const APP_ID = 'yumisign_nextcloud';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// $context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();

		$server->getNavigationManager()->add(function () use ($server) {
			/** @var IUser $user */
			$user = $server->getUserSession()->getUser();
			return [
				'id' => self::APP_ID,
				'name' => $server->getL10N(self::APP_ID)->t('YumiSignNxtC'),
				'href' => $server->getURLGenerator()->linkToRouteAbsolute(self::APP_ID . '.Page.index'),
				'icon' => $server->getURLGenerator()->imagePath(self::APP_ID, 'app.svg'),
				'order' => 3,
				'type' => 'link',
			];
		});

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $server->get(IEventDispatcher::class);

		FilesLoader::register($dispatcher);
	}
}
