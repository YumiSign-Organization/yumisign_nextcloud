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

use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;

use OCA\YumiSignNxtC\Service\SignService;

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
			Constante::get(Cst::CODE)	 		=> $resp[Constante::get(Cst::CODE)],
			Constante::get(Cst::YMS_STATUS)	 	=> $resp[Constante::get(Cst::YMS_STATUS)],
			Constante::get(Cst::YMS_MESSAGE) 	=> $resp[Constante::get(Cst::YMS_MESSAGE)]
		]);
	}

	public function checkServerUrl()
	{
		// $resp = $this->signService->checkServerUrl($this->request);
		$resp = $this->signService->checkSettings();

		return new JSONResponse([
			Constante::get(Cst::CODE)	 		=> $resp[Constante::get(Cst::CODE)],
			Constante::get(Cst::YMS_STATUS)	 	=> $resp[Constante::get(Cst::YMS_STATUS)],
			Constante::get(Cst::YMS_ID)		 	=> $resp[Constante::get(Cst::YMS_ID)],
			Constante::get(Cst::YMS_MESSAGE) 	=> $resp[Constante::get(Cst::YMS_MESSAGE)]
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function checkSettings()
	{
		$resp = $this->signService->checkSettings();

		return new JSONResponse([
			Constante::get(Cst::CODE)			=> $resp[Constante::get(Cst::CODE)],
			Constante::get(Cst::YMS_STATUS)	 	=> $resp[Constante::get(Cst::YMS_STATUS)],
			Constante::get(Cst::YMS_ID)		 	=> $resp[Constante::get(Cst::YMS_ID)],
			Constante::get(Cst::YMS_MESSAGE) 	=> $resp[Constante::get(Cst::YMS_MESSAGE)]
		]);
	}

	public function checkWorkspace()
	{
		$resp = $this->signService->checkWorkspace($this->request);

		return new JSONResponse([
			Constante::get(Cst::CODE)	 		=> $resp[Constante::get(Cst::CODE)],
			Constante::get(Cst::YMS_STATUS)	 	=> $resp[Constante::get(Cst::YMS_STATUS)],
			Constante::get(Cst::YMS_ID)		 	=> $resp[Constante::get(Cst::YMS_ID)],
			Constante::get(Cst::YMS_LIST_ID) 	=> $resp[Constante::get(Cst::YMS_LIST_ID)],
			Constante::get(Cst::YMS_MESSAGE) 	=> $resp[Constante::get(Cst::YMS_MESSAGE)]
		]);
	}

	public function resetJob()
	{
		$resp = $this->signService->resetJob();

		return new JSONResponse([
			Constante::get(Cst::CODE)	 		=> $resp[Constante::get(Cst::CODE)],
			Constante::get(Cst::YMS_STATUS)		=> $resp[Constante::get(Cst::YMS_STATUS)],
			Constante::get(Cst::YMS_MESSAGE)	=> $resp[Constante::get(Cst::YMS_MESSAGE)]
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
		$this->config->setAppValue('yumisign_nextcloud', 'server_url',		'https://app.yumisign.com:443/api/v1');
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
