<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;

class Version000099_2024_10_21_08_qualified_01 extends SimpleMigrationStep
{
	// Table
	private string $tableSignSessions;
	// Column(s)
	private string $qualified;

	public function __construct(
		private IDBConnection $connection,
		private ConfigurationService $configurationService,
	) {
		$this->tableSignSessions		= $this->configurationService->getAppTableNameSessions();
		$this->qualified				= 'qualified';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): null|ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable($this->tableSignSessions)) {
			$table = $schema->getTable($this->tableSignSessions);

			// Add 'qualified' column if needed, otherwise modify it
			if (!$table->hasColumn($this->qualified)) {
				$table->addColumn($this->qualified, 'smallint', [
					'length'	=> 1,
					'notnull'	=> false,
				]);
			} else {
				$table->modifyColumn($this->qualified, [
					'type'		=> Type::getType('smallint'),
					'length'	=> 1,
					'notnull'	=> false,
				]);
			}
		}

		return $schema;
	}
}
