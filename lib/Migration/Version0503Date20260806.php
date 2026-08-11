<?php
namespace OCA\Journeys\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Marks a journal as finished. Non-null completed_at = completed. */
class Version0503Date20260806 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('journeys_journals')) {
            return null;
        }
        $table = $schema->getTable('journeys_journals');
        if ($table->hasColumn('completed_at')) {
            return null;
        }
        $table->addColumn('completed_at', 'datetime', ['notnull' => false]);

        return $schema;
    }
}
