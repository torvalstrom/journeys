<?php
namespace OCA\Journeys\Service;

use OCA\Journeys\Model\JournalEntry;

/**
 * Travel figures for a journal: how long it lasted, where it went and how far.
 * Pure and stateless — takes plain rows so it can serve both the detail view
 * (JournalEntry objects) and the journal list (a single grouped query).
 */
class JournalStats {

    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @param JournalEntry[] $entries
     * @return array<int,array{date:string,lat:?float,lon:?float,country:?string,city:?string}>
     */
    public static function rowsFromEntries(array $entries): array {
        return array_map(static fn(JournalEntry $e) => [
            'date' => $e->entryDate,
            'lat' => $e->lat,
            'lon' => $e->lon,
            'country' => $e->country,
            'city' => $e->city ?: $e->placeLabel,
        ], $entries);
    }

    /**
     * @param array<int,array{date:string,lat:?float,lon:?float,country:?string,city:?string}> $rows
     * @return array{days:int,entryCount:int,photoCount:int,countries:string[],cities:string[],distanceKm:?int}
     */
    public static function compute(array $rows, int $photoCount = 0): array {
        usort($rows, static fn(array $a, array $b) => strcmp((string)$a['date'], (string)$b['date']));

        $countries = [];
        $cities = [];
        $points = [];
        $dates = [];
        foreach ($rows as $row) {
            $date = (string)($row['date'] ?? '');
            if ($date !== '') {
                $dates[$date] = true;
            }
            $country = $row['country'] ?? null;
            if ($country && !in_array($country, $countries, true)) {
                $countries[] = $country;
            }
            $city = $row['city'] ?? null;
            if ($city && !in_array($city, $cities, true)) {
                $cities[] = $city;
            }
            if ($row['lat'] !== null && $row['lon'] !== null) {
                $points[] = [(float)$row['lat'], (float)$row['lon']];
            }
        }

        return [
            'days' => self::spanInDays(array_keys($dates)),
            'entryCount' => count($rows),
            'photoCount' => $photoCount,
            'countries' => $countries,
            'cities' => $cities,
            'distanceKm' => self::routeDistanceKm($points),
        ];
    }

    /** Calendar days covered, first to last entry inclusive (not the entry count). */
    private static function spanInDays(array $dates): int {
        if (!$dates) {
            return 0;
        }
        sort($dates);
        $first = strtotime((string)reset($dates) . ' 00:00:00 UTC');
        $last = strtotime((string)end($dates) . ' 00:00:00 UTC');
        if ($first === false || $last === false) {
            return count($dates);
        }
        return (int)floor(($last - $first) / 86400) + 1;
    }

    /**
     * Great-circle distance along the geolocated entries, in whole km. Null with
     * fewer than two points — a journal with no GPS should show no figure rather
     * than a misleading "0 km".
     *
     * @param array<int,array{0:float,1:float}> $points
     */
    private static function routeDistanceKm(array $points): ?int {
        if (count($points) < 2) {
            return null;
        }
        $total = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $total += self::haversineKm($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }
        return (int)round($total);
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
