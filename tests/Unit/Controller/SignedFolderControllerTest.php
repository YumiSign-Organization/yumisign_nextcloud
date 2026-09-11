<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Controller;

use OCA\YumiSignNxtC\Controller\SignedFolderController;
use OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SignedFolderControllerTest extends TestCase
{
	public function testSettingsOnlyUseAuthenticatedUserAndKeepInvalidValuesEditable(): void
	{
		$service = $this->createMock(SignedFolderService::class);
		$service->expects(self::once())->method('values')->with('signer')->willReturn(['applicant' => '', 'recipient' => 'R']);
		$service->method('validate')->willThrowException(new \RuntimeException('Applicant path missing'));
		$service->expects(self::once())->method('save')->with('signer', 'New applicant', 'R')->willReturn(['applicant' => 'New applicant', 'recipient' => 'R']);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$controller = new SignedFolderController('yumisign_nextcloud', $this->createMock(IRequest::class), $service, $session);
		self::assertSame('Applicant path missing', $controller->read()->getData()['error']);
		self::assertSame('New applicant', $controller->save('New applicant', 'R')->getData()['values']['applicant']);
	}

	public function testAnonymousSettingsRequestCannotReadOrWritePreferences(): void
	{
		$service = $this->createMock(SignedFolderService::class);
		$service->expects(self::never())->method('save');
		$service->expects(self::never())->method('values');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$controller = new SignedFolderController('yumisign_nextcloud', $this->createMock(IRequest::class), $service, $session);
		self::assertSame(401, $controller->save('A', 'R')->getStatus());
		self::assertSame(401, $controller->read()->getStatus());
	}
}
