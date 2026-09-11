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

namespace OCA\YumiSignNxtC\Service;

// RCDevs App
use OCA\YumiSignNxtC\Constant\CstCommon;
use OCA\YumiSignNxtC\Constant\CstCurl;
use OCA\YumiSignNxtC\Constant\CstRequest;
use OCA\YumiSignNxtC\Constant\CstReturn;
use OCA\YumiSignNxtC\Constant\CstTransactionType;
use OCA\YumiSignNxtC\Db\TransactionMapper;
use OCA\YumiSignNxtC\Service\CurlService;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Db\JobMapper;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Service\SettingsService as RCDevsSettingsService;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;

// Nextcloud Core
use OC\URLGenerator;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use Throwable;

class SettingsService extends RCDevsSettingsService
{
	private		ConfigurationService	$configurationService;
	protected	CurlService				$curlService;

	public function __construct(
		private IAccountManager $accountManager,
		private IAppConfig $config,
		private IConfig $systemConfig,
		private IDateTimeFormatter $formatter,
		private IL10N $l10nYmsSettingsService,
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private JobMapper $jobMapper,
		private TransactionMapper $mapper,
		private URLGenerator $urlGenerator,
		protected LogRCDevs $logRCDevs,
		string $UserId
	) {
		parent::__construct(
			$config,
			$l10nYmsSettingsService,
			$jobMapper,
			$logRCDevs,
			$UserId,
		);

		$this->configurationService = new ConfigurationService($config);

		$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		$this->curlService = new CurlService($config, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);
	}

	/** ******************************************************************************************
	 * PRIVATE
	 ****************************************************************************************** */
	private function checkWspCommon(
		array $unitWorkspace,
		string $workspaceName,
		string $workspaceId
	) {
		if (empty($workspaceId)) {
			return (Helpers::areEqual(
				Helpers::getArrayData($unitWorkspace, CstCommon::NAME, missingForbidden: true),
				$workspaceName
			));
		} else {
			return (Helpers::areEqual(
				Helpers::getArrayData($unitWorkspace, CstCommon::NAME, missingForbidden: true),
				$workspaceName
			) &&
				Helpers::getArrayData($unitWorkspace, CstRequest::ID, missingForbidden: true) === intval($workspaceId));
		}
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */
	public function checkServerUrl(
		string $serverUrl
	): array {
		$cdem = new CDEM();

		try {
			$this->logRCDevs->debug(
				sprintf('$serverUrl: %s', $serverUrl),
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);

			$this->curlService->setCurlEnv($serverUrl, CstCurl::GET, false);
			$this->curlService->setOpt(CURLOPT_TIMEOUT, 10);
			$this->curlService->setOpt(CURLOPT_VERBOSE, true);

			$curlEntity = $this->curlService->getCurlResponse();
			$this->logRCDevs->debug(
				sprintf('checkServerUrl raw response: [%s]', $curlEntity->getBody()),
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);

			$this->curlService->closeHandle();

			$this->curlService->checkCurlCode(
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);
			$this->curlService->checkCurlBody(
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);

			$cdem->setOk(
				data: [
					CstCommon::NAME => CstCommon::CONNECTED,
					CstCommon::STATUS => true,
				],
				message: CstCommon::CONNECTED,
			);
		} catch (\Throwable $th) {
			$this->logRCDevs->error(
				$th->getMessage(),
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);
			$cdem->setWarning(
				exception: $th,
				logRCDevs: $this->logRCDevs,
				data: [
					CstCommon::NAME => null,
					CstCommon::STATUS => false,
				],
			);
		}

		return $cdem->toArray();
	}

	public function checkWorkspace(
		string $serverUrl,
		string $workspaceName,
		string $workspaceId
	) {
		$returned = [
			CstReturn::CODE => false,
			CstCommon::STATUS => false,
			CstReturn::MESSAGE => $this->l10nYmsSettingsService->t('No workspace found'),
		];
		$returned[CstCommon::LIST_ID] = [];
		$returned[CstRequest::ID] = '';

		try {
			// Init cUrl
			$this->curlService->setCurlEnv($serverUrl, CstCurl::GET, false);
			$this->curlService->setOpt(CURLOPT_TIMEOUT, 10);
			$this->curlService->setOpt(CURLOPT_VERBOSE, true);

			// Call YumiSign server
			$curlResponse = $this->curlService->getCurlResponse();

			$this->curlService->closeHandle();

			$workspaces = json_decode($curlResponse->getBody(), true);
			if (is_array($workspaces) && isset($workspaces['error']['message'])) {
				throw new \RuntimeException((string) $workspaces['error']['message']);
			}

			// Check response validity
			$this->curlService->checkCurlCode(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->curlService->checkCurlBody(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			$found = false;
			$workspaces = json_decode($curlResponse->getBody(), true);

			// Verify received intel from YumiSign server
			foreach ($workspaces as $unitWorkspace) {
				if (
					$this->checkWspCommon($unitWorkspace, $workspaceName, $workspaceId)
				) {
					// Common behaviour based on Name value
					$found = true;
					$returned[CstReturn::CODE] = $returned[CstCommon::STATUS] = true;
					$returned[CstCommon::NAME] = $returned[CstReturn::MESSAGE] = Helpers::getArrayData($unitWorkspace, CstCommon::NAME, true);

					// According to the ID value
					if (!empty($workspaceId)) {
						// Name & ID are filled => found this pair
						$returned[CstRequest::ID] = Helpers::getArrayData($unitWorkspace, CstRequest::ID, true);
						break;
					} else {
						// Just a valid Name but maybe several exist => add to the IDs list
						$returned[CstCommon::LIST_ID][] = Helpers::getArrayData($unitWorkspace, CstRequest::ID, true);
					}
				}
			}

			// If only one ID is found, convert IDs list to unique ID
			if (sizeof($returned[CstCommon::LIST_ID]) === 1) {
				$returned[CstRequest::ID] = $returned[CstCommon::LIST_ID][0];
			}

			if (!$found) {
				$returned[CstReturn::MESSAGE] = (string) $this->l10nYmsSettingsService->t(
					'The workspace named "%s" was not found on YumiSign server',
					[$workspaceName],
				);
				$this->logRCDevs->info($returned[CstReturn::MESSAGE], __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$returned = [
				CstCommon::LIST_ID	=> [],
				CstCommon::STATUS	=> false,
				CstReturn::CODE	=> false,
				CstReturn::ERROR	=> $th->getCode(),
				CstRequest::ID		=> '',
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $returned;
	}
}
