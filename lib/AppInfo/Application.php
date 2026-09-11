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

namespace OCA\YumiSignNxtC\AppInfo;

// Load Composer autoloader first
require_once __DIR__ . '/../../vendor/autoload.php';

// RCDevs App
use OCA\YumiSignNxtC\RCDevs\Utility\Notification;
use OCA\YumiSignNxtC\Constant\CstApplication;
use OCA\YumiSignNxtC\FilesLoader;
use OCA\YumiSignNxtC\Service\ConfigurationService;

// Nextcloud Core
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\INavigationManager;
use OCP\IURLGenerator;

class Application extends App implements IBootstrap
{
	private string $appId;
	private string $appName;
	private string $appNameShort;
	private string $appNameSigned;
	private string $appNameSealed;
	private string $appNamespace;
	private string $appTableNameSessions;
	private bool $debugVueJs;

	public function __construct(
		array $urlParams = [],
	) {
		$this->appId = \basename(\dirname(__DIR__, 2));

		/** @var IAppConfig $config */
		$config = \OC::$server->get(IAppConfig::class);

		$configurationService		= new ConfigurationService($config);
		$this->appName				= $configurationService->getApplicationName();
		$this->appNameShort			= $configurationService->getApplicationNameShort();
		$this->debugVueJs			= $configurationService->getDebugVueJs();
		$this->appNameSigned		= $configurationService->getAppNameSigned();
		$this->appNameSealed		= $configurationService->getAppNameSealed();
		$this->appNamespace			= $configurationService->getAppNamespace();
		$this->appTableNameSessions	= $configurationService->getAppTableNameSessions();

		parent::__construct($this->appId, $urlParams);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function require_all_files($dir)
	{
		foreach (glob("$dir/*") as $path) {
			if (preg_match('/\.php$/', $path)) {
				require_once $path;
			} elseif (is_dir($path)) {
				$this->require_all_files($path);
			}
		}
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function getAppId(): string
	{
		return $this->appId;
	}

	public function getAppName(): string
	{
		return $this->appName;
	}

	public function getAppNameSigned(): string
	{
		return $this->appNameSigned;
	}

	public function getAppNameSealed(): string
	{
		return $this->appNameSealed;
	}

	public function getAppNamespace(): string
	{
		return $this->appNamespace;
	}

	public function getAppTableNameSessions(): string
	{
		return $this->appTableNameSessions;
	}

	public function getDebugVueJs(): bool
	{
		return $this->debugVueJs;
	}

	public function register(IRegistrationContext $context): void
	{
		$context->registerNotifierService(Notification::class);
	}

	public function boot(IBootContext $context): void
	{
		/** @var INavigationManager $navManager */
		$navManager = \OC::$server->get(INavigationManager::class);

		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = \OC::$server->get(IURLGenerator::class);

		$navManager->add(function () use ($urlGenerator) {
			return [
				CstApplication::ID    => $this->appId,
				CstApplication::NAME  => $this->appNameShort,
				CstApplication::HREF  => $urlGenerator->linkToRouteAbsolute($this->appId . '.Page.index'),
				CstApplication::ICON  => $urlGenerator->imagePath($this->appId, 'app.svg'),
				CstApplication::ORDER => 3,
				CstApplication::TYPE  => CstApplication::LINK,
			];
		});

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = \OC::$server->get(IEventDispatcher::class);

		FilesLoader::register($dispatcher);
	}
}
