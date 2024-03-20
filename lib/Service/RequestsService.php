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

namespace OCA\YumiSignNxtC\Service;

use CURLFile;
use CurlHandle;
use DateInterval;
use DateTime;
use Exception;
use FileInfo;
use GuzzleHttp\Handler\CurlHandler;
use nusoap_client;

use OCA\YumiSignNxtC\AppInfo\Application as YumiSignApp;
use OCA\YumiSignNxtC\Commands\GetsFile;
use OCA\YumiSignNxtC\Db\SignSession;
use OCA\YumiSignNxtC\Db\SignSessionMapper;
use OCA\YumiSignNxtC\Utility\LogYumiSign;
use OCA\YumiSignNxtC\Utility\Utility;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OC\Files\Filesystem;
use OC\Files\Mount\Manager;
use OC\Files\Node\Folder;
use OC\Files\Node\File;
use OC\Files\Mount\MountPoint;
use OCP\IDateTimeFormatter;
use OCP\L10N\IFactory;

use function OCP\Log\logger;
use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class RequestsService
{
	// use GetsFile;

	const CNX_TIME_OUT = 3;

	// Settings
	private $apiKey;
	private $asyncTimeout;
	private $defaultDomain;
	private $description;
	private $enableDemoMode;
	private $proxyHost;
	private $proxyPassword;
	private $proxyPort;
	private $proxyUsername;
	private $serverUrl;
	private $signedFile;
	private $signScope;
	private $syncTimeout;
	private $temporaryFolder;
	private $useProxy;
	private $userSettings;
	private $watermarkText;
	private int $workspaceId;
	private $workspaceName;

	public function __construct(
		private IAccountManager $accountManager,
		private IConfig $config,
		private IL10N $l,
		private IRootFolder $storage,
		private IUserManager $userManager,
		private SignSessionMapper $mapper,
		private IFactory $l10nFactory,
		private IConfig $systemConfig,
		private IL10N $l10n,
		private IDateTimeFormatter $formatter,
		private LogYumiSign $logYumiSign,
		private SignService $signService,
		private $UserId,
	) {

		Utility::$l = $l;

		$this->apiKey           = $config->getAppValue(YumiSignApp::APP_ID, 'api_key');
		$this->asyncTimeout     = (int) $config->getAppValue(YumiSignApp::APP_ID, 'async_timeout'); // in days
		$this->defaultDomain    = $config->getAppValue(YumiSignApp::APP_ID, 'default_domain');
		$this->description      = $config->getAppValue(YumiSignApp::APP_ID, 'description');
		$this->enableDemoMode   = $config->getAppValue(YumiSignApp::APP_ID, 'enable_demo_mode');
		$this->proxyHost        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_host')     : false);
		$this->proxyPassword    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_password') : false);
		$this->proxyPort        = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_port')     : false);
		$this->proxyUsername    = ($this->useProxy ? $config->getAppValue(YumiSignApp::APP_ID, 'proxy_username') : false);
		$this->serverUrl        = $config->getAppValue(YumiSignApp::APP_ID, 'server_url');
		$this->signedFile       = $config->getAppValue(YumiSignApp::APP_ID, 'signed_file');
		$this->signScope        = $config->getAppValue(YumiSignApp::APP_ID, 'sign_scope', 'qualified');
		$this->syncTimeout      = (int) $config->getAppValue(YumiSignApp::APP_ID, 'sync_timeout') * 60;
		$this->temporaryFolder  = rtrim($config->getSystemValue('tempdirectory', rtrim(sys_get_temp_dir(), '/') . '/nextcloudtemp', '/')) . '/';
		$this->useProxy         = $config->getAppValue(YumiSignApp::APP_ID, 'use_proxy');
		$this->userSettings     = $config->getAppValue(YumiSignApp::APP_ID, 'user_settings');
		$this->watermarkText    = $config->getAppValue(YumiSignApp::APP_ID, 'watermark_text');
		$this->workspaceId      = intval($config->getAppValue(YumiSignApp::APP_ID, 'workspace_id'));
		$this->workspaceName    = $config->getAppValue(YumiSignApp::APP_ID, 'workspace_name');
	}

	public function getPendingRequests(string $userId, int $page, int $nbItems)
	{
		$rightNow = intval(time());

		try {
			// Retrieve all WFW from YMS and update global_status and status according to the records
			$this->signService->checkAsyncSignatureTask($userId); // Nothing to return, the records will be gathered with the next DB request

			$databaseResponse = $this->mapper->findPendingsByApplicant($rightNow, $userId, $page, $nbItems);

			$requests = [];
			foreach ($databaseResponse as $databaseRecord) {
				$requests[] = [
					'id'	=> $databaseRecord->getId(),
					'created'	=> $databaseRecord->getCreated(),
					'expiry_date'	=> $databaseRecord->getExpiryDate(),
					'recipient'	=> $databaseRecord->getRecipient(),
					'file_path'	=> basename($databaseRecord->getFilePath()),
					'envelope_id'	=> $databaseRecord->getEnvelopeId(),
					'status'	=> $databaseRecord->getStatus(),
				];
			}

			$returned = [
				'count' => count($requests),
				'requests' => $requests,
			];
		} catch (\Throwable $th) {
			$this->logYumiSign->error($th->getMessage(), __FUNCTION__);
			$returned = [
				'count' => 0,
				'requests' => [],
			];
		}

		return $returned;
	}

	public function getIssuesRequests(string $userId, int $page, int $nbItems)
	{
		
	}

}
