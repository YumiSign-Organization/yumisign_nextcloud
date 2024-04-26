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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Controller;

use OC\User\Backend;
use \OCP\AppFramework\Http\RedirectResponse;
use Exception;
use OC\AppFramework\Http;
use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\CurlResponse;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\Utility;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Share\IShare;
use OCP\Util;
use Psr\Log\LoggerInterface;

class UserController extends Controller
{

	/** @var IUserManager */

	public function __construct(
		private IAppManager $appManager,
		$AppName,
		IRequest $request,
		private string $userId,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private ISearch $search,
		private IUserSession $userSession
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getCurrentUserId()
	{
		return $this->userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getCurrentUserEmail()
	{
		$returned = '';

		try {
			$user = $this->userManager->get($this->userId);
			$returned = $user->getEMailAddress();
		} catch (\Throwable $th) {
			$returned = '';
		}
		// $user = $this->userSession->getUser();
		return $returned;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getLocalUsers()
	{
		$cm = \OC::$server->getContactsManager();

		// The API is not active -> nothing to do
		if (!$cm->isEnabled()) {
			$this->logger->error('Contact Manager not enabled');
			return new JSONResponse();
		}

		$result = $cm->search($this->request->getParam('search'), array('FN', 'EMAIL'));

		$contacts = array();
		foreach ($result as $raw_contact) {
			$contact = array();
			$contact['uid'] = $raw_contact['UID'];
			$contact['display_name'] = $raw_contact['FN'];

			if (array_key_exists('EMAIL', $raw_contact)) {
				$contact['email'] = $raw_contact['EMAIL'][0];
			}

			array_push($contacts, $contact);
		}

		return new JSONResponse($contacts);
	}
}
