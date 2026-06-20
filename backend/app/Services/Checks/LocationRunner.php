<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use Carbon\Carbon;

/**
 * Location capture from the end-user's device GPS (browser navigator.geolocation).
 * Capture-only: there is no geofence / allowed-country logic — the check passes
 * whenever a syntactically valid position is obtained, and the coordinates are
 * recorded on the session for the integrator to decide on. A permission denial
 * (no coordinates) is recorded as `review`, not a hard failure.
 *
 * Coordinates are PII: they live only in the check `data` and must never be logged.
 */
class LocationRunner
{
    /**
     * @param float|null $lat      Latitude in degrees (-90..90).
     * @param float|null $lon      Longitude in degrees (-180..180).
     * @param float|null $accuracy Reported accuracy radius in metres (optional).
     * @param bool       $denied   True when the user declined the permission prompt.
     */
    public function run(?float $lat, ?float $lon, ?float $accuracy = null, bool $denied = false): CheckResult
    {
        if ($denied) {
            return CheckResult::review(VerificationCheck::TYPE_LOCATION, ['reason' => 'permission_denied']);
        }

        if ($lat === null || $lon === null) {
            return CheckResult::failed(VerificationCheck::TYPE_LOCATION, 'missing_coordinates');
        }
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return CheckResult::failed(VerificationCheck::TYPE_LOCATION, 'invalid_coordinates', null, [
                'latitude' => $lat,
                'longitude' => $lon,
            ]);
        }

        $data = [
            'latitude' => $lat,
            'longitude' => $lon,
            'accuracy' => $accuracy,
            'source' => 'gps',
            'captured_at' => Carbon::now()->toIso8601String(),
        ];

        // Capture-only: a valid fix always passes. `score` carries the accuracy
        // radius (metres) so it surfaces in the per-feature decision summary.
        return CheckResult::passed(VerificationCheck::TYPE_LOCATION, $accuracy, $data);
    }
}
