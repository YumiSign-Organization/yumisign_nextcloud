<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Service;

use OCA\YumiSignNxtC\RCDevs\Constant\CstSignedFolder;
use OCP\Config\IUserConfig;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use RuntimeException;

/** Personal destinations, shared by all RCDevs applications. No provider logic. */
class SignedFolderService
{
	public function __construct(
		private ConfigurationService $configuration,
		private IUserConfig $preferences,
		private IRootFolder $root,
		private IFilenameValidator $validator,
	) {
	}

	public function defaults(): array
	{
		return [
			CstSignedFolder::APPLICANT => $this->configuration->getUserSignedFolderApplicant(),
			CstSignedFolder::RECIPIENT => $this->configuration->getUserSignedFolderRecipient(),
		];
	}

	/** Also usable by the settings page when values are invalid and need correction. */
	public function values(string $uid): array
	{
		$values = $this->defaults();
		foreach (CstSignedFolder::KEYS as $role => $key) {
			$values[$role] = $this->preferences->getValueString($uid, $this->configuration->getAppId(), $key, $values[$role]);
		}
		return $values;
	}

	public function validatePath(string $path, string $field): string
	{
		if ($path === '' || trim($path) !== $path || str_contains($path, '\\') || str_contains($path, ':')) {
			throw new RuntimeException("Invalid signed folder: {$field}. A non-empty relative Files path is required.");
		}
		foreach (explode('/', $path) as $part) {
			if ($part === '' || $part === '.' || $part === '..') {
				throw new RuntimeException("Invalid signed folder: {$field}. Empty, absolute or parent paths are not allowed.");
			}
			try {
				$this->validator->validateFilename($part);
			} catch (\Throwable $e) {
				throw new RuntimeException("Invalid signed folder: {$field}. " . $e->getMessage(), 0, $e);
			}
		}
		return $path;
	}

	/** Validate both XML defaults and both effective values, without creating folders. */
	public function validate(string $uid, ?array $values = null): array
	{
		foreach ($this->defaults() as $role => $path) {
			$this->validatePath($path, CstSignedFolder::KEYS[$role] . ' (config.xml)');
		}
		$values ??= $this->values($uid);
		foreach (CstSignedFolder::KEYS as $role => $key) {
			$this->validatePath($values[$role] ?? '', $key);
			$this->walk($uid, $values[$role], false);
		}
		return $values;
	}

	public function save(string $uid, string $applicant, string $recipient): array
	{
		$values = $this->validate($uid, compact('applicant', 'recipient'));
		foreach (CstSignedFolder::KEYS as $role => $key) {
			$this->preferences->setValueString($uid, $this->configuration->getAppId(), $key, $values[$role]);
		}
		return $values;
	}

	/** Only called while saving a final signed document, never while editing settings. */
	public function destination(string $uid, string $role): Folder
	{
		$values = $this->validate($uid);
		if (!isset(CstSignedFolder::KEYS[$role])) {
			throw new RuntimeException('Unknown signed document role');
		}
		return $this->walk($uid, $values[$role], true);
	}

	private function walk(string $uid, string $path, bool $create): Folder
	{
		$folder = $this->root->getUserFolder($uid);
		foreach (explode('/', $path) as $part) {
			$folder->verifyPath($part);
			try {
				$node = $folder->get($part);
			} catch (NotFoundException $e) {
				if (!$folder->isCreatable()) {
					throw new RuntimeException("Signed folder is not writable: {$path}");
				}
				if (!$create) {
					return $folder;
				}
				try {
					$node = $folder->newFolder($part);
				} catch (\Throwable $creationError) {
					// Another request may have created this level in the meantime.
					try {
						$node = $folder->get($part);
					} catch (NotFoundException $missing) {
						throw $creationError;
					}
				}
			}
			if (!$node instanceof Folder) {
				throw new RuntimeException("A file blocks the signed folder: {$path}");
			}
			$folder = $node;
		}
		if (!$folder->isCreatable()) {
			throw new RuntimeException("Signed folder is not writable: {$path}");
		}
		return $folder;
	}
}
