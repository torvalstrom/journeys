<?php
namespace OCA\Journeys\Model;

/**
 * A photo curated into a journal entry, referenced by fileid (mount-agnostic,
 * same convention as the rest of the app) with an explicit display order and
 * optional caption.
 */
class EntryPhoto {
    public function __construct(
        public int $id,
        public int $entryId,
        public int $fileid,
        public int $sortOrder = 0,
        public ?string $caption = null,
        public ?string $ownerUid = null,
        public ?string $takenAt = null,
    ) {}

    public static function fromRow(array $row): self {
        return new self(
            (int)$row['id'],
            (int)$row['entry_id'],
            (int)$row['fileid'],
            isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            isset($row['caption']) ? (string)$row['caption'] : null,
            isset($row['owner_uid']) && $row['owner_uid'] !== null ? (string)$row['owner_uid'] : null,
            isset($row['taken_at']) && $row['taken_at'] !== null ? (string)$row['taken_at'] : null,
        );
    }

    /**
     * Normalize a user-supplied photo selection into the rows to persist for an
     * entry. Accepts either bare fileids (int) or maps of
     * ['fileid' => int, 'caption' => ?string]. Drops non-positive/duplicate
     * fileids (first occurrence wins, preserving order) and assigns a dense,
     * zero-based sort_order. Pure — unit tested.
     *
     * @param array<int|array<string,mixed>> $items
     * @return array<int,array{fileid:int,caption:?string,sort_order:int}>
     */
    public static function normalizeSelection(array $items): array {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            $fileid = 0;
            $caption = null;
            if (is_array($item)) {
                $fileid = isset($item['fileid']) ? (int)$item['fileid'] : 0;
                if (isset($item['caption'])) {
                    $c = trim((string)$item['caption']);
                    $caption = $c === '' ? null : $c;
                }
            } else {
                $fileid = (int)$item;
            }
            if ($fileid <= 0 || isset($seen[$fileid])) {
                continue;
            }
            $seen[$fileid] = true;
            $out[] = [
                'fileid' => $fileid,
                'caption' => $caption,
                'sort_order' => count($out),
            ];
        }
        return $out;
    }

    /**
     * Merge sort a normalized selection into capture-time order and re-assign a
     * dense sort_order. This is what interleaves photos added by different
     * collaborators into one chronological day instead of appending each
     * contributor's batch after the previous one.
     *
     * Photos Memories has no capture time for keep their submitted relative
     * order and go last (they carry no timeline position, so guessing one would
     * scatter them through the day). Equal timestamps fall back to fileid, so
     * the result is deterministic. Pure — unit tested.
     *
     * @param array<int,array{fileid:int,caption:?string,sort_order:int}> $items
     * @param array<int,?string> $takenAtByFileid 'Y-m-d H:i:s' per fileid, missing/null = undated
     * @return array<int,array{fileid:int,caption:?string,sort_order:int,taken_at:?string}>
     */
    public static function sortChronologically(array $items, array $takenAtByFileid): array {
        $timed = [];
        $undated = [];
        foreach ($items as $item) {
            $takenAt = $takenAtByFileid[$item['fileid']] ?? null;
            $takenAt = ($takenAt === null || trim((string)$takenAt) === '') ? null : (string)$takenAt;
            $item['taken_at'] = $takenAt;
            if ($takenAt === null) {
                $undated[] = $item;
            } else {
                $timed[] = $item;
            }
        }
        usort($timed, static fn(array $a, array $b) => [$a['taken_at'], $a['fileid']] <=> [$b['taken_at'], $b['fileid']]);

        $out = [];
        foreach (array_merge($timed, $undated) as $item) {
            $item['sort_order'] = count($out);
            $out[] = $item;
        }
        return $out;
    }
}
