<?php

/**
 *
 * @copyright Copyright (c) 2025, RCDevs (info@rcdevs.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Controller;

// RCDevs App
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\Service\TransactionsService;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class WebhookController extends Controller
{
	public function __construct(
		string $AppName,
		IRequest $request,
		private TransactionMapper $mapper,
		private TransactionsService $transactionsService,
		protected LogRCDevs $logRCDevs
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function receive(): JSONResponse
	{
		$payload = $this->request->getParams();
		if (!is_array($payload) || count($payload) === 0 || (count($payload) === 1 && isset($payload['_route']))) {
			$raw = file_get_contents('php://input');
			$decoded = json_decode((string) $raw, true);
			$payload = is_array($decoded) ? $decoded : [];
		}

		$headerYumiSign = (string) ($this->request->getHeader('YumiSign-Signature')
			?: $this->request->getHeader('YUMISIGN-SIGNATURE')
			?: '');

		$envelopeId = trim((string) ($payload[CstRequest::ID] ?? ''));
		$userId = $envelopeId === '' ? null : $this->mapper->getUserIdByEnvelopeId($envelopeId);

		$result = $this->transactionsService->webhook($headerYumiSign, $payload, $userId);

		return new JSONResponse([
			CstReturn::CODE => $result[CstReturn::CODE] ?? false,
			CstReturn::MESSAGE => $result[CstReturn::MESSAGE] ?? null,
			CstRequest::STATUS => $result[CstRequest::STATUS] ?? null,
		]);
	}
}
