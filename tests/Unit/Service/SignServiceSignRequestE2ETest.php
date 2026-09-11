<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Entity\UsersListEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Notification;
use OCA\YumiSignNxtC\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Constant\CstEntity;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\CurlService;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Service\TokenService;
use OCA\YumiSignNxtC\Tests\Support\YmsLocalStubServer;
use OCA\YumiSignNxtC\Tests\Support\YmsSignRequestStubScenarios;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SignServiceSignRequestE2ETest extends TestCase
{
	private ?YmsLocalStubServer $stubServer = null;

	private ?string $envelopeId = null;

	private ?int $createdFileId = null;

	private ?string $testUserId = null;

	private ?string $testUserEmail = null;

	private ?TransactionMapper $mapper = null;

	protected function tearDown(): void
	{
		if ($this->mapper !== null && $this->envelopeId !== null) {
			$this->mapper->deleteTransactions($this->envelopeId, CstEntity::ENVELOPE_ID);
		}

		if ($this->createdFileId !== null) {
			/** @var IRootFolder $rootFolder */
			$rootFolder = \OC::$server->get(IRootFolder::class);
			$nodes = $rootFolder->getById($this->createdFileId);
			if (isset($nodes[0])) {
				$nodes[0]->delete();
			}
		}

		if ($this->stubServer instanceof YmsLocalStubServer) {
			$this->stubServer->stop();
		}
		$this->stubServer = null;

		if ($this->testUserId !== null) {
			\OCP\Server::get(IUserManager::class)->get($this->testUserId)?->delete();
		}
		parent::tearDown();
	}

	/**
	 * End-to-end test for VueJS "Send" path:
	 * self-signed + standard signature creates a transaction record in DB.
	 *
	 * @group yms_service
	 * @group yms_e2e
	 * @return void
	 */
	public function testSelfSignedStandardSendCreatesTransactionInDatabase(): void
	{
		$this->logMarker('start');

		[$userId, $userEmail] = $this->resolveTestUser();
		$this->testUserId = $userId;
		$this->testUserEmail = $userEmail;

		$workflowId = random_int(20000, 90000);
		$this->envelopeId = sprintf('e2e-sign-%s', bin2hex(random_bytes(8)));
		$this->stubServer = YmsLocalStubServer::start(
			YmsSignRequestStubScenarios::selfStandardSuccess(
				65699,
				$workflowId,
				$this->envelopeId,
				$userEmail,
			)
		);

		$signService = $this->buildSignService($userId, $this->stubServer->baseUrl());
		$this->mapper = $this->buildMapper();

		[$fileId, $path] = $this->createUserPdfFile($userId);
		$this->createdFileId = $fileId;

		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		/** @var IRootFolder $rootFolder */
		$rootFolder = \OC::$server->get(IRootFolder::class);
		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);

		$applicant = new UserEntity($config, $rootFolder, $userManager, $userId);
		$recipients = new UsersListEntity($config, $rootFolder, $userManager, $userId);

		$cdem = $signService->signLocalAsyncPrepare(
			$applicant,
			$recipients,
			$path,
			$fileId,
			new SignatureType(false, false, true),
		);

		self::assertTrue($cdem->isSuccess(), (string)$cdem->message());
		self::assertIsArray($cdem->data());
		self::assertSame($this->envelopeId, $cdem->data()[CstRequest::ENVELOPEID] ?? null);
		self::assertSame($workflowId, $cdem->data()[CstRequest::WORKFLOWID] ?? null);

		$count = $this->mapper->countTransactionsByTransactionId($this->envelopeId, CstEntity::ENVELOPE_ID);
		self::assertSame(1, $count);

		/** @var IDBConnection $db */
		$db = \OC::$server->get(IDBConnection::class);
		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);
		$tableName = (new ConfigurationService($appConfig))->getAppTableNameSessions();

		$qb = $db->getQueryBuilder();
		$qb
			->select('status', 'global_status')
			->from($tableName)
			->where($qb->expr()->eq('envelope_id', $qb->createNamedParameter($this->envelopeId)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch() ?: [];
		$result->closeCursor();

		self::assertSame('pending', strtolower((string)($row['status'] ?? '')));
		self::assertSame('pending', strtolower((string)($row['global_status'] ?? '')));

		$this->logMarker('inserted', [
			'envelope_id' => $this->envelopeId,
			'user_id' => $userId,
			'status' => (string)($row['status'] ?? ''),
			'global_status' => (string)($row['global_status'] ?? ''),
		]);
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function resolveTestUser(): array
	{
		$uid = 'yms_request_test_' . bin2hex(random_bytes(5));
		$user = \OCP\Server::get(IUserManager::class)->createUser($uid, bin2hex(random_bytes(24)));
		$email = $uid . '@example.invalid';
		$user->setEMailAddress($email);
		return [$uid, $email];
	}

	/**
	 * @return array{0:int,1:string}
	 */
	private function createUserPdfFile(string $userId): array
	{
		/** @var IRootFolder $rootFolder */
		$rootFolder = \OC::$server->get(IRootFolder::class);
		$userFolder = $rootFolder->getUserFolder($userId);

		$filename = sprintf('yms-e2e-sign-%s.pdf', bin2hex(random_bytes(6)));
		$file = $userFolder->newFile($filename, "%PDF-1.4\n%YMS E2E\n");

		return [(int)$file->getId(), (string)$file->getPath()];
	}

	private function buildMapper(): TransactionMapper
	{
		/** @var IDBConnection $db */
		$db = \OC::$server->get(IDBConnection::class);
		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);
		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);

		$configurationService = new ConfigurationService($appConfig);
		$logRCDevs = new LogRCDevs($configurationService, $logger);

		return new TransactionMapper($db, $appConfig, $logRCDevs);
	}

	private function buildSignService(string $userId, string $baseUrl): SignService
	{
		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);
		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		/** @var IDateTimeFormatter $formatter */
		$formatter = \OC::$server->get(IDateTimeFormatter::class);
		/** @var IFactory $l10nFactory */
		$l10nFactory = \OC::$server->get(IFactory::class);
		/** @var IL10N $l10n */
		$l10n = $l10nFactory->get('yumisign_nextcloud');
		/** @var IRootFolder $rootFolder */
		$rootFolder = \OC::$server->get(IRootFolder::class);
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = \OC::$server->get(IURLGenerator::class);
		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);
		/** @var IUserSession $userSession */
		$userSession = \OC::$server->get(IUserSession::class);
		/** @var INotificationManager $notificationManager */
		$notificationManager = \OC::$server->get(INotificationManager::class);
		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);

		$configurationService = new ConfigurationService($appConfig);
		$logRCDevs = new LogRCDevs($configurationService, $logger);
		$mapper = new TransactionMapper(\OC::$server->get(IDBConnection::class), $appConfig, $logRCDevs);

		/** @var Notification&MockObject $notification */
		$notification = $this->createMock(Notification::class);
		/** @var TokenService&MockObject $tokenService */
		$tokenService = $this->createMock(TokenService::class);
		$tokenService
			->method('isTokenExpired')
			->willReturn(true);

		$service = new SignService(
			$configurationService,
			new CurlService($appConfig, $logRCDevs),
			$appConfig,
			$config,
			$formatter,
			$l10nFactory,
			$l10n,
			$l10n,
			$rootFolder,
			$urlGenerator,
			$userManager,
			$userSession,
			$logRCDevs,
			$notification,
			$mapper,
			$tokenService,
			$notificationManager,
			$urlGenerator,
			$userId,
			$this->createMock(\OCP\Config\IUserConfig::class),
			$this->createMock(\OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService::class),
			$this->createMock(\OCA\YumiSignNxtC\RCDevs\Service\SignedDocumentService::class),
		);

		$stubCurl = $this->buildStubCurlService($baseUrl, $logRCDevs);
		$property = new \ReflectionProperty(SignService::class, 'curlService');
		$property->setValue($service, $stubCurl);

		return $service;
	}

	private function buildStubCurlService(string $baseUrl, LogRCDevs $logRCDevs): CurlService
	{
		/** @var IAppConfig $appConfig */
		$appConfig = \OC::$server->get(IAppConfig::class);
		$service = new CurlService($appConfig, $logRCDevs);
		$service->addCredentialKey('x-yumisign-api-key:test-token');

		$fakeConfiguration = new class($appConfig, $baseUrl) extends ConfigurationService {
			public function __construct(
				IAppConfig $config,
				private string $baseUrl,
			) {
				parent::__construct($config);
			}

			public function getUrlApp(): string { return $this->baseUrl; }
			public function getUrlDebrief(int $workflowId): string { return sprintf('%s/api/v1/workspaces/65699/workflows/%d/debrief', $this->baseUrl, $workflowId); }
			public function getUrlPreferences(int $workflowId): string { return sprintf('%s/api/v1/workspaces/65699/workflows/%d/preferences', $this->baseUrl, $workflowId); }
			public function getUrlRecipients(int $workflowId): string { return sprintf('%s/api/v1/workspaces/65699/workflows/%d/roles', $this->baseUrl, $workflowId); }
			public function getUrlSession(int $workflowId): string { return sprintf('%s/api/v1/workspaces/65699/workflows/%d/session', $this->baseUrl, $workflowId); }
			public function getUrlSteps(int $workflowId): string { return sprintf('%s/api/v1/workspaces/65699/workflows/%d/steps', $this->baseUrl, $workflowId); }
			public function getUrlWorkflows(): string { return sprintf('%s/api/v1/workspaces/65699/workflows', $this->baseUrl); }
		};

		$setChildConfiguration = \Closure::bind(
			function (ConfigurationService $fakeConfiguration): void {
				$this->configurationService = $fakeConfiguration;
			},
			$service,
			CurlService::class
		);
		$setChildConfiguration($fakeConfiguration);

		$setBundleConfiguration = \Closure::bind(
			function (ConfigurationService $fakeConfiguration): void {
				$this->configurationServiceBundle = $fakeConfiguration;
			},
			$service,
			\OCA\YumiSignNxtC\RCDevs\Service\CurlService::class
		);
		$setBundleConfiguration($fakeConfiguration);

		return $service;
	}

	/**
	 * Write explicit markers to Nextcloud log so test execution is visible from UI runs.
	 *
	 * @param string $stage
	 * @param array<string, mixed> $context
	 * @return void
	 */
	private function logMarker(string $stage, array $context = []): void
	{
		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);
		$logger->warning(
			sprintf('[RCDevsTesting][YMS_E2E_SIGN] stage=%s', $stage),
			[
				'app' => 'rcdevs_yms_testing',
				'context' => $context,
			]
		);
	}
}
