<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls vc.pollus.tech's bulk credential-verification endpoint to verify a
 * professional license. API-key authenticated; no Valyd user account required.
 */
class CredentialClient
{
    /**
     * @param array $input Keys: first_name, last_name|full_name, license_type|provider_code,
     *                     license_state, license_number, npi (optional)
     * @return array{success: bool, match: bool, data: array, error: array|null}
     */
    public function verify(array $input): array
    {
        $base = rtrim((string) config('services.credential.base_url'), '/');
        $path = ltrim((string) config('services.credential.verify_path'), '/');
        $url = "{$base}/{$path}";
        $apiKey = (string) config('services.credential.api_key');
        $timeout = (int) config('services.credential.timeout', 360);

        if ($apiKey === '') {
            return [
                'success' => false,
                'match' => false,
                'data' => [],
                'error' => ['code' => 'not_configured', 'message' => 'VC_API_KEY is not configured'],
            ];
        }

        try {
            $response = Http::withHeaders(['X-API-Key' => $apiKey])
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, array_filter([
                    'first_name' => $input['first_name'] ?? null,
                    'last_name' => $input['last_name'] ?? null,
                    'full_name' => $input['full_name'] ?? null,
                    'license_type' => $input['license_type'] ?? null,
                    'provider_code' => $input['provider_code'] ?? null,
                    'license_state' => $input['license_state'] ?? ($input['state'] ?? null),
                    'license_number' => $input['license_number'] ?? ($input['license_no'] ?? null),
                    'npi' => $input['npi'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));

            $json = $response->json() ?? [];
            $success = (bool) ($json['success'] ?? false);
            // vc returns { success, data: { match: bool, data: {...} } }
            $payload = $json['data'] ?? [];
            $match = (bool) ($payload['match'] ?? false);

            return [
                'success' => $success,
                'match' => $match,
                'data' => $payload,
                'error' => $success ? null : ($json['error'] ?? [
                    'code' => 'verification_failed',
                    'message' => 'Credential verification failed',
                ]),
            ];
        } catch (\Exception $e) {
            Log::error('CredentialClient verify failed: ' . $e->getMessage());
            return [
                'success' => false,
                'match' => false,
                'data' => [],
                'error' => ['code' => 'exception', 'message' => $e->getMessage()],
            ];
        }
    }

    /**
     * List the states/jurisdictions that have credential providers.
     * @return array vc payload, e.g. { states: [{ state_name, state_code, ... }] }
     */
    public function states(): array
    {
        return $this->discover('credential/states');
    }

    /**
     * List the credential providers (license types) available for a given state,
     * each with its `required_fields` so the UI can render the right form.
     * @return array vc payload, e.g. { state_code, providers: [{ provider_code, ... }] }
     */
    public function providers(string $state): array
    {
        return $this->discover('credential/states/' . rawurlencode($state) . '/providers');
    }

    /**
     * GET a vc.pollus.tech discovery endpoint (public metadata). Successful
     * responses are cached briefly — these lists change rarely. Failures are not
     * cached so a transient vc outage self-heals on the next request.
     */
    private function discover(string $path): array
    {
        $base = rtrim((string) config('services.credential.base_url'), '/');
        $url = "{$base}/api/{$path}";
        $cacheKey = 'vc_discover:' . sha1($url);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $apiKey = (string) config('services.credential.api_key');
            $request = Http::acceptJson()->timeout(30);
            if ($apiKey !== '') {
                $request = $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            $response = $request->get($url);
            $json = $response->json();

            if ($response->successful() && is_array($json)) {
                Cache::put($cacheKey, $json, 600); // 10 min
                return $json;
            }

            return ['error' => ['code' => 'discovery_failed', 'message' => 'Could not load credential options']];
        } catch (\Exception $e) {
            Log::error('CredentialClient discover failed: ' . $e->getMessage());
            return ['error' => ['code' => 'exception', 'message' => 'Could not load credential options']];
        }
    }
}
