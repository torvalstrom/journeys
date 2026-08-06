<?php
namespace OCA\Journeys\Tests\Service;

use OCA\Journeys\Model\EntryPhoto;
use PHPUnit\Framework\TestCase;

/**
 * EntryPhoto::sortChronologically — the merge sort that interleaves photos
 * contributed by different collaborators into one chronological entry.
 */
class EntryPhotoSortTest extends TestCase {

    /** @param array<int,?string> $times */
    private function sortIds(array $fileids, array $times): array {
        $normalized = EntryPhoto::normalizeSelection($fileids);
        $sorted = EntryPhoto::sortChronologically($normalized, $times);
        return array_map(static fn(array $p) => $p['fileid'], $sorted);
    }

    public function testInterleavesLateAddedPhotoByCaptureTime(): void {
        // A's morning + evening photos are already on the entry; B appends a
        // midday photo — it must land in the middle, not at the end.
        $ids = $this->sortIds([1, 3, 2], [
            1 => '2026-06-03 09:00:00',
            3 => '2026-06-03 19:30:00',
            2 => '2026-06-03 12:15:00',
        ]);
        $this->assertSame([1, 2, 3], $ids);
    }

    public function testUndatedPhotosGoLastKeepingSubmittedOrder(): void {
        $ids = $this->sortIds([7, 5, 9, 8], [
            5 => '2026-06-03 08:00:00',
            8 => '2026-06-03 10:00:00',
        ]);
        // timed first (5, 8), then the undated ones in submission order (7, 9).
        $this->assertSame([5, 8, 7, 9], $ids);
    }

    public function testEmptyAndBlankTimestampsCountAsUndated(): void {
        $ids = $this->sortIds([1, 2, 3], [
            1 => null,
            2 => '   ',
            3 => '2026-06-03 08:00:00',
        ]);
        $this->assertSame([3, 1, 2], $ids);
    }

    public function testEqualTimestampsFallBackToFileidForDeterminism(): void {
        $stamp = '2026-06-03 08:00:00';
        $this->assertSame([4, 6, 9], $this->sortIds([9, 4, 6], [4 => $stamp, 6 => $stamp, 9 => $stamp]));
        // …and the reverse submission order yields the same result.
        $this->assertSame([4, 6, 9], $this->sortIds([6, 9, 4], [4 => $stamp, 6 => $stamp, 9 => $stamp]));
    }

    public function testAssignsDenseZeroBasedSortOrder(): void {
        $sorted = EntryPhoto::sortChronologically(
            EntryPhoto::normalizeSelection([3, 1, 2]),
            [1 => '2026-06-03 09:00:00', 2 => '2026-06-03 10:00:00', 3 => '2026-06-03 11:00:00']
        );
        $this->assertSame([0, 1, 2], array_column($sorted, 'sort_order'));
        $this->assertSame([1, 2, 3], array_column($sorted, 'fileid'));
    }

    public function testCarriesCaptionsAndTakenAtThrough(): void {
        $sorted = EntryPhoto::sortChronologically(
            EntryPhoto::normalizeSelection([
                ['fileid' => 2, 'caption' => 'later'],
                ['fileid' => 1, 'caption' => 'earlier'],
            ]),
            [1 => '2026-06-03 09:00:00', 2 => '2026-06-03 18:00:00']
        );
        $this->assertSame(['earlier', 'later'], array_column($sorted, 'caption'));
        $this->assertSame(['2026-06-03 09:00:00', '2026-06-03 18:00:00'], array_column($sorted, 'taken_at'));
    }

    public function testEmptySelection(): void {
        $this->assertSame([], EntryPhoto::sortChronologically([], []));
    }

    public function testSingleItemWithNoKnownTime(): void {
        $sorted = EntryPhoto::sortChronologically(EntryPhoto::normalizeSelection([42]), []);
        $this->assertSame([['fileid' => 42, 'caption' => null, 'sort_order' => 0, 'taken_at' => null]], $sorted);
    }
}
