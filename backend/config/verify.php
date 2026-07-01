<?php

return [

    // Public base URL of the hosted verification UI. The hosted session URL is
    // built as: {hosted_base_url}/s/{session_token}
    'hosted_base_url' => env('VERIFY_HOSTED_BASE_URL', 'https://verify.valyd.id'),

    // Default session lifetime (seconds) when the client does not pass ttl_seconds.
    'default_session_ttl' => (int) env('VERIFY_DEFAULT_SESSION_TTL', 1800),
    'min_session_ttl' => 60,
    'max_session_ttl' => 86400,

    // Queue used for outbound webhook delivery jobs.
    'webhook_queue' => env('VERIFY_WEBHOOK_QUEUE', 'default'),

    // Face-match similarity threshold (cosine, 0..1). Mirrors BiometricUtils::TARGET_SIM.
    'face_match_threshold' => (float) env('VERIFY_FACE_MATCH_THRESHOLD', 0.95),

    // Minimum FaceOnLive liveness score (0..100) to consider a selfie "live".
    'liveness_threshold' => (int) env('VERIFY_LIVENESS_THRESHOLD', 50),

    // Location matching. accuracy_max_m: reject GPS fixes coarser than this (a
    // large accuracy radius means Wi-Fi/IP positioning, not real GPS). ip_mismatch_km:
    // in HOSTED flows only, flag (soft `review`) when the user IP geolocates this
    // far from the captured GPS — coarse anti-spoof signal, never a hard fail.
    'location_accuracy_max_m' => (float) env('VERIFY_LOCATION_ACCURACY_MAX_M', 100),
    'location_ip_mismatch_km' => (float) env('VERIFY_LOCATION_IP_MISMATCH_KM', 200),

    // Disk used to store uploaded ID/selfie images.
    'image_disk' => env('VERIFY_IMAGE_DISK', 'local'),

    // Supported verification feature keys.
    'features' => [
        'id_verification',
        'liveness',
        'face_match',
        'age',
        'credential',
        'location',        // raw geocode of a captured GPS point (country/state/city)
        'location_match',  // captured vs expected coordinates → { match, distance_m }
        'evv_presence',    // bundle: face_match + location_match (right person + right place)
    ],

    // Billing currency for the prepaid account balance (display only for now).
    'currency' => env('VERIFY_CURRENCY', 'USD'),

    // Per-API cost charged against the account balance, by feature key. A hosted
    // session's upfront guard sums its workflow's features; each check deducts its
    // own cost as it runs (refunded on our-side errors).
    'pricing' => [
        'id_verification' => (float) env('VERIFY_PRICE_ID', 0.13),
        'liveness' => (float) env('VERIFY_PRICE_LIVENESS', 0.08),
        'face_match' => (float) env('VERIFY_PRICE_FACE_MATCH', 0.10),
        'age' => (float) env('VERIFY_PRICE_AGE', 0.05),
        'credential' => (float) env('VERIFY_PRICE_CREDENTIAL', 0.32),
        'location' => (float) env('VERIFY_PRICE_LOCATION', 0.02),
        'location_match' => (float) env('VERIFY_PRICE_LOCATION_MATCH', 0.05),
        // EVV Presence bundle (face_match + location_match). Priced BELOW the sum
        // (0.10 + 0.05 = 0.15) to steer integrators to the combined check.
        'evv_presence' => (float) env('VERIFY_PRICE_EVV_PRESENCE', 0.12),
    ],
];
