<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Service;

use OCA\YumiSignNxtC\RCDevs\Constant\CstSignedFolder;
use OCP\Files\File;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

/** Durable delivery outbox. The caller only submits globally successful results. */
class SignedDocumentService
{
	public function __construct(
		private IAppData $appData,
		private ConfigurationService $configuration,
		private SignedFolderService $folders,
		private FilesRefreshService $refresh,
		private ILockingProvider $locks,
		private LoggerInterface $logger,
	) {
	}

	private function bucket(string $name): ISimpleFolder
	{
		$name = CstSignedFolder::OUTBOX . '-' . $name;
		try {
			return $this->appData->getFolder($name);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder($name);
		}
	}

	private function key(string $reference): string
	{
		return hash('sha256', $reference);
	}

	private function lockKey(string $reference): string
	{
		return hash('sha256', $this->configuration->getAppId() . '/signed-delivery/' . $reference);
	}

	public function known(string $reference): bool
	{
		$key = $this->key($reference) . '.json';
		return $this->readState($this->bucket('pending'), $key) !== null || $this->readState($this->bucket('completed'), $key) !== null;
	}

	/** Retain retrieval metadata before downloading, so transient failures outlive workflow expiry. */
	public function rememberSource(string $reference, array $source): void
	{
		$lock = $this->lockKey($reference);
		$this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$this->writeState($this->bucket('sources'), $this->key($reference) . '.json', ['reference' => $reference, 'source' => $source]);
		} finally {
			$this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function pendingSources(): array
	{
		$sources = [];
		foreach ($this->bucket('sources')->getDirectoryListing() as $entry) {
			if (!str_ends_with($entry->getName(), '.json')) {
				continue;
			}
			try {
				$state = $this->readState($this->bucket('sources'), $entry->getName());
				if ($state !== null) {
					$sources[] = $state;
				}
			} catch (\Throwable $e) {
				$this->logger->error('Cannot read signed document retrieval metadata', ['exception' => $e]);
			}
		}
		return $sources;
	}

	public function forgetSource(string $reference): void
	{
		$bucket = $this->bucket('sources');
		$key = $this->key($reference) . '.json';
		foreach ([$key, $key . '.bak'] as $name) {
			if ($bucket->fileExists($name)) {
				$bucket->getFile($name)->delete();
			}
		}
	}

	/**
	 * Keep provider bytes before attempting any user-folder creation.
	 * @param array<string, list<string>> $participants local UID => roles
	 * @param list<array{name: string, content: string}> $documents
	 */
	public function enqueue(string $reference, array $participants, array $documents): void
	{
		$lock = $this->lockKey($reference);
		$this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			if ($this->known($reference)) {
				return;
			}
			if ($documents === [] || $participants === []) {
				throw new RuntimeException('A signed delivery requires documents and local participants');
			}
			$key = $this->key($reference);
			$job = ['reference' => $reference, 'documents' => [], 'deliveries' => []];
			foreach ($documents as $index => $document) {
				$name = $document['name'];
				$this->folders->validatePath($name, 'signed filename');
				if (str_contains($name, '/') || $document['content'] === '') {
					throw new RuntimeException('Invalid or empty signed document');
				}
				$blob = $key . '-' . $index;
				$this->put($this->bucket('content'), $blob, $document['content']);
				$job['documents'][] = ['name' => $name, 'blob' => $blob, 'sha256' => hash('sha256', $document['content'])];
				foreach ($participants as $uid => $roles) {
					foreach (array_unique($roles) as $role) {
						if (!isset(CstSignedFolder::KEYS[$role]) || (string) $uid === '') {
							throw new RuntimeException('Invalid signed delivery participant');
						}
						$job['deliveries'][] = ['uid' => (string) $uid, 'role' => $role, 'document' => $index, 'done' => false];
					}
				}
			}
			$this->checkpoint($job);
		} finally {
			$this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	private function put(ISimpleFolder $folder, string $name, string $content): void
	{
		if ($folder->fileExists($name)) {
			$folder->getFile($name)->putContent($content);
		} else {
			$folder->newFile($name, $content);
		}
	}

	private function checkpoint(array $job): void
	{
		$this->writeState($this->bucket('pending'), $this->key($job['reference']) . '.json', $job);
	}

	/** Keep the preceding valid checkpoint if an AppData write is interrupted. */
	private function writeState(ISimpleFolder $folder, string $name, array $state): void
	{
		$previous = $this->readState($folder, $name);
		if ($previous !== null) {
			$this->put($folder, $name . '.bak', json_encode($previous, JSON_THROW_ON_ERROR));
		}
		$this->put($folder, $name, json_encode($state, JSON_THROW_ON_ERROR));
	}

	private function readState(ISimpleFolder $folder, string $name): ?array
	{
		foreach ([$name, $name . '.bak'] as $candidate) {
			if (!$folder->fileExists($candidate)) {
				continue;
			}
			try {
				$state = json_decode($folder->getFile($candidate)->getContent(), true, 512, JSON_THROW_ON_ERROR);
				if (is_array($state) && isset($state['reference'])) {
					return $state;
				}
			} catch (\JsonException $e) {
				$this->logger->error('Recovering an interrupted signed delivery checkpoint', ['exception' => $e]);
			}
		}
		return null;
	}

	private function cleanupCompleted(array $job): void
	{
		$key = $this->key($job['reference']) . '.json';
		$pending = $this->bucket('pending');
		foreach ([$key, $key . '.bak'] as $name) {
			if ($pending->fileExists($name)) {
				$pending->getFile($name)->delete();
			}
		}
		foreach ($job['documents'] as $document) {
			if ($this->bucket('content')->fileExists($document['blob'])) {
				$this->bucket('content')->getFile($document['blob'])->delete();
			}
		}
	}

	public function deliver(string $reference): bool
	{
		$lock = $this->lockKey($reference);
		$this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$key = $this->key($reference) . '.json';
			if (($completed = $this->readState($this->bucket('completed'), $key)) !== null) {
				$this->cleanupCompleted($completed);
				return true;
			}
			$job = $this->readState($this->bucket('pending'), $key);
			if ($job === null) {
				throw new RuntimeException('Signed delivery state is missing or unreadable');
			}
			foreach ($job['deliveries'] as $index => $delivery) {
				if ($delivery['done']) {
					continue;
				}
				try {
					$this->writeDelivery($job, $index);
					unset($job['deliveries'][$index]['error']);
				} catch (\Throwable $e) {
					$job['deliveries'][$index]['error'] = $e->getMessage();
					$this->logger->error('Signed document delivery remains pending', ['app' => $this->configuration->getAppId(), 'reference' => $reference, 'uid' => $delivery['uid'], 'exception' => $e]);
				}
				$this->checkpoint($job);
			}
			if (in_array(false, array_column($job['deliveries'], 'done'), true)) {
				return false;
			}
			// Retain small receipts for duplicate callbacks; provider bytes are no longer needed.
			$this->writeState($this->bucket('completed'), $key, $job);
			$this->cleanupCompleted($job);
			return true;
		} finally {
			$this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	private function writeDelivery(array &$job, int $index): void
	{
		$delivery = &$job['deliveries'][$index];
		$document = $job['documents'][$delivery['document']];
		$folder = $this->folders->destination($delivery['uid'], $delivery['role']);
		// Serialize RCDevs deliveries to the same physical folder across all applications.
		$folderLock = hash('sha256', 'rcdevs/signed-folder/' . $folder->getId());
		$this->locks->acquireLock($folderLock, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			// One user filling both roles in the same folder gets only one physical file.
			foreach ($job['deliveries'] as $saved) {
				if ($saved['done'] && $saved['uid'] === $delivery['uid'] && $saved['document'] === $delivery['document'] && $saved['folderId'] === $folder->getId()) {
					$delivery = array_merge($delivery, array_intersect_key($saved, array_flip(['done', 'folderId', 'name', 'fileId'])));
					return;
				}
			}
			$file = null;
			// Recover a crash after a successful write but before persisting its receipt.
			if (($delivery['folderId'] ?? null) === $folder->getId() && isset($delivery['name']) && $folder->nodeExists($delivery['name'])) {
				$candidate = $folder->get($delivery['name']);
				if ($candidate instanceof File && hash('sha256', $candidate->getContent()) === $document['sha256']) {
					$file = $candidate;
				} elseif ($candidate instanceof File && ($delivery['fileId'] ?? null) === $candidate->getId()) {
					// Our own interrupted write may be resumed, even when overwrite is disabled.
					$file = $candidate;
				}
			}
			if ($file === null) {
				$name = $document['name'];
				$suffix = CstSignedFolder::FIRST_SUFFIX;
				while ($folder->nodeExists($name)) {
					$existing = $folder->get($name);
					if ($this->configuration->doyouOverwrite() && $existing instanceof File) {
						$file = $existing;
						break;
					}
					$name = self::collisionName($document['name'], $suffix++);
				}
				$delivery['folderId'] = $folder->getId();
				$delivery['name'] = $name;
				unset($delivery['fileId']);
				$this->checkpoint($job);
				$file ??= $folder->newFile($name);
				$delivery['fileId'] = $file->getId();
				$this->checkpoint($job);
			}
			$content = $this->bucket('content')->getFile($document['blob'])->getContent();
			if (hash('sha256', $content) !== $document['sha256']) {
				throw new RuntimeException('Stored signed document failed its integrity check');
			}
			if (hash('sha256', $file->getContent()) !== $document['sha256']) {
				$file->putContent($content);
			}
			$delivery['fileId'] = $file->getId();
			// If signaling fails, the retained attempt is reconciled without creating another file.
			$this->refresh->signal($delivery['uid']);
			$delivery['done'] = true;
		} finally {
			$this->locks->releaseLock($folderLock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public static function collisionName(string $name, int $number): string
	{
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$stem = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);
		return $stem . sprintf(CstSignedFolder::COLLISION_SUFFIX, $number) . ($extension === '' ? '' : '.' . $extension);
	}

	/** Called by the application's background job independently of provider expiry/status. */
	public function retryPending(): array
	{
		$completed = [];
		foreach ($this->bucket('pending')->getDirectoryListing() as $entry) {
			if (!str_ends_with($entry->getName(), '.json')) {
				continue;
			}
			try {
				$job = $this->readState($this->bucket('pending'), $entry->getName());
				if ($job === null) {
					continue;
				}
				if ($this->deliver($job['reference'])) {
					$completed[] = $job['reference'];
				}
			} catch (\Throwable $e) {
				$this->logger->error('Cannot retry signed document delivery', ['app' => $this->configuration->getAppId(), 'exception' => $e]);
			}
		}
		return $completed;
	}
}
