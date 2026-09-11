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

namespace OCA\YumiSignNxtC\RCDevs\Service;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstCommon;
use OCA\YumiSignNxtC\RCDevs\Constant\CstCurl;
use OCA\YumiSignNxtC\RCDevs\Constant\CstException;
use OCA\YumiSignNxtC\RCDevs\Constant\CstRequest;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Constant\CstSettings;
use OCA\YumiSignNxtC\RCDevs\Constant\CstTransactionType;
use OCA\YumiSignNxtC\RCDevs\Db\JobMapper;
use OCA\YumiSignNxtC\RCDevs\Entity\CurlEntity;
use OCA\YumiSignNxtC\RCDevs\Enum\CDEMLogLevel;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;
use OCA\YumiSignNxtC\RCDevs\Utility\Helpers;

// Nextcloud Core
use Exception;
use OCP\IAppConfig;
use OCP\IL10N;
use Throwable;

class SettingsService
{
	private	ConfigurationService	$configurationService;
	private	CurlService				$curlService;

	public function __construct(
		IAppConfig						$config,
		private		IL10N				$l10nRcdevsSettingsService,
		private		JobMapper			$mapper,
		protected	LogRCDevs			$logRCDevs,
	) {
		$this->configurationService = new ConfigurationService($config);

		$_credentialKey = "{$this->configurationService->getApiKeyName()}:{$this->configurationService->getApiKey()}";
		$this->curlService = new CurlService($config, $this->logRCDevs);
		$this->curlService->addCredentialKey($_credentialKey);
	}

	/** ******************************************************************************************
	 * PUBLIC
	 ****************************************************************************************** */

	/**
	 * Check if server is online
	 * 
	 * @param string $serverUrl 
	 * @return array 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function checkServerUrl(
		string $serverUrl,
	): array {
		$cdem = new CDEM();

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
			$this->logRCDevs->debug(
				sprintf('checkServerUrl raw response: [%s]', $curlEntity->getBody()),
				__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . __FILE__ . ':' . __LINE__,
			);

			$this->curlService->closeHandle();

			// Check response validity
			$this->curlService->checkCurlCode(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$this->curlService->checkCurlBody(__FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));

			// At this point, this response is OK
			$cdem->setOk(
				data: [
					CstCommon::NAME		=> CstCommon::CONNECTED,
					CstCommon::STATUS	=> true,
				],
				message: CstCommon::CONNECTED,
			);
		} catch (\Throwable $th) {
			$thCode = CstException::CONNECTION_ERROR;
			$this->logRCDevs->error(vsprintf('%s [%s]', [$thCode, $th->getMessage()]), __FUNCTION__ . DIRECTORY_SEPARATOR . __CLASS__ . DIRECTORY_SEPARATOR . (isset($th) ? $th->getFile() . ':' . $th->getLine() : __FILE__ . ':' . __LINE__));
			$cdem->setWarning(
				exception: $th,
				logRCDevs: $this->logRCDevs,
				data: [
					CstCommon::NAME		=> null,
					CstCommon::STATUS	=> false,
				],
			);
		}

		return $cdem->toArray();
	}

	/**
	 * Check if Signatures Types are enabled or disabled
	 * 
	 * @return array 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function checkSignTypes(): array
	{
		$cdem = new CDEM();

		try {
			$data = [
				CstSettings::SIGN_TYPE_ADVANCED	=> $this->configurationService->isEnabledSignTypeAdvanced(),
				CstSettings::SIGN_TYPE_QUALIFIED	=> $this->configurationService->isEnabledSignTypeQualified(),
				CstSettings::SIGN_TYPE_STANDARD	=> $this->configurationService->isEnabledSignTypeStandard(),
			];

			if (!$this->configurationService->isEnabledSignTypesAll()) {
				throw new Exception(CstException::SIGN_TYPES_DISABLED);
			}

			$cdem->setOk(
				data: $data,
				logRCDevs: $this->logRCDevs,
				logLevel: CDEMLogLevel::DEBUG
			);
		} catch (\Throwable $th) {
			$cdem->setWarning(
				exception: $th,
				logRCDevs: $this->logRCDevs,
				data: [
					CstSettings::SIGN_TYPE_ADVANCED		=> null,
					CstSettings::SIGN_TYPE_QUALIFIED	=> null,
					CstSettings::SIGN_TYPE_STANDARD		=> null,
				],
			);
		}

		return $cdem->toArray();
	}

	public function lastJobRun(): array
	{
		$return = [];

		try {
			$cronResponse = $this->mapper->findLastRun();
			$cronJob = Helpers::getIfExists(CstReturn::DATA, $cronResponse, returnNull: false);

			$reservedAt = intval(Helpers::getArrayData(is_array($cronJob) ? $cronJob : null, 'reserved_at', false, 'No "Reservation" column found in query result'));
			$lastRun = intval(Helpers::getArrayData(is_array($cronJob) ? $cronJob : null, 'last_run', false, 'No "Last Run" column found in query result'));

			switch (true) {
				case $reservedAt === 0 && $lastRun === 0:
					$return[CstReturn::CODE]	= 1;
					$return[CstCommon::STATUS]	= CstCommon::SUCCESS;
					$return[CstReturn::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job is activated; it has never run yet');
					break;

				case $reservedAt === 0 && $lastRun !== 0:
					$return[CstReturn::CODE]	= 1;
					$return[CstCommon::STATUS]	= CstCommon::SUCCESS;
					$return[CstReturn::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job is activated; the last time the job ran was at %s', [date('Y-m-d_H:i:s', $lastRun)]);
					break;

				default:
					$return[CstReturn::CODE]	= 0;
					$return[CstCommon::STATUS]	= CstCommon::ERROR;
					$return[CstReturn::MESSAGE]	= $this->l10nRcdevsSettingsService->t('The cron job was disabled at %s', [date('Y-m-d_H:i:s', $reservedAt)]);
					break;
			}
		} catch (\Throwable $th) {
			$this->logRCDevs->error(vsprintf('Checking process failed at %s: %s', [date('Y-m-d_H:i:s'), $th->getMessage()]), __FUNCTION__);
			$return = [
				CstReturn::CODE	=> 0,
				CstCommon::STATUS	=> CstCommon::ERROR,
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $return;
	}

	public function resetJob(): array
	{
		$return = [];

		try {
			$this->mapper->reset();
			// No exception => query is OK (does not mean data is updated)
			$return = [
				CstReturn::CODE		=> 1,
				CstCommon::STATUS	=> CstCommon::SUCCESS,
				CstReturn::MESSAGE	=> $this->l10nRcdevsSettingsService->t('The cron job has been activated at %s', [date('Y-m-d_H:i:s')]),
			];
		} catch (\Throwable $th) {
			$return = [
				CstReturn::CODE		=> 0,
				CstCommon::STATUS	=> CstCommon::ERROR,
				CstReturn::MESSAGE	=> $th->getMessage(),
			];
		}

		return $return;
	}
}
