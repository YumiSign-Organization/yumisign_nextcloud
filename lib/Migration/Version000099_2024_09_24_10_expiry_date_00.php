<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use Exception;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000099_2024_09_24_10_expiry_date_00 extends SimpleMigrationStep
{
	// Table
	private string $tableSignSessions;
	// Column(s)
	private string $expiry_Date_caseError;
	private string $tmp_expiry_date;

	public function __construct(
		private IDBConnection $connection,
		private ConfigurationService $configurationService,
	) {
		$this->tableSignSessions		= $this->configurationService->getAppTableNameSessions();
		$this->expiry_Date_caseError	= 'expiry_Date';
		$this->tmp_expiry_date			= 'tmp_expiry_date';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): null|ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable($this->tableSignSessions)) {
			$table = $schema->getTable($this->tableSignSessions);

			// Proceed only if old column [expiry_Date] exists
			if ($table->hasColumn($this->expiry_Date_caseError)) {
				// Add TMP column if needed, otherwise modify it
				if (!$table->hasColumn($this->tmp_expiry_date)) {
					$table->addColumn($this->tmp_expiry_date, CstEntity::BIGINT, [
						'length'	=> 20,
						'notnull'	=> false,
					]);
				} else {
					$table->modifyColumn($this->tmp_expiry_date, [
						'type'		=> Type::getType(CstEntity::BIGINT),
						'length'	=> 20,
						'notnull'	=> false,
						'unsigned'	=> true,
					]);
				}
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{
		// Add existing data inside TMP column
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable($this->tableSignSessions)) {
			throw new Exception("Table tableSignSessions is missing", 1);;
		}
		$table = $schema->getTable($this->tableSignSessions);

		// Proceed only if old column [expiry_Date] exists and temporary column
		if (
			$table->hasColumn($this->expiry_Date_caseError) &&
			$table->hasColumn($this->tmp_expiry_date)
		) {
			$update = $this->connection->getQueryBuilder();
			$update
				->update($this->tableSignSessions)
				->set($this->tmp_expiry_date, $this->expiry_Date_caseError);
				//
			;
			$update->executeStatement();
		}
	}
}
