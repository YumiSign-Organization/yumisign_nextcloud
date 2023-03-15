<?php

namespace OCA\YumiSignNxtC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date_20230228_153900 extends SimpleMigrationStep
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

        $table->addColumn('expiry_date', 'bigint', [
            'autoincrement' => false,
            'unsigned' => true,
            'notnull' => true,
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

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options)
    {
        $query = $this->db->getQueryBuilder();
        $query->update('yumisign_nxtc_sess')
            ->set('expiry_date', 'expiry_date_lower');
        $query->execute();
    }
}
