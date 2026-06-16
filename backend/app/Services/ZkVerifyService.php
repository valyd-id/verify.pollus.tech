<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zero-knowledge age-band proof client. Ported (slimmed to the generic
 * AGE_VERIFY_GTE proof) from idp.pollus.tech.
 */
class ZkVerifyService
{
    /**
     * Verify age >= $minAge via a ZK proof.
     *
     * @return array{success: bool, is_age_verified: bool|null, data: array, error: array|null}
     */
    public function verifyAgeGte(
        int $minAge,
        int $dobYear,
        int $dobMonth,
        int $dobDay,
        ?int $currentYear = null,
        ?int $currentMonth = null,
        ?int $currentDay = null
    ): array {
        $now = Carbon::now();
        $currentYear = $currentYear ?? $now->year;
        $currentMonth = $currentMonth ?? $now->month;
        $currentDay = $currentDay ?? $now->day;

        $apiUrl = config('services.zk_verify.url', 'http://localhost:4000/api/prove');
        $timeout = (int) config('services.zk_verify.timeout', 120);

        $payload = [
            'proofType' => 'AGE_VERIFY_GTE',
            'params' => [
                'dobYear' => $dobYear,
                'dobMonth' => $dobMonth,
                'dobDay' => $dobDay,
                'currentYear' => $currentYear,
                'currentMonth' => $currentMonth,
                'currentDay' => $currentDay,
                'minAge' => $minAge,
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl, $payload);

            $statusCode = $response->status();
            $responseData = $response->json();

            // 200 = proof verified; 400 = valid response but proof failed.
            if ($statusCode !== 200 && $statusCode !== 400) {
                return [
                    'success' => false,
                    'is_age_verified' => null,
                    'data' => $responseData ?? [],
                    'error' => [
                        'code' => 'HTTP_ERROR',
                        'message' => "ZK verify API returned status {$statusCode}",
                    ],
                ];
            }

            $success = $responseData['success'] ?? false;
            $publicSignals = $responseData['data']['publicSignals'] ?? [];
            $isAgeVerified = isset($publicSignals[0]) && $publicSignals[0] === '1';

            if ($success && $isAgeVerified) {
                return [
                    'success' => true,
                    'is_age_verified' => true,
                    'data' => $responseData['data'] ?? [],
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'is_age_verified' => false,
                'data' => $responseData['data'] ?? [],
                'error' => $responseData['error'] ?? [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => "Age verification failed: subject is under {$minAge}",
                ],
            ];
        } catch (\Exception $e) {
            Log::error("ZK Verify Exception (AGE_VERIFY_GTE - {$minAge}+)", ['exception' => $e->getMessage()]);
            return [
                'success' => false,
                'is_age_verified' => null,
                'data' => [],
                'error' => [
                    'code' => 'EXCEPTION',
                    'message' => 'ZK verify service error: ' . $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Verify age >= $minAge from a date string / Carbon.
     */
    public function verifyAgeGteFromDob(int $minAge, $dob): array
    {
        if ($dob instanceof Carbon) {
            $dobCarbon = $dob;
        } else {
            try {
                $dobCarbon = Carbon::parse($dob);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'is_age_verified' => null,
                    'data' => [],
                    'error' => [
                        'code' => 'INVALID_DOB',
                        'message' => 'Invalid date of birth format',
                    ],
                ];
            }
        }

        return $this->verifyAgeGte($minAge, $dobCarbon->year, $dobCarbon->month, $dobCarbon->day);
    }
}
