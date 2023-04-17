<?php

/**
 *
 * @copyright Copyright (c) 2023, RCDevs (info@rcdevs.com)
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

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;

use OCA\YumiSignNxtC\Service\SignService;
use OCA\YumiSignNxtC\Utility\LogYumiSign;

class SettingsController extends Controller
{

	private $config;
	private $signService;

	public function __construct($AppName, IRequest $request, IConfig $config, SignService $signService)
	{
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->signService = $signService;
	}

	public function checkCronStatus()
	{
		$resp = $this->signService->lastJobRun();

		return new JSONResponse([
			YMS_CODE	=> $resp[YMS_CODE],
			YMS_STATUS	=> $resp[YMS_STATUS],
			YMS_MESSAGE => $resp[YMS_MESSAGE]
		]);
	}

	public function checkServerUrl()
	{
		$resp = $this->signService->checkServerUrl($this->request);

		return new JSONResponse([
			YMS_CODE	=> $resp[YMS_CODE],
			YMS_STATUS	=> $resp[YMS_STATUS],
			YMS_ID		=> $resp[YMS_ID],
			YMS_MESSAGE => $resp[YMS_MESSAGE]
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkSettings()
	{
		$serverUrl = $this->config->getAppValue('yumisign_nextcloud', 'server_url');
		$empty = empty($serverUrl);

		return new JSONResponse(!$empty);
	}

	public function checkWorkspace()
	{
		$resp = $this->signService->checkWorkspace($this->request);

		return new JSONResponse([
			YMS_CODE	=> $resp[YMS_CODE],
			YMS_STATUS	=> $resp[YMS_STATUS],
			YMS_ID		=> $resp[YMS_ID],
			YMS_LIST_ID	=> $resp[YMS_LIST_ID],
			YMS_MESSAGE => $resp[YMS_MESSAGE]
		]);
	}

	public function resetJob()
	{
		$resp = $this->signService->resetJob();

		return new JSONResponse([
			YMS_CODE	=> $resp[YMS_CODE],
			YMS_STATUS	=> $resp[YMS_STATUS],
			YMS_MESSAGE => $resp[YMS_MESSAGE]
		]);
	}

	public function saveSettings()
	{
		$this->config->setAppValue('yumisign_nextcloud', 'api_key',			$this->request->getParam('api_key'));
		$this->config->setAppValue('yumisign_nextcloud', 'async_timeout',	$this->request->getParam('async_timeout'));
		$this->config->setAppValue('yumisign_nextcloud', 'cron_interval',	$this->request->getParam('cron_interval'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_host',		$this->request->getParam('proxy_host'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_password',	$this->request->getParam('proxy_password'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_port',		$this->request->getParam('proxy_port'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_username',	$this->request->getParam('proxy_username'));
		$this->config->setAppValue('yumisign_nextcloud', 'server_url',		rtrim($this->request->getParam('server_url'), '/'));
		$this->config->setAppValue('yumisign_nextcloud', 'sign_scope',		$this->request->getParam('sign_scope'));
		$this->config->setAppValue('yumisign_nextcloud', 'use_proxy',		$this->request->getParam('use_proxy'));
		$this->config->setAppValue('yumisign_nextcloud', 'workspace_id',	$this->request->getParam('workspace_id'));
		$this->config->setAppValue('yumisign_nextcloud', 'workspace_name',	$this->request->getParam('workspace_name'));
		$this->config->setAppValue('yumisign_nextcloud', 'description',		$this->request->getParam('description'));

		return new JSONResponse([
			'code' => 1,
		]);
	}
}
