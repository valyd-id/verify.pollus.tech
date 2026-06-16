<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use App\Services\CredentialClient;

/**
 * Professional-license verification via vc.pollus.tech.
 */
class CredentialRunner
{
    public function __construct(private CredentialClient $client)
    {
    }

    /**
     * @param array $input first_name, last_name|full_name, license_type|provider_code,
     *                     license_state|state, license_number|license_no, npi
     */
    public function run(array $input): CheckResult
    {
        $result = $this->client->verify($input);

        if (!($result['success'] ?? false)) {
            return CheckResult::failed(
                VerificationCheck::TYPE_CREDENTIAL,
                $result['error']['message'] ?? 'Credential verification failed',
                null,
                ['raw' => $result['data'] ?? []],
            );
        }

        $matched = (bool) ($result['match'] ?? false);
        $data = ['match' => $matched, 'license' => $result['data']['data'] ?? $result['data'] ?? []];

        return $matched
            ? CheckResult::passed(VerificationCheck::TYPE_CREDENTIAL, null, $data)
            : CheckResult::failed(VerificationCheck::TYPE_CREDENTIAL, 'License could not be matched', null, $data);
    }
}
