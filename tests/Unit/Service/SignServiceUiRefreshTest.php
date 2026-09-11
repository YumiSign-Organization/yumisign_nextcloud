<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Service;

use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Service\SignedDocumentService;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Service\CurlService;
use OCA\YumiSignNxtC\Service\SignService;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SignServiceUiRefreshTest extends TestCase
{
	public function testFinalResultTargetsRecordedApplicantAndOnlyLocalRecipients(): void
	{
		$mapper = $this->createMock(TransactionMapper::class);
		$session = new SignSession();
		$session->setApplicantId('applicant');
		$session->setFilePath('/applicant/files/Contracts/source.pdf');
		$mapper->method('findTransaction')->willReturn($session);
		$rows = [];
		foreach (['signer', '', 'applicant'] as $id) {
			$row = new SignSession();
			$row->setRecipientId($id);
			$rows[] = $row;
		}
		$mapper->method('findRecipientsIdsByTransaction')->willReturn($rows);
		$users = $this->createMock(IUserManager::class);
		$users->method('userExists')->willReturnCallback(static fn ($uid) => in_array($uid, ['applicant', 'signer'], true));
		$config = $this->createMock(ConfigurationService::class);
		$config->method('getUrlArchive')->willReturn('https://sign.example/archive/');
		$config->method('getAppNameSigned')->willReturn('signed');
		$result = new CurlEntity();
		foreach (['response' => '%PDF-final', 'code' => 200] as $key => $value) {
			(new \ReflectionProperty(CurlEntity::class, $key))->setValue($result, $value);
		}
		$curl = $this->createMock(CurlService::class);
		$curl->method('getDocument')->willReturn($result);
		$delivery = $this->createMock(SignedDocumentService::class);
		$delivery->expects(self::once())->method('rememberSource');
		$delivery->expects(self::once())->method('enqueue')->willReturnCallback(static function ($reference, $participants, $documents): void {
			self::assertSame('workflow', $reference);
			self::assertSame(['applicant' => ['applicant', 'recipient'], 'signer' => ['recipient']], $participants);
			self::assertSame('%PDF-final', $documents[0]['content']);
			self::assertStringStartsWith('source_signed_', $documents[0]['name']);
		});
		$delivery->method('deliver')->willReturn(true);
		$service = $this->service(['mapper' => $mapper, 'userManager' => $users, 'configurationService' => $config, 'curlService' => $curl, 'signedDocuments' => $delivery]);
		$response = $service->saveTransactionFiles(['id' => 'workflow', 'status' => 'signed', 'documents' => [['file' => ['file' => 'https://sign.example/archive/final.pdf']]]], 'another-current-user', 'test');
		self::assertTrue($response['saved']);
	}

	public function testIndividualSignatureDoesNotStartDelivery(): void
	{
		$delivery = $this->createMock(SignedDocumentService::class);
		$delivery->expects(self::never())->method('enqueue');
		$delivery->expects(self::never())->method('known');
		$service = $this->service(['signedDocuments' => $delivery]);
		$response = $service->saveTransactionFiles(['id' => 'workflow', 'status' => 'started', 'steps' => [['actions' => [['status' => 'signed']]]]], 'applicant', 'test');
		self::assertFalse($response['saved']);
	}

	public function testRepeatedCallbackUsesRetainedDocumentAndReturnsPendingOnFailure(): void
	{
		$delivery = $this->createMock(SignedDocumentService::class);
		$delivery->method('known')->willReturn(true);
		$delivery->expects(self::never())->method('enqueue');
		$delivery->expects(self::once())->method('deliver')->with('workflow')->willReturn(false);
		$service = $this->service(['signedDocuments' => $delivery]);
		self::assertFalse($service->saveTransactionFiles(['id' => 'workflow', 'status' => 'signed'], 'applicant', 'test')['saved']);
	}

	private function service(array $properties): SignService
	{
		$reflection = new ReflectionClass(SignService::class);
		$service = $reflection->newInstanceWithoutConstructor();
		$properties['logRCDevs'] = $this->createMock(LogRCDevs::class);
		foreach ($properties as $name => $value) {
			$reflection->getProperty($name)->setValue($service, $value);
		}
		return $service;
	}
}
