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

namespace OCA\RCDevs\Service;

use Exception;
use OCA\RCDevs\Db\SignSessionMapper;
use OCA\RCDevs\Entity\CurlEntity;
use OCA\RCDevs\Utility\Constantes\CstCommon;
use OCA\RCDevs\Utility\Constantes\CstCurl;
use OCA\RCDevs\Utility\Constantes\CstException;
use OCA\RCDevs\Utility\Constantes\CstRequest;
use OCA\RCDevs\Utility\Helpers;
use OCA\RCDevs\Utility\LogRCDevs;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCP\IConfig;
use OCP\IL10N;

class SettingsService
{
	private	ConfigurationService	$configurationService;
	private	CurlService				$curlService;
	private string					$userId;

	public function __construct(
		IConfig							$config,
		private		IL10N				$l10nRcdevsSettingsService,
		private		SignSessionMapper	$mapper,
		protected	LogRCDevs			$logRCDevs,
		string							$UserId,
	) {
		$this->userId = $UserId;
		$this->configurationService = new ConfigurationService($config);

		$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		$this->curlService = new CurlService($config, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	public function checkServerUrl(string $serverUrl): array
	{
		$return = [];

		try {
			$this->logRCDevs->debug(sprintf('$serverUrl: %s', $serverUrl), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// Init cUrl
			$this->curlService->setCurlEnv($serverUrl, CstCurl::GET, false);
			$this->curlService->setOpt(CURLOPT_TIMEOUT, 10);
			$this->curlService->setOpt(CURLOPT_VERBOSE, true);

			// Call Signature server
			/** @var CurlEntity $curlEntity */
			// $curlEntity = new CurlEntity();
			$curlEntity = $this->curlService->getCurlResponse();

			$this->curlService->closeHandle();

			// Check response validity
			$this->curlService->checkCurlCode(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->curlService->checkCurlBody(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// At this point, this response is OK
			$return[CstCommon::NAME]			= CstCommon::CONNECTED;
			$return[CstCommon::STATUS]			= true;
			$return[CstRequest::CODE]		= 1;
			$return[CstRequest::MESSAGE]	= CstCommon::CONNECTED;
		} catch (\Throwable $th) {
			$thCode = CstException::CONNECTION_ERROR;
			$this->logRCDevs->error(vsprintf('%s [%s]', [$thCode, $th->getMessage()]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = [];
			$return[CstCommon::NAME]			= null;
			$return[CstCommon::STATUS]			= false;
			$return[CstRequest::CODE]		= 0;
			$return[CstRequest::MESSAGE]	= $thCode;
		}

		return $return;
	}

	public function checkSignTypes(): array
	{
		$return = [];

		try {
			$return[CstRequest::SIGNTYPEADVANCED]	= $this->configurationService->isEnabledSignTypeAdvanced();
			$return[CstRequest::SIGNTYPEQUALIFIED]	= $this->configurationService->isEnabledSignTypeQualified();
			$return[CstRequest::SIGNTYPESTANDARD]	= $this->configurationService->isEnabledSignTypeStandard();

			$this->logRCDevs->debug(sprintf('isEnabledSignTypeAdvanced:		%b', $this->configurationService->isEnabledSignTypeAdvanced()),		__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->debug(sprintf('isEnabledSignTypeQualified:	%b', $this->configurationService->isEnabledSignTypeQualified()),	__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->logRCDevs->debug(sprintf('isEnabledSignTypeStandard:		%b', $this->configurationService->isEnabledSignTypeStandard()),		__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			if (!$this->configurationService->isEnabledSignTypesAll()) {
				throw new Exception(CstException::SIGN_TYPES_DISABLED);
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error($th->getMessage(), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$return = [];
			$return[CstCommon::NAME]		= null;
			$return[CstCommon::STATUS]		= false;
			$return[CstRequest::CODE]		= 0;
			$return[CstRequest::MESSAGE]	= $th->getMessage();

			$return[CstRequest::SIGNTYPEADVANCED]	= null;
			$return[CstRequest::SIGNTYPEQUALIFIED]	= null;
			$return[CstRequest::SIGNTYPESTANDARD]	= null;
		}

		return $return;
	}

	public function lastJobRun(): array
	{
		$return = [];

		try {
			$cronJob = $this->mapper->findJob();
			$reservedAt	= Helpers::getArrayData($cronJob, 'reserved_at', false, 'No "Reservation" column found in query result');
			$lastRun	= Helpers::getArrayData($cronJob, 'last_run', false, 'No "Last Run" column found in query result');

			switch (true) {
				case $reservedAt === 0 && $lastRun === 0:
					$return[CstRequest::CODE]		= 1;
					$return[CstCommon::STATUS]			= CstCommon::SUCCESS;
					$return[CstRequest::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job is activated');
					break;

				case $reservedAt === 0 && $lastRun !== 0:
					$return[CstRequest::CODE]		= 1;
					$return[CstCommon::STATUS]			= CstCommon::SUCCESS;
					$return[CstRequest::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job is activated; the last time the job ran was at %s', [date('Y-m-d_H:i:s', $lastRun)]);
					break;

				default:
					$return[CstRequest::CODE]		= 0;
					$return[CstCommon::STATUS]			= CstCommon::ERROR;
					$return[CstRequest::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job was disabled at %s', [date('Y-m-d_H:i:s', $reservedAt)]);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error($this->l10nRcdevsSettingsService->t('Checking process failed at %s', [date('Y-m-d_H:i:s')]), __FUNCTION__, true);
			$return = [
				CstRequest::CODE	=> false,
				CstCommon::STATUS	=> CstCommon::ERROR,
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		return $return;
	}

	public function resetJob(): array
	{
		$return = [];

		try {
			$this->mapper->resetJob();
			// No exception => query is OK (does not mean data is updated)
			$return = [
				CstRequest::CODE	=> true,
				CstCommon::STATUS		=> CstCommon::SUCCESS,
				CstRequest::MESSAGE	=> $this->l10nRcdevsSettingsService->t('The cron job has been activated at %s', [date('Y-m-d_H:i:s')]),
			];
		} catch (\Throwable $th) {
			$return = [
				CstRequest::CODE	=> false,
				CstCommon::STATUS	=> CstCommon::ERROR,
				CstRequest::MESSAGE	=> $th->getMessage(),
			];
		}

		return $return;
	}
}
