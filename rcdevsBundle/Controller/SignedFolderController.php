<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Controller;

use OCA\YumiSignNxtC\RCDevs\Service\SignedFolderService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SignedFolderController extends Controller
{
	public function __construct(string $appName, IRequest $request, private SignedFolderService $folders, private IUserSession $session) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function read(): JSONResponse
	{
		$uid = $this->session->getUser()?->getUID();
		if ($uid === null) {
			return new JSONResponse(['message' => 'Authentication required'], 401);
		}
		$values = $this->folders->values($uid);
		try {
			$this->folders->validate($uid, $values);
			$error = null;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
		}
		return new JSONResponse(['values' => $values, 'error' => $error]);
	}

	#[NoAdminRequired]
	public function save(string $applicant = '', string $recipient = ''): JSONResponse
	{
		$uid = $this->session->getUser()?->getUID();
		if ($uid === null) {
			return new JSONResponse(['message' => 'Authentication required'], 401);
		}
		try {
			return new JSONResponse(['values' => $this->folders->save($uid, $applicant, $recipient), 'error' => null]);
		} catch (\Throwable $e) {
			return new JSONResponse(['message' => $e->getMessage()], 400);
		}
	}
}
