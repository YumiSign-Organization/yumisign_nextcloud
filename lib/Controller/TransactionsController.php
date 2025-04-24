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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Controller;

use OCA\RCDevs\Utility\LogRCDevs;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Service\TransactionsService;
use OCA\YumiSignNxtC\Utility\Constantes\CstDatabase;
use OCA\YumiSignNxtC\Utility\Constantes\CstException;
use OCA\YumiSignNxtC\Utility\Constantes\CstRequest;
use OCA\YumiSignNxtC\Utility\Constantes\CstStatus;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager;

class TransactionsController extends Controller
{
	protected	IManager		$notificationManager;
	protected	ITimeFactory	$timeFactory;
	protected	IUserManager	$userManager;

	public function __construct(
		$AppName,
		IManager						$notificationManager,
		IRequest						$request,
		ITimeFactory					$timeFactory,
		IUserManager					$userManager,
		private		SignSessionMapper	$mapper,
		private		string				$userId,
		private		TransactionsService	$transactionsService,
		protected	LogRCDevs			$logRCDevs,
	) {
		parent::__construct($AppName, $request);

		$this->timeFactory = $timeFactory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */

	private function commonGetTransactions(int $page, int $nbItems, string $status): JSONResponse
	{
		$data = [
			CstDatabase::COUNT => 0,
			'transactions' => [],
		];

		try {
			$status = ucfirst($status);
			$functionToRun = "getTransactions{$status}";

			if ($nbItems == 0) {
				$nbItems = 20;
			}

			$data = $this->transactionsService->$functionToRun($this->userId, $page, $nbItems);

			$returned = [
				CstRequest::CODE	=> 1,
				CstRequest::DATA	=> $data,
				CstRequest::ERROR	=> null,
				CstRequest::MESSAGE	=> null,
			];

			$this->logRCDevs->debug(json_encode($data), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
		} catch (\Throwable $th) {
			$data = [
				CstDatabase::COUNT => 0,
				'transactions' => null,
			];
			$returned = [
				CstRequest::CODE	=> 0,
				CstRequest::DATA	=> $data,
				CstRequest::ERROR	=> $th->getCode(),
				CstRequest::MESSAGE	=> CstException::QUERY_TRANSACTION,
			];
		}
		return new JSONResponse($returned);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	/**
	 * @NoAdminRequired
	 */
	public function cancelTransaction()
	{
		$resp = $this->transactionsService->cancelTransaction(
			$this->request->getParam('envelopeId'),
			$this->userId,
			forceDeletion: false,
		);

		return new JSONResponse([
			'code' => $resp['code'],
			'message' => $resp['message']
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteTransaction()
	{
		$recipient = ($this->request->getParam(CstRequest::RECIPIENT) ?? '');
		$resp = $this->transactionsService->cancelTransaction(
			$this->request->getParam('envelopeId'),
			$this->userId,
			forceDeletion: true,
			recipient: $recipient,
		);

		return new JSONResponse([
			'code' => strval($resp['code']),
			'message' => $resp['message']
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTransactionsCompleted(int $page = 0, int $nbItems = 20)
	{
		return $this->commonGetTransactions($page, $nbItems, CstStatus::COMPLETED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTransactionsDeclined(int $page = 0, int $nbItems = 20)
	{
		return $this->commonGetTransactions($page, $nbItems, CstStatus::DECLINED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTransactionsExpired(int $page = 0, int $nbItems = 20)
	{
		return $this->commonGetTransactions($page, $nbItems, CstStatus::EXPIRED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTransactionsFailed(int $page = 0, int $nbItems = 20)
	{
		return $this->commonGetTransactions($page, $nbItems, CstStatus::FAILED);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTransactionsPending(int $page = 0, int $nbItems = 20)
	{
		return $this->commonGetTransactions($page, $nbItems, CstStatus::PENDING);
	}
}
