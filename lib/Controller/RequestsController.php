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


use Exception;
use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCA\YumiSignNxtC\Service\CurlResponse;
use OCA\YumiSignNxtC\Service\RequestsService;
use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\Utility;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Share\IShare;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse as HttpFoundationJsonResponse;

class RequestsController extends Controller
{

	public function __construct(
		$AppName,
		IRequest $request,
		private string $userId,
		private SignSessionMapper $mapper,
		private RequestsService $requestsService,
		private SignService $signService,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getPendingRequests(int $page = 0, int $nbItems = 20)
	{
		$returned = [
			'count' => 0,
			'requests' => [],
		];

		try {
			if ($nbItems == 0) {
				$nbItems = 20;
			}

			$returned = $this->requestsService->getPendingRequests($this->userId, $page, $nbItems);
		} catch (\Throwable $th) {
			$returned = [
				'count' => 0,
				'requests' => null,
			];
		}
		return new JSONResponse($returned);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getIssuesRequests(int $page = 0, int $nbItems = 20)
	{
		if ($nbItems == 0) {
			$nbItems = 20;
		}
		// Retrieve all WFW from YMS and update global_status and status according to the records
		$this->signService->checkAsyncSignatureTask($this->userId);

		$count = $this->mapper->countIssuesByApplicant($this->userId);
		$requests = $this->mapper->findIssuesByApplicant($this->userId, $page, $nbItems);

		// Change fullPath to basename
		$requests = json_decode(json_encode($requests), true);
		foreach ($requests as $keyRequest => $unitRequest) {
			$requests[$keyRequest]['file_path'] = basename($unitRequest['file_path']);
		}

		return new JSONResponse([
			'count' => $count,
			'requests' => $requests,
		]);
	}
}
