<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCA\YumiSignNxtC\Utility\Constantes\CstEntity;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000099_2024_09_24_10_overwrite_00 extends SimpleMigrationStep
{
	// Table
	private string $tableSignSessions;
	// Column(s)
	private string $overwrite;

	public function __construct(
		private IDBConnection $connection,
		private ConfigurationService $configurationService,
	) {
		$this->tableSignSessions	= $this->configurationService->getAppTableNameSessions();
		$this->overwrite			= CstEntity::OVERWRITE;
	}

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): null|ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable($this->tableSignSessions)) {
			$table = $schema->getTable($this->tableSignSessions);

			// Add column if needed, otherwise modify it
			if (!$table->hasColumn($this->overwrite)) {
				$table->addColumn($this->overwrite, CstEntity::SMALLINT, [
					'length'	=> 1,
					'notnull'	=> false,
				]);
			} else {
				$table->modifyColumn($this->overwrite, [
					'type'		=> Type::getType(CstEntity::SMALLINT),
					'length'	=> 1,
					'notnull'	=> false,
				]);
			}
		}

		return $schema;
	}
}
