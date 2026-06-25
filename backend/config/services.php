<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // FaceOnLive face engine (liveness, feature extraction, similarity) + OCR.
    'faceonlive' => [
        'url' => env('REMOTE_FACE_URL', 'https://faceonlive-face.pollus.us'),
        'ocr_url' => env('REMOTE_FACE_OCR_URL', 'https://faceonlive-ocr.pollus.us'),
    ],

    // Zero-knowledge age-band proof service.
    'zk_verify' => [
        'url' => env('ZK_VERIFY_API_URL', 'http://localhost:4000/api/prove'),
        'timeout' => (int) env('ZK_VERIFY_TIMEOUT', 120),
    ],

    // Valyd SSO (OIDC relying-party) for the developer console login.
    // Register a client at idp.pollus.tech with redirect_uri = {APP}/dashboard/auth/callback.
    'valyd_oidc' => [
        'base_url' => env('VALYD_OIDC_BASE_URL', 'https://idp.pollus.tech'),
        'authorize_path' => env('VALYD_OIDC_AUTHORIZE_PATH', 'api/auth/oidc/authorize'),
        'token_path' => env('VALYD_OIDC_TOKEN_PATH', 'api/auth/oidc/token'),
        'userinfo_path' => env('VALYD_OIDC_USERINFO_PATH', 'api/auth/oidc/userinfo'),
        'client_id' => env('VALYD_OIDC_CLIENT_ID'),
        'client_secret' => env('VALYD_OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('VALYD_OIDC_REDIRECT_URI', 'https://verify.pollus.tech/login'),
        'scopes' => env('VALYD_OIDC_SCOPES', 'openid profile email'),
    ],

    // IdP (idp.pollus.tech) integration for "verify once, reuse": end-user logs in
    // with Valyd (TPSSO) so we get a pollus_id, then we read/write their verified
    // data backend-to-backend. TPSSO endpoints use client_id/client_secret + the
    // user's access token; the internal endpoints use the shared X-Internal-Auth key.
    'idp' => [
        'base_url' => env('IDP_BASE_URL', 'https://idp.pollus.tech'),
        'internal_auth_key' => env('IDP_INTERNAL_AUTH_KEY'),
        'tpsso_client_id' => env('IDP_TPSSO_CLIENT_ID'),
        'tpsso_client_secret' => env('IDP_TPSSO_CLIENT_SECRET'),
        'tpsso_redirect_uri' => env('IDP_TPSSO_REDIRECT_URI', 'https://verify.pollus.tech/verify/valyd-callback'),
        'tpsso_scopes' => env('IDP_TPSSO_SCOPES', 'profile verifications zkp'),
        'timeout' => (int) env('IDP_TIMEOUT', 30),
    ],

    // Professional-license verification (machine-to-machine into vc.pollus.tech).
    // Uses the bulk endpoint, which is API-key (Company) authenticated and does
    // not require an existing Valyd user account — correct for anonymous KYC.
    'credential' => [
        'base_url' => env('VC_BASE_URL', 'https://vc.pollus.tech'),
        'verify_path' => env('VC_VERIFY_PATH', 'api/bulk-verify-credential'),
        'api_key' => env('VC_API_KEY'),
        'timeout' => (int) env('VC_TIMEOUT', 360),
    ],

];
