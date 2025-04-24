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

namespace OCA\RCDevs\Service;

use Exception;
use OCA\RCDevs\Entity\UserEntity;
use OCA\RCDevs\Utility\Constantes\CstCommon;
use OCA\RCDevs\Utility\Constantes\CstException;
use OCA\RCDevs\Utility\Constantes\CstFile;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\FilesMetadata\IFilesMetadataManager;

class FileService
{
	public	Folder	$parentFolder;
	public	File	$file;
	public	string	$timedName;
	private	string	$extensionSignedFile;
	private	string	$timestamp;

	public function __construct(
		private ConfigurationService $configurationService,
		private IFilesMetadataManager $filesMetadataManager,
		private LogRCDevs $logRCDevs,
		private UserEntity $user,
		public int $id,
		public bool $toSeal = false,
		public bool $changeExtension = false,
	) {
		try {
			/** @var Node $tmpNode */
			$tmpNode = $user->getFolder()->getById($this->id)[0];

			// Signing a folder is not allowed
			if ($tmpNode->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
				throw new Exception(CstException::TYPE_NOT_FILE, 1);
			}

			$this->file = $tmpNode;

			// Define extension for signed file (PDF or P7S if source is not a PDF file)
			//	TODO	P7S is only for OOTP => have to build a function which gives the extension according to the App (using config.xml)
			$this->extensionSignedFile = Helpers::areEqual(pathinfo($this->file->getName(), PATHINFO_EXTENSION), CstCommon::PDF) ? pathinfo($this->file->getName(), PATHINFO_EXTENSION) : CstCommon::P7S;

			$this->parentFolder = $this->file->getParent();
			$this->timestamp = $user->getTimedLocales();

			// $textualComplement
			$sealComplement = ($this->configurationService->textualComplementSeal() === '' ? $this->configurationService->getAppNameSealed() : $this->configurationService->textualComplementSeal());
			$signComplement = ($this->configurationService->textualComplementSign() === '' ? $this->configurationService->getAppNameSigned() : $this->configurationService->textualComplementSign());

			$this->timedName = vsprintf(
				'%s_%s_%s.%s',
				[
					pathinfo($this->file->getName(), PATHINFO_FILENAME), // original filename without extension
					($this->toSeal ?
						$sealComplement :
						$signComplement
					),
					$this->timestamp,
					$this->extensionSignedFile, // original extension
				]
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error("Issue on file creation {$this->timedName}: {$th->getMessage()}", __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			throw $th;
		}
	}

	public function create(mixed $temporaryFile, bool $eraseOriginal = false): File
	{
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
				CstRequest::CODE	=> 1,
				CstRequest::DATA	=> $data,
				CstRequest::ERROR	=> null,
				CstRequest::MESSAGE	=> $message,
			];
		} catch (\Throwable $th) {
			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> null,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> $th->getMessage(),
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
