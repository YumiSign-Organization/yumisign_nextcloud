<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20240325_095600 extends SimpleMigrationStep
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

        if ($schema->hasTable('yumisign_nxtc_sess')) {
            $table = $schema->getTable('yumisign_nxtc_sess');

            // Check if index exists
            $table->dropIndex('yumisign_applicantId_idx');

            // Drop table
            $schema->dropTable('yumisign_nxtc_sess');
        }

        return $schema;
    }
}
