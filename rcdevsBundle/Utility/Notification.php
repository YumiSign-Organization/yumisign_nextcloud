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

namespace OCA\RCDevs\Utility;

use OCA\RCDevs\Entity\NotificationEntity;
use OCA\RCDevs\Entity\UsersListEntity;
use OCA\RCDevs\Service\ConfigurationService;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;


class Notification implements INotifier
{
	public function __construct(
		private		ConfigurationService	$configurationService,
		protected	IFactory				$factory,
		protected	INotificationManager	$notificationManager,
		protected	IURLGenerator			$urlGenerator,
		protected	LogRCDevs				$logRCDevs,
	) {}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 * @return string
	 */
	public function getID(): string
	{
		try {
			return $this->configurationService->getAppId();
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	/**
	 * Human readable name describing the notifier
	 * @return string
	 */
	public function getName(): string
	{
		try {
			return $this->factory->get($this->configurationService->getAppId())->t("Add {$this->configurationService->getAppId()}");
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 */
	public function prepare(INotification $notification, string $languageCode): INotification
	{
		try {
			if ($notification->getApp() !== $this->configurationService->getAppId()) {
				// Not my app
				throw new \InvalidArgumentException();
			}
			$l = $this->factory->get($this->configurationService->getAppId(), $languageCode);

			// $parameters = $notification->getSubjectParameters();
			$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->configurationService->getAppId(), $this->configurationService->getApplicationLogo())));

			/**
			 * Set rich subject, see https://github.com/nextcloud/server/issues/1706 for mor information
			 * and https://github.com/nextcloud/server/blob/master/lib/public/RichObjectStrings/Definitions.php
			 * for a list of defined objects and their parameters.
			 */

			$parameters = $notification->getSubjectParameters();
			// If sign process returns a false/0 code, an Exception notification will be displayed
			$subject = ($parameters[CstRequest::CODE]
				? '{message}'
				: "{$this->configurationService->getApplicationNameShort()} error; contact your administrator"
			);
			
			// Prepare the message for internationalization
			$parameters[CstRequest::MESSAGE]	= $l->t($parameters[CstRequest::MESSAGE]);
			$parameters[CstRequest::STATUS]		= $l->t($parameters[CstRequest::STATUS]);

			// Fill the data for the notification
			$subject = str_replace(['{code}', '{message}', '{status}'], $parameters, $subject);

			$notification->setParsedSubject($subject);
			$notification->setRichSubject($subject, [
				$this->configurationService->getAppId() => [
					'type'	=> 'highlight',
					'id'	=> $notification->getObjectId(),
					'name'	=> $this->configurationService->getApplicationName(),
				]
			]);

			return $notification;
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	public function send(UsersListEntity $usersIdsList, NotificationEntity $notificationEntity): void
	{
		try {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp($this->configurationService->getAppId())
				->setDateTime(new \DateTime())
				->setObject($notificationEntity->idName, $notificationEntity->id)
				->setSubject($this->configurationService->getApplicationName(), [
					CstRequest::CODE	=> true,
					CstRequest::MESSAGE	=> $notificationEntity->message,
					CstRequest::STATUS	=> $notificationEntity->status,
				])
			;

			/** @var UserEntity $unitUserId */
			foreach ($usersIdsList->list as $unitUserId) {
				if (!empty($unitUserId->getId())) {
					$notification->setUser($unitUserId->getId());
					$this->notificationManager->notify($notification);
				}
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(sprintf("Critical error during process. Error is \"%s\"", $th->getMessage()), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}
}
