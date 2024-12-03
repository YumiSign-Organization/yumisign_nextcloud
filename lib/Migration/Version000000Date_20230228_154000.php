<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20230228_154000 extends SimpleMigrationStep
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

		// $table = $schema->getTable('yumisign_nxtc_sess');

		// /**
		//	* ###### Fri Mar 15 07:48:10 CET 2024
		//	* Add check phase before altering table => issue reported for RCDevs Support
		//	* Cause : maybe manual DB updates/deletions
		//	*/
		// if ($table->hasColumn('expiry_date_lower')) {
		//		 $table->dropColumn('expiry_date_lower');
		// }

		return $schema;
	}
}
