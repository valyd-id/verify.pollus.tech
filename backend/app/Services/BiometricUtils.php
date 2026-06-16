<?php

namespace App\Services;

/**
 * Biometric constants + vector helpers, ported (slimmed) from idp.pollus.tech.
 * Image cropping (Intervention/GD) is intentionally omitted — the FaceOnLive
 * feature endpoint detects and embeds the face from the full image, so the
 * doc-to-selfie flow never needs to crop locally.
 */
class BiometricUtils
{
    public const FEATURE_SIZE = 2056;
    public const TARGET_SIM = 0.95;

    public static function normalizeVector(?array $v): ?array
    {
        if ($v === null) {
            return null;
        }

        $arr = array_map('floatval', $v);
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $arr)));

        if ($norm == 0.0 || count($arr) !== self::FEATURE_SIZE) {
            return null;
        }

        return array_map(fn ($x) => $x / $norm, $arr);
    }

    public static function decodeDataUrlToBytes(string $dataUrl): ?string
    {
        $payload = strpos($dataUrl, ',') !== false
            ? substr($dataUrl, strpos($dataUrl, ',') + 1)
            : $dataUrl;
        $raw = base64_decode($payload, true);
        return $raw === false ? null : $raw;
    }

    public static function isEmptyOrZeroVector(?array $feature): bool
    {
        if (empty($feature)) {
            return true;
        }
        foreach ($feature as $value) {
            if ((float) $value != 0.0) {
                return false;
            }
        }
        return true;
    }
}
