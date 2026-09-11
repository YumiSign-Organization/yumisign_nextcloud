<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\RCDevs\Constant\CstSignedFolder;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;
use OCA\YumiSignNxtC\RCDevs\Service\FilesRefreshService;
use OCA\YumiSignNxtC\RCDevs\Service\SignedDocumentService;
use OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService;
use OCP\Config\IUserConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Uses disposable local accounts and real Nextcloud storage/locks; no provider requests. */
class SignedDocumentServiceTest extends TestCase
{
	private array $users = [];
	private string $appId;
	private IAppData $data;
	private IRootFolder $root;
	private IUserConfig $preferences;
	private SignedFolderService $folders;
	private FilesRefreshService $refresh;
	private SignedDocumentService $delivery;
	private bool $overwrite = false;
	private ConfigurationService $configuration;

	protected function setUp(): void
	{
		$this->appId = 'yms_test_' . bin2hex(random_bytes(5));
		$manager = \OCP\Server::get(IUserManager::class);
		foreach (['a', 'r'] as $suffix) {
			$this->users[] = $manager->createUser($this->appId . $suffix, bin2hex(random_bytes(24)));
		}
		$this->root = \OCP\Server::get(IRootFolder::class);
		$this->preferences = \OCP\Server::get(IUserConfig::class);
		$this->configuration = $this->createMock(ConfigurationService::class);
		$this->configuration->method('getAppId')->willReturn($this->appId);
		$this->configuration->method('getUserSignedFolderApplicant')->willReturn('YMSSigned/applicant');
		$this->configuration->method('getUserSignedFolderRecipient')->willReturn('YMSSigned/recipient');
		$this->configuration->method('doyouOverwrite')->willReturnCallback(fn () => $this->overwrite);
		$this->folders = new SignedFolderService($this->configuration, $this->preferences, $this->root, \OCP\Server::get(IFilenameValidator::class));
		$this->refresh = new FilesRefreshService($this->configuration, $this->preferences);
		$this->data = \OCP\Server::get(IAppDataFactory::class)->get($this->appId);
		$this->delivery = $this->newService();
	}

	private function newService(): SignedDocumentService
	{
		return new SignedDocumentService($this->data, $this->configuration, $this->folders, $this->refresh, \OCP\Server::get(ILockingProvider::class), new NullLogger());
	}

	protected function tearDown(): void
	{
		foreach ($this->data->getDirectoryListing() as $folder) {
			$folder->delete();
		}
		foreach ($this->users as $user) {
			$user->delete();
		}
	}

	private function document(string $content = '%PDF-test'): array
	{
		return [['name' => 'toto_2026-09-08_09.50.00.pdf', 'content' => $content]];
	}

	public function testLazyFoldersRoleIsolationCollisionSuffixAndIdempotency(): void
	{
		$a = $this->users[0]->getUID();
		$r = $this->users[1]->getUID();
		$aRoot = $this->root->getUserFolder($a);
		$rRoot = $this->root->getUserFolder($r);
		$this->folders->validate($a);
		$this->folders->save($a, 'Signed by coworkers', 'YMSSigned/recipient');
		self::assertFalse($aRoot->nodeExists('Signed by coworkers'));
		self::assertFalse($aRoot->nodeExists('YMSSigned'));
		$participants = [$a => ['applicant'], $r => ['recipient']];
		$this->delivery->enqueue('workflow-1', $participants, $this->document());
		self::assertFalse($rRoot->nodeExists('YMSSigned'));
		self::assertTrue($this->delivery->deliver('workflow-1'));
		self::assertFalse($aRoot->nodeExists('YMSSigned/recipient'));
		self::assertFalse($rRoot->nodeExists('YMSSigned/applicant'));
		self::assertNotSame('', $this->refresh->token($r));
		self::assertSame('%PDF-test', $rRoot->get('YMSSigned/recipient/toto_2026-09-08_09.50.00.pdf')->getContent());
		self::assertTrue($this->newService()->deliver('workflow-1'));
		self::assertCount(1, $rRoot->get('YMSSigned/recipient')->getDirectoryListing());
		foreach ([2, 3] as $number) {
			$this->delivery->enqueue('workflow-' . $number, $participants, $this->document('%PDF-' . $number));
			self::assertTrue($this->delivery->deliver('workflow-' . $number));
			self::assertSame('%PDF-' . $number, $aRoot->get('Signed by coworkers/toto_2026-09-08_09.50.00_' . $number . '.pdf')->getContent());
		}
	}

	public function testFailedRecipientIsRetriedAtNewPathWithoutDuplicatingApplicant(): void
	{
		$a = $this->users[0]->getUID();
		$r = $this->users[1]->getUID();
		$this->root->getUserFolder($r)->newFile('Blocked', 'keep me');
		$this->preferences->setValueString($r, $this->appId, CstSignedFolder::KEYS['recipient'], 'Blocked/Child');
		$this->delivery->enqueue('retry-workflow', [$a => ['applicant'], $r => ['recipient']], $this->document());
		self::assertFalse($this->delivery->deliver('retry-workflow'));
		self::assertSame('', $this->refresh->token($r));
		self::assertCount(1, $this->root->getUserFolder($a)->get('YMSSigned/applicant')->getDirectoryListing());
		$this->folders->save($r, 'YMSSigned/applicant', 'Archives/Signatures/2026');
		self::assertFalse($this->root->getUserFolder($r)->nodeExists('Archives'));
		// Simulate an interrupted checkpoint write: the preceding valid state must recover.
		$this->data->getFolder(CstSignedFolder::OUTBOX . '-pending')->getFile(hash('sha256', 'retry-workflow') . '.json')->putContent('{interrupted');
		self::assertSame(['retry-workflow'], $this->newService()->retryPending());
		self::assertCount(1, $this->root->getUserFolder($a)->get('YMSSigned/applicant')->getDirectoryListing());
		self::assertSame('%PDF-test', $this->root->getUserFolder($r)->get('Archives/Signatures/2026/toto_2026-09-08_09.50.00.pdf')->getContent());
		self::assertSame('keep me', $this->root->getUserFolder($r)->get('Blocked')->getContent());
	}

	public function testBothRolesSameFolderProduceOneFileAndDifferentFoldersProduceTwo(): void
	{
		$a = $this->users[0]->getUID();
		$this->folders->save($a, 'Together', 'Together');
		$this->delivery->enqueue('self-1', [$a => ['applicant', 'recipient']], $this->document());
		self::assertTrue($this->delivery->deliver('self-1'));
		self::assertCount(1, $this->root->getUserFolder($a)->get('Together')->getDirectoryListing());
		$this->folders->save($a, 'A', 'R');
		$this->delivery->enqueue('self-2', [$a => ['applicant', 'recipient']], $this->document());
		self::assertTrue($this->delivery->deliver('self-2'));
		self::assertSame($this->root->getUserFolder($a)->get('A/toto_2026-09-08_09.50.00.pdf')->getContent(), $this->root->getUserFolder($a)->get('R/toto_2026-09-08_09.50.00.pdf')->getContent());
	}

	public function testFailureAfterWritingBeforeRefreshDoesNotCreateAnotherCopy(): void
	{
		$a = $this->users[0]->getUID();
		$refresh = $this->createMock(FilesRefreshService::class);
		$calls = 0;
		$refresh->expects(self::exactly(2))->method('signal')->willReturnCallback(static function () use (&$calls): void {
			if (++$calls === 1) {
				throw new \RuntimeException('Temporary preference write failure');
			}
		});
		$service = new SignedDocumentService($this->data, $this->configuration, $this->folders, $refresh, \OCP\Server::get(ILockingProvider::class), new NullLogger());
		$service->enqueue('refresh-retry', [$a => ['applicant']], $this->document());
		self::assertFalse($service->deliver('refresh-retry'));
		self::assertTrue($service->deliver('refresh-retry'));
		self::assertCount(1, $this->root->getUserFolder($a)->get('YMSSigned/applicant')->getDirectoryListing());
	}

	public function testOverwriteOnlyAffectsDestinationAndPendingSourceIsRetained(): void
	{
		$a = $this->users[0]->getUID();
		$this->root->getUserFolder($a)->newFile('source.pdf', 'original');
		$this->delivery->rememberSource('fetch', ['status' => 'signed', 'id' => 'fetch']);
		self::assertSame('fetch', $this->newService()->pendingSources()[0]['reference']);
		$this->delivery->forgetSource('fetch');
		self::assertSame([], $this->delivery->pendingSources());
		foreach (['first', 'second'] as $name) {
			$this->delivery->enqueue($name, [$a => ['applicant']], $this->document('%PDF-' . $name));
			$this->overwrite = true;
			self::assertTrue($this->delivery->deliver($name));
		}
		self::assertCount(1, $this->root->getUserFolder($a)->get('YMSSigned/applicant')->getDirectoryListing());
		self::assertSame('%PDF-second', $this->root->getUserFolder($a)->get('YMSSigned/applicant/toto_2026-09-08_09.50.00.pdf')->getContent());
		self::assertSame('original', $this->root->getUserFolder($a)->get('source.pdf')->getContent());
	}
}
