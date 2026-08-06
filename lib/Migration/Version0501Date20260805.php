<?php
namespace OCA\Journeys\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the capture time to curated entry photos so a journal entry can be merge
 * sorted chronologically across contributors: before this, sort_order was
 * assigned in submission order, so a collaborator's photos were appended after
 * everyone else's instead of interleaving by when they were taken.
 *
 * taken_at is a denormalized cache of oc_memories.datetaken (the same index the
 * rest of the app reads); it stays NULL for files Memories has not indexed.
 */
class Version0501Date20260805 extends SimpleMigrationStep {

    public function __construct(
        private IDBConnection $db,
    ) {}

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('journeys_entry_photos')) {
            return null;
        }
        $table = $schema->getTable('journeys_entry_photos');
        $changed = false;

        if (!$table->hasColumn('taken_at')) {
            $table->addColumn('taken_at', 'datetime', ['notnull' => false]);
            $changed = true;
        }
        if (!$table->hasIndex('jep_taken_idx')) {
            $table->addIndex(['entry_id', 'taken_at'], 'jep_taken_idx');
            $changed = true;
        }

        return $changed ? $schema : null;
    }

    /**
     * Backfill taken_at for photos already attached to an entry, and re-derive
     * sort_order from it so existing entries come out chronological too.
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        try {
            $rows = $this->db->executeQuery(
                'SELECT p.id, p.entry_id, p.fileid, m.datetaken
                   FROM `*PREFIX*journeys_entry_photos` p
                   LEFT JOIN `*PREFIX*memories` m ON m.fileid = p.fileid
                  ORDER BY p.entry_id ASC, p.sort_order ASC, p.id ASC'
            )->fetchAll();
        } catch (\Throwable $e) {
            // No Memories table (app not installed yet) — the column stays NULL and
            // fills in on the next photo write. Never fail `occ upgrade` over this.
            $output->warning('journeys: skipped taken_at backfill (' . $e->getMessage() . ')');
            return;
        }
        if (!$rows) {
            return;
        }

        // Group per entry, keeping the current order as the tiebreaker for
        // photos Memories has no capture time for.
        $byEntry = [];
        foreach ($rows as $row) {
            $byEntry[(int)$row['entry_id']][] = [
                'id' => (int)$row['id'],
                'fileid' => (int)$row['fileid'],
                'takenAt' => $row['datetaken'] !== null ? (string)$row['datetaken'] : null,
            ];
        }

        $updated = 0;
        foreach ($byEntry as $photos) {
            // Same rule as EntryPhoto::sortChronologically(): timed photos
            // ascending, undated ones last in their existing relative order.
            $timed = array_values(array_filter($photos, static fn(array $p) => $p['takenAt'] !== null));
            $undated = array_values(array_filter($photos, static fn(array $p) => $p['takenAt'] === null));
            usort($timed, static fn(array $a, array $b) => [$a['takenAt'], $a['fileid']] <=> [$b['takenAt'], $b['fileid']]);
            $order = 0;
            foreach (array_merge($timed, $undated) as $photo) {
                $this->db->executeStatement(
                    'UPDATE `*PREFIX*journeys_entry_photos` SET `taken_at` = ?, `sort_order` = ? WHERE `id` = ?',
                    [$photo['takenAt'], $order++, $photo['id']]
                );
                $updated++;
            }
        }
        $output->info("journeys: backfilled taken_at + chronological order for {$updated} entry photos");
    }
}
