<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20230227_093500 extends SimpleMigrationStep
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

        $table->addColumn('expiry_date_lower', 'bigint', [
            'autoincrement' => false,
            'unsigned' => true,
            'notnull' => true,
        ]);

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options)
    {
        $query = $this->db->getQueryBuilder();
        $query->update('yumisign_nxtc_sess')
            ->set('expiry_date_lower', 'expiry_Date');
        $query->executeQuery();
    }
}
