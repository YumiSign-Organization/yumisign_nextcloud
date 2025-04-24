<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;

class Version000099_2024_10_21_08_advanced_01 extends SimpleMigrationStep
{
	// Table
	private string $tableSignSessions;
	// Column(s)
	private string $advanced;

	public function __construct(
		private IDBConnection $connection,
		private ConfigurationService $configurationService,
	) {
		$this->tableSignSessions		= $this->configurationService->getAppTableNameSessions();
		$this->advanced				= 'advanced';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): null|ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable($this->tableSignSessions)) {
			$table = $schema->getTable($this->tableSignSessions);

			// Add 'advanced' column if needed, otherwise modify it
			if (!$table->hasColumn($this->advanced)) {
				$table->addColumn($this->advanced, 'smallint', [
					'length'	=> 1,
					'notnull'	=> false,
				]);
			} else {
				$table->modifyColumn($this->advanced, [
					'type'		=> Type::getType('smallint'),
					'length'	=> 1,
					'notnull'	=> false,
				]);
			}
		}

		return $schema;
	}
}
