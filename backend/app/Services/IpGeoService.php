<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coarse IP geolocation for the HOSTED flow's anti-spoof cross-check only.
 * IP location is city/region level (never a precise address) and VPN/mobile
 * carriers skew it — so this is a soft signal, used to flag gross GPS/IP
 * mismatches, never to decide the exact "at the home" match.
 */
class IpGeoService
{
    /** @return array{lat: float, lon: float, country: ?string, city: ?string}|null */
    public function locate(?string $ip): ?array
    {
        if (!$ip || $this->isPrivate($ip)) {
            return null;
        }
        try {
            $res = Http::timeout(4)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,lat,lon,country,city',
            ]);
            $j = $res->json();
            if (($j['status'] ?? null) !== 'success' || !isset($j['lat'], $j['lon'])) {
                return null;
            }
            return [
                'lat' => (float) $j['lat'],
                'lon' => (float) $j['lon'],
                'country' => $j['country'] ?? null,
                'city' => $j['city'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('IpGeoService lookup failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function isPrivate(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
