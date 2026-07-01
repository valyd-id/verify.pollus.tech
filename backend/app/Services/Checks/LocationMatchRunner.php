<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use Carbon\Carbon;

/**
 * Location MATCH: compare a captured GPS position against an expected position
 * (e.g. the address a home-healthcare visit was assigned to) and decide whether
 * the person is within an allowed radius. Unlike LocationRunner (capture-only),
 * this is the sellable "proof of presence" primitive — it returns a hard
 * match/no-match plus the distance.
 *
 * Coordinates are PII: they live only in the check `data` and must never be logged.
 */
class LocationMatchRunner
{
    private const EARTH_RADIUS_M = 6_371_000.0;
    private const DEFAULT_RADIUS_M = 200.0;

    /**
     * @param float      $capLat      Captured latitude.
     * @param float      $capLon      Captured longitude.
     * @param float      $expLat      Expected latitude.
     * @param float      $expLon      Expected longitude.
     * @param float|null $radiusM     Allowed radius in metres (default 200).
     * @param float|null $accuracy    Captured accuracy radius in metres (optional).
     */
    public function run(float $capLat, float $capLon, float $expLat, float $expLon, ?float $radiusM = null, ?float $accuracy = null): CheckResult
    {
        foreach ([[$capLat, $capLon], [$expLat, $expLon]] as [$lat, $lon]) {
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                return CheckResult::failed(VerificationCheck::TYPE_LOCATION_MATCH, 'invalid_coordinates');
            }
        }

        $radius = $radiusM !== null && $radiusM > 0 ? $radiusM : self::DEFAULT_RADIUS_M;
        $distance = $this->haversine($capLat, $capLon, $expLat, $expLon);
        $matched = $distance <= $radius;

        $data = [
            'captured' => ['latitude' => $capLat, 'longitude' => $capLon, 'accuracy' => $accuracy],
            'expected' => ['latitude' => $expLat, 'longitude' => $expLon],
            'distance_m' => round($distance, 1),
            'radius_m' => $radius,
            'match' => $matched,
            'checked_at' => Carbon::now()->toIso8601String(),
        ];

        // Accuracy gate: a coarse fix (large accuracy radius, or none reported) can't
        // be trusted to the geofence — surface as `review`, not a silent pass.
        $accuracyMax = (float) config('verify.location_accuracy_max_m', 100);
        if ($accuracy === null || $accuracy > $accuracyMax) {
            $data['accuracy_flag'] = $accuracy === null ? 'missing' : 'coarse';
            $data['accuracy_max_m'] = $accuracyMax;
            return CheckResult::review(VerificationCheck::TYPE_LOCATION_MATCH, $data, round($distance, 1));
        }

        // score = distance in metres (lower is better) so it surfaces in summaries.
        return $matched
            ? CheckResult::passed(VerificationCheck::TYPE_LOCATION_MATCH, round($distance, 1), $data)
            : CheckResult::failed(VerificationCheck::TYPE_LOCATION_MATCH, 'outside_allowed_radius', round($distance, 1), $data);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
