<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000099_2024_09_24_10_expiry_date_02 extends SimpleMigrationStep
{
	// Table
	private string $tableSignSessions;
	// Column(s)
	private string $tmp_expiry_date;

	public function __construct(
		private IDBConnection $connection,
		private ConfigurationService $configurationService,
	) {
		$this->tableSignSessions	= $this->configurationService->getAppTableNameSessions();
		$this->tmp_expiry_date		= 'tmp_expiry_date';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): null|ISchemaWrapper
	{
		// Drop TMP column
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable($this->tableSignSessions)) {
			$table = $schema->getTable($this->tableSignSessions);

			// Drop 'tmp_expiry_date' column if needed
			if ($table->hasColumn($this->tmp_expiry_date)) {
				$table->dropColumn($this->tmp_expiry_date);
			}
		}

		return $schema;
	}
}
