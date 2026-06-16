<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use Carbon\Carbon;

/**
 * Age-band verification computed directly from the date of birth (obtained from
 * the ID document via KYC/OCR, or supplied by the client). No ZK proof — the age
 * is derived from the DOB and compared against each requested band.
 */
class AgeRunner
{
    /** Map a band key like "is_18_plus" to its minimum age. */
    private const BAND_AGES = [
        'is_16_plus' => 16,
        'is_18_plus' => 18,
        'is_21_plus' => 21,
        'is_30_plus' => 30,
        'is_65_plus' => 65,
    ];

    /**
     * @param string $dob   Date of birth (ISO / parseable date, e.g. "2002-08-13").
     * @param array  $bands Band keys to assert, e.g. ["is_18_plus"]. Defaults to is_18_plus.
     */
    public function run(string $dob, array $bands = []): CheckResult
    {
        $bands = empty($bands) ? ['is_18_plus'] : $bands;

        try {
            $birth = Carbon::parse($dob);
        } catch (\Exception $e) {
            return CheckResult::failed(VerificationCheck::TYPE_AGE, 'Invalid or unreadable date of birth', null, ['dob' => $dob]);
        }
        if ($birth->isFuture()) {
            return CheckResult::failed(VerificationCheck::TYPE_AGE, 'Date of birth is in the future', null, ['dob' => $birth->toDateString()]);
        }

        $age = $birth->age; // whole years as of today
        $results = [];
        $allPassed = true;

        foreach ($bands as $band) {
            $minAge = self::BAND_AGES[$band] ?? null;
            if ($minAge === null) {
                $results[$band] = ['verified' => null, 'error' => 'unknown_band'];
                $allPassed = false;
                continue;
            }
            $ok = $age >= $minAge;
            $results[$band] = ['verified' => $ok, 'min_age' => $minAge];
            if (!$ok) {
                $allPassed = false;
            }
        }

        $data = ['age' => $age, 'dob' => $birth->toDateString(), 'bands' => $results];

        return $allPassed
            ? CheckResult::passed(VerificationCheck::TYPE_AGE, (float) $age, $data)
            : CheckResult::failed(VerificationCheck::TYPE_AGE, 'One or more age bands not satisfied', (float) $age, $data);
    }
}
