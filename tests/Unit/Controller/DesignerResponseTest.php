<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Unit\Controller;

use OCA\YumiSignNxtC\Controller\SignController;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Entity\UserEntity;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\RequestResponse;
use OCA\YumiSignNxtC\RCDevs\Utility\SignatureType;
use OCA\YumiSignNxtC\Service\SignService;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DesignerResponseTest extends TestCase
{
	public function testDesignerLaunchDataIsReturnedInsideCdemData(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn ($key) => [
			'recipientEmail' => 'recipient@example.com', 'path' => '/file.pdf', 'fileId' => 42,
		][$key] ?? null);
		$response = new CDEM();
		$response->fromArray(['code' => 0, 'data' => [
			'workspaceId' => 1, 'workflowId' => 2, 'envelopeId' => 3,
			'response' => new RequestResponse(['designerUrl' => 'https://example.com/designer?session=test']),
		], 'error' => null, 'message' => 'OpenDesigner']);
		$service = $this->createMock(SignService::class);
		$service->expects(self::once())->method('signLocalAsyncPrepare')->willReturn($response);
		$reflection = new ReflectionClass(SignController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach ([
			'request' => $request,
			'config' => $this->createMock(IConfig::class),
			'rootFolder' => $this->createMock(IRootFolder::class),
			'userManager' => $this->createMock(IUserManager::class),
			'applicant' => $this->createMock(UserEntity::class),
			'logRCDevs' => $this->createMock(LogRCDevs::class),
			'signService' => $service,
		] as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		$method = $reflection->getMethod('commonSignLocalAsync');
		$method->setAccessible(true);
		$result = $method->invoke($controller, new SignatureType(standard: true));
		self::assertSame(0, $result['code']);
		self::assertSame([
			'designerUrl' => 'https://example.com/designer?session=test',
			'envelopeId' => 3, 'workflowId' => 2, 'workspaceId' => 1,
		], $result['data']);
	}
}
