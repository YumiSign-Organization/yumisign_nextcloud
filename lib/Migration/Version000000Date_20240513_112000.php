<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20240513_112000 extends SimpleMigrationStep
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

        $table = $schema->getTable('yumisign_nxtc_sess');

        // Add Mutex for Async processes
        // The same as previous migScript but an issue occured on customer DB
        if (!$table->hasColumn('mutex')) {
            $table->addColumn('mutex', 'string', [
                'notnull' => false,
                'length' => 32
            ]);
        } else {
            $table->changeColumn('mutex', [
                'notnull' => false,
                'length' => 32
            ]);
        }

        return $schema;
    }
}
