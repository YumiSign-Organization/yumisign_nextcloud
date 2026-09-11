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

namespace OCA\YumiSignNxtC\Service;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Service\FileService as RCDevsFileService;

// Nextcloud Core
use OCP\Files\File;
use OCP\Files\Folder;

class FileService extends RCDevsFileService
{
	public function __construct(
		// Parent
		protected	LogRCDevs				$logRCDevs,
		protected	UserEntity				$user,
		public		int						$id,
		// Child
		private		ConfigurationService	$configurationService,
		public		bool					$changeExtension = false,
		public		bool					$toSeal = false,
	) {
		try {
			parent::__construct($logRCDevs, $user, $id);

			// $textualComplement
			$signComplement = ($this->configurationService->textualComplementSign() === '' ? $this->configurationService->getAppNameSigned() : $this->configurationService->textualComplementSign());

			$this->timedName = vsprintf(
				'%s_%s_%s.%s',
				[
					pathinfo($this->file->getName(), PATHINFO_FILENAME), // original filename without extension
					$signComplement,
					$this->timestamp,
					$this->extensionSignedFile, // original extension
				]
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error("Issue on file creation {$this->file->getName()}: {$th->getMessage()}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}
}
