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
use OCA\YumiSignNxtC\RCDevs\Constant\CstJob;
use OCA\YumiSignNxtC\RCDevs\Constant\CstMessage;
use OCA\YumiSignNxtC\RCDevs\Constant\CstReturn;
use OCA\YumiSignNxtC\RCDevs\Db\JobMapper;
use OCA\YumiSignNxtC\RCDevs\Enum\CDEMLogLevel;
use OCA\YumiSignNxtC\RCDevs\Exception\JobException;
use OCA\YumiSignNxtC\RCDevs\Helper\ArrayObject;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\DateService;
use OCA\YumiSignNxtC\RCDevs\Service\LogRCDevs;

// Nextcloud Core
use Exception;
use OCA\OpenOTPSign\Constant\CstDatabase;
use OCA\OpenOTPSign\Constant\CstException;
use OCA\YumiSignNxtC\RCDevs\Constant\CstCriticity;
use OCP\DB\Exception as DBException;
use RuntimeException;
use Throwable;

use function PHPUnit\Framework\throwException;

class JobService
{
	public function __construct(
		private ConfigurationService	$configurationService,
		private DateService				$dateService,
		private LogRCDevs				$logRCDevs,
		private JobMapper				$mapper,
	) {}

	/**
	 * Retrieves the datetime of the last run job
	 * Also indicates if job is enabled or disabled
	 * 
	 * @return array 
	 * @throws Throwable 
	 * @throws Exception 
	 */
	public function lastRun(): CDEM
	{
		$cdem = new CDEM();

		try {
			$resp = $this->mapper->findLastRun();
			if ($resp[CstReturn::CODE] !== 0) {
				throw new Exception($resp[CstReturn::ERROR], $resp[CstReturn::CODE]);
			}

			$reservedAt = ArrayObject::getArrayData(
				array: $resp[CstReturn::DATA],
				key: CstJob::RESERVED_AT,
				missingForbidden: true,
				exceptionMessage: vsprintf('%s-%s', [CstException::NOT_FOUND, CstDatabase::COLUMN_RESERVED_AT]),
			);
			$lastRun	= ArrayObject::getArrayData(
				array: $resp[CstReturn::DATA],
				key: CstJob::LAST_RUN,
				missingForbidden: true,
				exceptionMessage: vsprintf('%s-%s', [CstException::NOT_FOUND, CstDatabase::COLUMN_LAST_RUN]),
			);

			// $data = [
			// 	CstJob::LAST_RUN	=> $lastRun,
			// 	CstJob::RESERVED_AT	=> $reservedAt,
			// ];

			switch (true) {
				case $reservedAt === 0 && $lastRun === 0:
					$cdem->setOK(message: CstMessage::CRON_JOB_ACTIVATED);
					break;

				case $reservedAt === 0 && $lastRun !== 0:
					$cdem->setOk(
						data: [
							CstMessage::CRON_JOB_ACTIVATED_LAST_TIME,
							$this->dateService->formatCurrentUser($lastRun)
						],
						message: CstMessage::CRON_JOB_ACTIVATED_LAST_TIME
					);
					break;

				default:
					throw new JobException(
						sprintf(CstMessage::CRON_JOB_DISABLED, $this->dateService->formatCurrentUser($reservedAt)),
						2,
						data: [CstMessage::CRON_JOB_DISABLED, $this->dateService->formatCurrentUser($reservedAt)]
					);
					break;
			}
		} catch (JobException $jobException) {
			$cdem->setWarning(
				exception: $jobException,
				logRCDevs: $this->logRCDevs,
				data: $jobException->getData(),
			);
		} catch (\Throwable $th) {
			$cdem->setError(
				exception: $th,
				logRCDevs: $this->logRCDevs
			);
		}

		return $cdem;
	}

	/**
	 * Permits to completely reset the job
	 * 
	 * @return null 
	 * @throws Throwable 
	 * @throws DBException 
	 * @throws RuntimeException 
	 */
	public function reset(): CDEM
	{
		$cdem = new CDEM();

		try {
			$affectedRows = $this->mapper->reset();

			switch ($affectedRows) {
				case 0:
					$message = CstMessage::CRON_JOB_ALREADY_ACTIVATED;
					$cdem->setOk(
						data: [
							CstReturn::MESSAGE	=> $message,
							CstCommon::DATE		=> $this->dateService->formatDefault(),
							/**
							 * Will be used to display the fact nothing has been reset
							 * Not an exception, maybe another Admin has done this reset
							 */
							CstCriticity::ALERT	=> CstCriticity::INFO,
						],
						message: $message,
						logRCDevs: $this->logRCDevs,
						logLevel: CDEMLogLevel::INFO,
					);
					break;

				case 1:
					$message = CstMessage::CRON_JOB_BEEN_ACTIVATED;
					$cdem->setOk(
						data: [
							CstReturn::MESSAGE	=> $message,
							CstCommon::DATE		=> $this->dateService->formatDefault(),
							CstCriticity::ALERT	=> CstCriticity::OK,
						],
						message: $message,
						logRCDevs: $this->logRCDevs,
						logLevel: CDEMLogLevel::INFO,
					);
					break;

				default:
					throw new Exception(CstException::CRON_JOB_ACTIVATION);
					break;
			}
		} catch (\Throwable $th) {
			$cdem->setWarning(
				exception: $th,
				logRCDevs: $this->logRCDevs,
				data: [
					CstReturn::MESSAGE	=> $th->getMessage(),
					CstCommon::DATE		=> $this->dateService->formatDefault(),
					CstCriticity::ALERT	=> CstCriticity::WARNING,
				],
			);
		}

		return $cdem;
	}
}
