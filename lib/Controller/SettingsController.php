<?php
/**
 *
 * @copyright Copyright (c) 2021, RCDevs (info@rcdevs.com)
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

class SettingsController extends Controller {

	private $config;
	private $signService;

	public function __construct($AppName, IRequest $request, IConfig $config, SignService $signService){
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->signService = $signService;
	}

	public function saveSettings() {
		// Remove trailing slash in servers urls
		$serversUrls = $this->request->getParam('server_urls');
		foreach ($serversUrls as $idxServer => $serverUrl) {
			$serversUrls[$idxServer] = rtrim($serverUrl, '/');
		}

		// $this->config->setAppValue('yumisign_nextcloud', 'server_urls', json_encode($this->request->getParam('server_urls')));
		$this->config->setAppValue('yumisign_nextcloud', 'server_urls', json_encode($serversUrls));
		$this->config->setAppValue('yumisign_nextcloud', 'api_key', $this->request->getParam('api_key'));
		$this->config->setAppValue('yumisign_nextcloud', 'use_proxy', $this->request->getParam('use_proxy'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_host', $this->request->getParam('proxy_host'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_port', $this->request->getParam('proxy_port'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_username', $this->request->getParam('proxy_username'));
		$this->config->setAppValue('yumisign_nextcloud', 'proxy_password', $this->request->getParam('proxy_password'));
		$this->config->setAppValue('yumisign_nextcloud', 'sign_scope', $this->request->getParam('sign_scope'));
		$this->config->setAppValue('yumisign_nextcloud', 'async_timeout', $this->request->getParam('async_timeout'));
		$this->config->setAppValue('yumisign_nextcloud', 'cron_interval', $this->request->getParam('cron_interval'));

		return new JSONResponse([
			'code' => 1,
		]);
	}

	public function checkServerUrl() {
		$resp = $this->signService->yumisignStatus($this->request);

		if (isset($resp['status'])) {
			return new JSONResponse([
				'status' => $resp['status'],
				'message' => $resp['message']
			]);
		}

		return new JSONResponse([
			'status' => 'false',
			'message' => ''
		]);
	}

		/**
	 * @NoAdminRequired
	 */
	public function checkSettings() {
		$serverUrls = json_decode($this->config->getAppValue('yumisign_nextcloud', 'server_urls', '[]'));
		$empty = true;

		foreach ($serverUrls as &$serverUrl) {
			if (!empty($serverUrl)) {
				$empty = false;
				break;
			}
		}

		return new JSONResponse(!$empty);
	}
}
