<?php

/**
 *
 * @copyright Copyright (c) 2023, RCDevs (info@rcdevs.com)
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

use OCP\IServerContainer;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;
use OCP\Notification\IManager;
use OCA\YumiSignNxtC\Notification\Notifier;

require_once(__DIR__ . '/../../vendor/autoload.php');

define("YMS_ALREADY_CANCELLED",         "already_cancelled");
define("YMS_CODE",                      "code");
define("YMS_DELETED",                   "deleted");
define("YMS_ERROR",                     "error");
define("YMS_EXCEPTION",                 "Exception");
define("YMS_ID",                        "id");
define("YMS_IDENTIFIER",                "identifier");
define("YMS_LIST_ID",                   "listId");
define("YMS_MESSAGE",                   "message");
define("YMS_NAME",                      "name");
define("YMS_OWNER",                     "owner");
define("YMS_RESPONSE",                  "response");
define("YMS_RESULT",                    "result");
define("YMS_SIGNED",                    "signed");
define("YMS_SIMPLE",                    "simple");
define("YMS_STARTED",                   "started");
define("YMS_STATUS_APPROVED",           "approved");
define("YMS_STATUS_CANCELED",           "canceled");
define("YMS_STATUS_DECLINED",           "declined");
define("YMS_STATUS_EXPIRED",            "expired");
define("YMS_STATUS_NOT_STARTED",        "not_started");
define("YMS_STATUS_SIGNED",             "signed");
define("YMS_STATUS_STARTED",            "started");
define("YMS_STATUS_TO_BE_ARCHIVED",     "to_be_archived");
define("YMS_STATUS",                    "status");
define("YMS_SUCCESS",                   "success");
define("YMS_URL_ARCHIVE",               "/storage/archive/");
define("YMS_VALUE",                     "value");
define("YMS_WF_SECRET",                 "WorkflowNotificationCallbackUrlSecretPreference");

class Application extends App implements IBootstrap
{

    public const APP_ID = 'yumisign_nextcloud';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);

        $container = $this->getContainer();
        $eventDispatcher = $container->get(IEventDispatcher::class);
        $eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
            Util::addScript(self::APP_ID, 'yumisign_nextcloud-main');
            Util::addStyle(self::APP_ID, 'style');
        });
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerNotifierService(Notifier::class);
    }

    public function boot(IBootContext $context): void
    {
        $server = $context->getServerContainer();
        $this->registerNotifier($server);
    }

    protected function registerNotifier(IServerContainer $server): void
    {
        $manager = $server->get(IManager::class);
        $manager->registerNotifierService(Notifier::class);
    }
}
