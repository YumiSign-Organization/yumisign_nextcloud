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

namespace OCA\YumiSignNxtC\RCDevs\Service;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstCommon;
use OCA\YumiSignNxtC\RCDevs\Constant\CstException;
use OCA\YumiSignNxtC\RCDevs\Constant\CstFile;
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransactionType;
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;

// Nextcloud Core
use Exception;
use OCP\Files\File;
use OCP\Files\Folder;

class FileService
{
	public		Folder	$parentFolder;
	public		File	$file;
	public		string	$timedName;
	protected	string	$extensionSignedFile;
	protected	string	$timestamp;

	public function __construct(
		protected	LogRCDevs $logRCDevs,
		protected	UserEntity $user,
		public		int $id,
		) {
			try {
				$tmpNodes = $user->getFolder()->getById($this->id);
				$tmpNode = $tmpNodes[0] ?? null;

				// Signing a folder is not allowed
				if (!$tmpNode instanceof File) {
					throw new Exception(CstException::TYPE_NOT_FILE, 1);
				}

			$this->file = $tmpNode;

			// Define extension for signed file (PDF or P7S if source is not a PDF file)
			//	TODO	P7S is only for OOTP => have to build a function which gives the extension according to the App (using config.xml)
			$this->extensionSignedFile = Helpers::areEqual(pathinfo($this->file->getName(), PATHINFO_EXTENSION), CstCommon::PDF) ? pathinfo($this->file->getName(), PATHINFO_EXTENSION) : CstCommon::P7S;

			$this->parentFolder = $this->file->getParent();
			$this->timestamp = $user->getTimedLocales();
		} catch (\Throwable $th) {
			$this->logRCDevs->error("Issue on file creation {$this->file->getName()}: {$th->getMessage()}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	public function create(
		mixed $temporaryFile,
		bool $eraseOriginal = false
	): File {
		try {
			// If signed file is not a PDF but P7S, force to "not erase original"
			$eraseOriginal = $eraseOriginal && Helpers::isPdf($this->timedName);

			if ($eraseOriginal) {
				$this->file->putContent($temporaryFile);
				$this->logRCDevs->info(sprintf('File modified [%s]', $this->file->getName()), __FUNCTION__);

				return $this->user->getFolder()->getById($this->id)[0];
			} else {
				$transactionFile = $this->parentFolder->newFile($this->timedName, $temporaryFile);
				$this->logRCDevs->info(sprintf('File created [%s]', $this->timedName), __FUNCTION__);

				$this->id = $transactionFile->getId();
				return $transactionFile;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(vsprintf('%s for %s : [%s]', [CstException::FILE_CREATION, $this->timedName, $th->getMessage()]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	public function intel(): array
	{
		$returned = [];
		$message = null;

		try {
			$data = [
				CstFile::CONTENT	=> $this->file->getContent(),
				CstFile::NAME		=> $this->file->getName(),
				CstFile::SIZE		=> $this->file->getSize(),
				CstFile::MTIME		=> $this->file->getMTime(),
			];

			$returned = [
				CstReturn::CODE	=> 1,
				CstReturn::DATA	=> $data,
				CstReturn::ERROR	=> null,
				CstReturn::MESSAGE	=> $message,
			];
		} catch (\Throwable $th) {
			$returned = [
				CstReturn::CODE	=> 0,
				CstReturn::DATA	=> null,
				CstReturn::ERROR	=> $th->getCode(),
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}

	public function getContent(): string
	{
		return $this->file->getContent();
	}

	public function getMimeType(): string
	{
		return $this->file->getMimeType();
	}

	public function getMTime(): int
	{
		return $this->file->getMTime();
	}

	public function getName(): string
	{
		return $this->file->getName();
	}

	public function getParent(): Folder
	{
		return $this->file->getParent();
	}

	public function getPath(): string
	{
		return $this->file->getPath();
	}

	public function getSize(): int|float
	{
		return $this->file->getSize();
	}
}
