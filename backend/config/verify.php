<?php

return [

    // Public base URL of the hosted verification UI. The hosted session URL is
    // built as: {hosted_base_url}/s/{session_token}
    'hosted_base_url' => env('VERIFY_HOSTED_BASE_URL', 'https://verify.valyd.net'),

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

    // Disk used to store uploaded ID/selfie images.
    'image_disk' => env('VERIFY_IMAGE_DISK', 'local'),

    // Supported verification feature keys.
    'features' => [
        'id_verification',
        'liveness',
        'face_match',
        'age',
        'credential',
    ],
];
