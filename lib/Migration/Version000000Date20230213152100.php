<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20230213152100 extends SimpleMigrationStep
{

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

		// if (!$schema->hasTable('yumisign_nxtc_sess')) {
		//		 $table = $schema->createTable('yumisign_nxtc_sess');
		//		 $table->addColumn('id', 'bigint', [
		//				 'autoincrement' => true,
		//				 'unsigned' => true,
		//				 'notnull' => true,
		//		 ]);
		//		 $table->addColumn('applicant_id', 'string', [
		//				 'notnull' => true,
		//				 'length' => 256
		//		 ]);
		//		 $table->addColumn('file_path', 'string', [
		//				 'notnull' => true,
		//				 'length' => 512
		//		 ]);
		//		 $table->addColumn('workspace_id', 'bigint', [
		//				 'autoincrement' => false,
		//				 'unsigned' => true,
		//				 'notnull' => true,
		//		 ]);
		//		 $table->addColumn('workflow_id', 'bigint', [
		//				 'autoincrement' => false,
		//				 'unsigned' => true,
		//				 'notnull' => true,
		//		 ]);
		//		 $table->addColumn('workflow_name', 'string', [
		//				 'notnull' => true,
		//				 'length' => 256
		//		 ]);
		//		 $table->addColumn('envelope_id', 'string', [
		//				 'notnull' => true,
		//				 'length' => 256
		//		 ]);
		//		 $table->addColumn('secret', 'string', [
		//				 'notnull' => false,
		//				 'length' => 256
		//		 ]);
		//		 $table->addColumn('expiry_Date', 'bigint', [
		//				 'autoincrement' => false,
		//				 'unsigned' => true,
		//				 'notnull' => false,
		//		 ]);
		//		 $table->addColumn('status', 'string', [
		//				 'notnull' => false,
		//				 'length' => 32
		//		 ]);

		//		 $table->setPrimaryKey(['id']);
		//		 $table->addIndex(['applicant_id'], 'yumisign_applicantId_idx');
		// }
		return $schema;
	}
}
