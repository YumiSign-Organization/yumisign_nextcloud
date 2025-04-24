<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20240325_095601 extends SimpleMigrationStep
{
	private $db;

	public function __construct(IDBConnection $db)
	{
		$this->db = $db;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Create clean table
		$table = $schema->createTable('yumisign_nxtc_sess');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('applicant_id', 'string', [
			'notnull' => true,
			'length' => 256
		]);
		$table->addColumn('file_path', 'string', [
			'notnull' => true,
			'length' => 512
		]);
		$table->addColumn('workspace_id', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('workflow_id', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('workflow_name', 'string', [
			'notnull' => true,
			'length' => 256
		]);
		$table->addColumn('envelope_id', 'string', [
			'notnull' => true,
			'length' => 256
		]);
		$table->addColumn('secret', 'string', [
			'notnull' => false,
			'length' => 256
		]);
		$table->addColumn('status', 'string', [
			'notnull' => false,
			'length' => 32
		]);
		$table->addColumn('expiry_Date', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => false,
		]);
		$table->addColumn('created', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('change_status', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('recipient', 'string', [
			'notnull' => true,
			'length' => 100,
		]);
		$table->addColumn('global_status', 'string', [
			'notnull' => false,
			'length' => 32
		]);
		$table->addColumn('msg_date', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('file_id', 'bigint', [
			'autoincrement' => false,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['applicant_id'], 'yumisign_applicantId_idx');

		return $schema;
	}
}
