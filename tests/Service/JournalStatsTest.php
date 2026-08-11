<?php
namespace OCA\Journeys\Tests\Service;

use OCA\Journeys\Service\JournalStats;
use PHPUnit\Framework\TestCase;

class JournalStatsTest extends TestCase {

    private function row(string $date, ?float $lat = null, ?float $lon = null, ?string $country = null, ?string $city = null): array {
        return ['date' => $date, 'lat' => $lat, 'lon' => $lon, 'country' => $country, 'city' => $city];
    }

    public function testEmptyJournal(): void {
        $stats = JournalStats::compute([]);
        $this->assertSame(0, $stats['days']);
        $this->assertSame(0, $stats['entryCount']);
        $this->assertSame([], $stats['countries']);
        $this->assertNull($stats['distanceKm']);
    }

    public function testSingleEntryIsOneDayAndNoDistance(): void {
        $stats = JournalStats::compute([$this->row('2026-06-03', 43.77, 11.25, 'Italy', 'Florence')], 4);
        $this->assertSame(1, $stats['days']);
        $this->assertSame(1, $stats['entryCount']);
        $this->assertSame(4, $stats['photoCount']);
        $this->assertSame(['Italy'], $stats['countries']);
        $this->assertNull($stats['distanceKm']);
    }

    public function testDaysCountTheSpanNotTheEntries(): void {
        $stats = JournalStats::compute([
            $this->row('2026-06-01'),
            $this->row('2026-06-10'),
        ]);
        $this->assertSame(10, $stats['days']);
        $this->assertSame(2, $stats['entryCount']);
    }

    public function testKnownDistanceBerlinToParis(): void {
        // ~878 km great-circle.
        $stats = JournalStats::compute([
            $this->row('2026-06-01', 52.520008, 13.404954),
            $this->row('2026-06-02', 48.856613, 2.352222),
        ]);
        $this->assertEqualsWithDelta(878, $stats['distanceKm'], 3);
    }

    public function testDistanceFollowsChronologyNotInputOrder(): void {
        $ordered = JournalStats::compute([
            $this->row('2026-06-01', 52.520008, 13.404954),
            $this->row('2026-06-02', 48.856613, 2.352222),
            $this->row('2026-06-03', 41.902782, 12.496366),
        ]);
        $shuffled = JournalStats::compute([
            $this->row('2026-06-03', 41.902782, 12.496366),
            $this->row('2026-06-01', 52.520008, 13.404954),
            $this->row('2026-06-02', 48.856613, 2.352222),
        ]);
        $this->assertSame($ordered['distanceKm'], $shuffled['distanceKm']);
    }

    public function testEntriesWithoutCoordinatesAreSkippedNotTreatedAsNullIsland(): void {
        $withGap = JournalStats::compute([
            $this->row('2026-06-01', 52.520008, 13.404954),
            $this->row('2026-06-02'),
            $this->row('2026-06-03', 48.856613, 2.352222),
        ]);
        $withoutGap = JournalStats::compute([
            $this->row('2026-06-01', 52.520008, 13.404954),
            $this->row('2026-06-03', 48.856613, 2.352222),
        ]);
        $this->assertSame($withoutGap['distanceKm'], $withGap['distanceKm']);
        $this->assertSame(3, $withGap['days']);
    }

    public function testCountriesAndCitiesAreDistinctInFirstSeenOrder(): void {
        $stats = JournalStats::compute([
            $this->row('2026-06-01', null, null, 'Italy', 'Florence'),
            $this->row('2026-06-02', null, null, 'Italy', 'Siena'),
            $this->row('2026-06-03', null, null, 'France', 'Nice'),
            $this->row('2026-06-04', null, null, 'Italy', 'Florence'),
        ]);
        $this->assertSame(['Italy', 'France'], $stats['countries']);
        $this->assertSame(['Florence', 'Siena', 'Nice'], $stats['cities']);
    }

    public function testNoGpsJournalReportsNoDistance(): void {
        $stats = JournalStats::compute([
            $this->row('2026-06-01', null, null, 'Italy'),
            $this->row('2026-06-02', null, null, 'Italy'),
        ]);
        $this->assertNull($stats['distanceKm']);
        $this->assertSame(['Italy'], $stats['countries']);
    }

    public function testHaversineIsSymmetricAndZeroForSamePoint(): void {
        $this->assertSame(0.0, JournalStats::haversineKm(43.77, 11.25, 43.77, 11.25));
        $this->assertEqualsWithDelta(
            JournalStats::haversineKm(43.77, 11.25, 48.85, 2.35),
            JournalStats::haversineKm(48.85, 2.35, 43.77, 11.25),
            0.001
        );
    }
}
