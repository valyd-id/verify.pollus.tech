<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backend-to-backend client for idp.pollus.tech, used by the "verify once, reuse"
 * flow. Two auth surfaces:
 *  - TPSSO (client_id/client_secret + the end-user's access token) to LOG THE USER
 *    IN and READ their verified profile/licenses.
 *  - Internal (X-Internal-Auth shared secret) to WRITE verified data and run the
 *    returning-user face match.
 *
 * The IdP is the system of record for identity + biometrics; verify never stores
 * the face. See [[verify-valyd-service]] / [[idp-oauth-flow]].
 */
class IdpClient
{
    private function base(): string
    {
        return rtrim((string) config('services.idp.base_url'), '/');
    }

    private function timeout(): int
    {
        return (int) config('services.idp.timeout', 30);
    }

    private function internalKey(): string
    {
        return (string) config('services.idp.internal_auth_key');
    }

    /**
     * Exchange a TPSSO authorization code for tokens + the user identity.
     * @return array{ok:bool, pollus_id:?string, access_token:?string, user:array, error:?array}
     */
    public function exchangeCode(string $code): array
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->post($this->base() . '/api/auth/tpsso/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.idp.tpsso_client_id'),
                    'client_secret' => config('services.idp.tpsso_client_secret'),
                    'redirect_uri' => config('services.idp.tpsso_redirect_uri'),
                    'code' => $code,
                ]);

            $json = $res->json() ?? [];
            // The IdP wraps the token payload in { success, data: { access_token, user } }.
            $body = is_array($json['data'] ?? null) ? $json['data'] : $json;
            $user = $body['user'] ?? [];
            $accessToken = $body['access_token'] ?? null;
            $pollusId = $user['pollus_id'] ?? $user['sub'] ?? $user['id'] ?? null;

            if (!$res->successful() || !$accessToken || !$pollusId) {
                return ['ok' => false, 'pollus_id' => null, 'access_token' => null, 'user' => [], 'error' => $json['error'] ?? ['code' => 'token_exchange_failed', 'message' => 'Could not complete Valyd login']];
            }

            return ['ok' => true, 'pollus_id' => (string) $pollusId, 'access_token' => (string) $accessToken, 'user' => $user, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('IdpClient exchangeCode failed: ' . $e->getMessage());
            return ['ok' => false, 'pollus_id' => null, 'access_token' => null, 'user' => [], 'error' => ['code' => 'exception', 'message' => $e->getMessage()]];
        }
    }

    /**
     * Validate an end-user's Valyd access token and read their identity. This is
     * how a verify session is bound to a logged-in Valyd user: the integrator logs
     * the user in with Valyd (TPSSO) on their own site, then hands us the resulting
     * access token when creating the session. A valid response IS the proof of
     * login; `pollus_id` is the stable user key.
     * @return array{ok:bool, pollus_id:?string, user:array, error:?array}
     */
    public function userinfo(string $accessToken): array
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->withToken($accessToken)
                ->get($this->base() . '/api/auth/tpsso/userinfo');
            $json = $res->json() ?? [];
            // The IdP wraps success responses in { success, data: {...} } — unwrap before
            // reading. /tpsso/userinfo exposes `sub` (the IdP user id) + `anon_id`, not a
            // `pollus_id`, so fall through those for the stable per-user key.
            $body = is_array($json['data'] ?? null) ? $json['data'] : $json;
            $pollusId = $body['pollus_id'] ?? $body['sub'] ?? $body['anon_id'] ?? $body['id'] ?? null;

            if (!$res->successful() || !$pollusId) {
                return ['ok' => false, 'pollus_id' => null, 'user' => [], 'error' => $json['error'] ?? ['code' => 'valyd_login_required', 'message' => 'Valyd login is not valid or has expired.']];
            }

            return ['ok' => true, 'pollus_id' => (string) $pollusId, 'user' => $body, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('IdpClient userinfo failed: ' . $e->getMessage());
            return ['ok' => false, 'pollus_id' => null, 'user' => [], 'error' => ['code' => 'exception', 'message' => $e->getMessage()]];
        }
    }

    /**
     * Read the user's verification status + licenses with their access token.
     * @return array{human_verified:bool, id_verified:bool, licenses:array}
     */
    public function verifications(string $accessToken): array
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->withToken($accessToken)
                ->get($this->base() . '/api/auth/tpsso/verifications');
            $json = $res->json() ?? [];
            $v = $json['verifications'] ?? $json['data']['verifications'] ?? $json['data'] ?? $json;
            return [
                'human_verified' => (bool) ($v['human_verified'] ?? false),
                'id_verified' => (bool) ($v['id_verified'] ?? false),
                'licenses' => $v['licenses'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('IdpClient verifications failed: ' . $e->getMessage());
            return ['human_verified' => false, 'id_verified' => false, 'licenses' => []];
        }
    }

    /**
     * Match a fresh selfie against the user's stored face (returning-user reuse).
     * @return array{ok:bool, match:bool, score:?float}
     */
    public function faceMatch(string $pollusId, string $selfie): array
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->withHeaders(['X-Internal-Auth' => $this->internalKey()])
                ->post($this->base() . '/api/internal/face-match', [
                    'pollus_id' => $pollusId,
                    'selfie' => $selfie,
                ]);
            $data = $res->json()['data'] ?? [];
            return [
                'ok' => $res->successful() && (bool) ($res->json()['success'] ?? false),
                'match' => (bool) ($data['match'] ?? false),
                'score' => isset($data['score']) ? (float) $data['score'] : null,
            ];
        } catch (\Throwable $e) {
            Log::error('IdpClient faceMatch failed: ' . $e->getMessage());
            return ['ok' => false, 'match' => false, 'score' => null];
        }
    }

    /**
     * Upsert a verified license onto the user's IdP record (system of record).
     */
    public function recordLicense(string $pollusId, array $payload): bool
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->withHeaders(['X-Internal-Auth' => $this->internalKey()])
                ->post($this->base() . '/api/wc/licenses/internal_record_license_verification', array_merge([
                    'pollus_id' => $pollusId,
                    'verified_from' => 'verify',
                ], $payload));
            return $res->successful() && (bool) ($res->json()['success'] ?? true);
        } catch (\Throwable $e) {
            Log::error('IdpClient recordLicense failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark the user id_verified (+ names) on the IdP after a first-time verification.
     */
    public function setUserVerified(string $anonOrPollusId, array $fields = []): bool
    {
        try {
            $res = Http::acceptJson()->timeout($this->timeout())
                ->withHeaders(['X-Internal-Auth' => $this->internalKey()])
                ->post($this->base() . '/api/internal/verify-user', array_merge([
                    'anon_id' => $anonOrPollusId,
                    'id_verified' => true,
                ], $fields));
            return $res->successful() && (bool) ($res->json()['success'] ?? true);
        } catch (\Throwable $e) {
            Log::error('IdpClient setUserVerified failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Push a completed verification back to the IdP (the system of record), keyed
     * by pollus_id: mark the user id_verified (+ name) and upsert any verified
     * license. Best-effort — logs and swallows failures so a write-back hiccup
     * never fails the user's verification.
     */
    public function writeBackSession(\App\Models\VerificationSession $session): void
    {
        $pollusId = $session->pollus_id;
        if (!$pollusId) {
            return;
        }
        $checks = $session->checks()->get()->keyBy('type');

        $id = $checks->get(\App\Models\VerificationCheck::TYPE_ID);
        if ($id && $id->status === \App\Models\VerificationCheck::STATUS_PASSED) {
            $fields = $id->data['fields'] ?? [];
            $this->setUserVerified($pollusId, array_filter([
                'full_name' => $fields['full_name'] ?? null,
                'dob' => $id->data['dob'] ?? ($fields['date_of_birth'] ?? null),
            ], fn ($v) => $v !== null && $v !== ''));
        }

        $cred = $checks->get(\App\Models\VerificationCheck::TYPE_CREDENTIAL);
        if ($cred && $cred->status === \App\Models\VerificationCheck::STATUS_PASSED) {
            $license = $cred->data['license'] ?? $cred->data ?? [];
            $this->recordLicense($pollusId, array_filter([
                'license_type' => $license['type'] ?? ($cred->data['license_type'] ?? null),
                'license_state' => $license['state'] ?? ($cred->data['license_state'] ?? null),
                'license_number' => $license['number'] ?? ($cred->data['license_number'] ?? null),
                'status' => 'verified',
                'data' => $license,
            ], fn ($v) => $v !== null && $v !== ''));
        }
    }
}
