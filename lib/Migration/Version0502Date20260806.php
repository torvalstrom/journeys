<?php
namespace OCA\Journeys\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Per-journal library consent: a member may let the journal's other members pick
 * from their own photos. Keyed on the user rather than the member row, because a
 * user who has access via a group has no row in journeys_journal_members.
 */
class Version0502Date20260806 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('journeys_journal_consents')) {
            return null;
        }

        $t = $schema->createTable('journeys_journal_consents');
        $t->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $t->addColumn('journal_id', 'bigint', ['notnull' => true, 'unsigned' => true]);
        $t->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
        $t->addColumn('created_at', 'datetime', ['notnull' => false]);
        $t->setPrimaryKey(['id'], 'jjc_pk');
        $t->addUniqueIndex(['journal_id', 'user_id'], 'jjc_journal_user_uidx');
        $t->addIndex(['user_id'], 'jjc_user_idx');

        return $schema;
    }
}
