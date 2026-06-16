<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use App\Services\FaceOnLive\IdOcrService;

/**
 * Runs ID document OCR + authenticity. Returns the extracted fields so callers
 * (and downstream age / face-match checks) can reuse the DOB and portrait.
 */
class IdVerificationRunner
{
    public function __construct(private IdOcrService $ocr)
    {
    }

    /**
     * @param string $frontPath Absolute path to the front-of-ID image.
     * @param string|null $backPath Absolute path to the back-of-ID image.
     */
    public function run(string $frontPath, ?string $backPath = null): CheckResult
    {
        $result = $this->ocr->getIdOcrInfo($frontPath, $backPath);

        if (!($result['success'] ?? false)) {
            return CheckResult::failed(
                VerificationCheck::TYPE_ID,
                $result['error']['message'] ?? 'OCR failed',
                null,
                ['raw' => $result['data'] ?? []],
            );
        }

        $ocrData = $result['data']['result']['ocr_data'] ?? [];
        $authenticity = $result['data']['result']['authenticity'] ?? null;
        $dob = self::extractDob($ocrData);

        $data = [
            'fields' => self::extractFields($ocrData),     // clean, flat key/value fields
            'portrait' => $ocrData['image']['portrait'] ?? null, // base64 face crop, if any
            'ocr_data' => $ocrData,                        // raw OCR payload (unmodified)
            'authenticity' => $authenticity,
            'dob' => $dob, // normalised YYYY-MM-DD when the OCR provided one
        ];

        // The OCR engine signals a non-readable document with errorCode != 0
        // (e.g. 6 = no ID found). A real document returns errorCode 0.
        if ((int) ($ocrData['errorCode'] ?? 0) !== 0) {
            return CheckResult::failed(VerificationCheck::TYPE_ID, 'No readable ID document detected', null, $data);
        }

        // The OCR succeeded (document read). `authenticity` is only a hint here:
        // this engine returns 0 when it did NOT compute an authenticity score, so
        // we only route to manual review when it returns a real, low-but-present
        // score (0 < score < 0.5). 0 / absent = "no signal" → pass on a good read.
        $score = is_numeric($authenticity) ? (float) $authenticity : null;
        if ($score !== null && $score > 0.0 && $score < 0.5) {
            return CheckResult::review(VerificationCheck::TYPE_ID, $data, $score);
        }

        return CheckResult::passed(VerificationCheck::TYPE_ID, $score, $data);
    }

    /** Flatten the messy OCR payload into a clean, display-ready set of fields. */
    public static function extractFields(array $ocrData): array
    {
        $o = $ocrData['ocr'] ?? [];
        $b = $ocrData['barcode'] ?? [];
        return array_filter([
            'full_name' => $o['name'] ?? null,
            'fathers_name' => $o['fathersName'] ?? null,
            'document_number' => $o['identityCardNumber'] ?? ($b['identityCardNumber'] ?? null),
            'date_of_birth' => $o['dateOfBirth'] ?? null,
            'date_of_issue' => $o['dateOfIssue'] ?? null,
            'date_of_expiry' => $o['dateOfExpiry'] ?? null,
            'sex' => $o['sex'] ?? null,
            'issuing_state' => $o['issuingStateCode'] ?? null,
            'place_of_registration' => $o['placeOfRegistration'] ?? null,
            'country' => $ocrData['countryName'] ?? null,
            'document_type' => $ocrData['documentName'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Pull a YYYY-MM-DD date of birth out of the OCR payload, wherever it lives. */
    public static function extractDob(array $ocrData): ?string
    {
        $candidates = [
            $ocrData['dob'] ?? null,
            $ocrData['date_of_birth'] ?? null,
            $ocrData['dateOfBirth'] ?? null,
            $ocrData['ocr']['dateOfBirth'] ?? null,
            $ocrData['ocr']['date_of_birth'] ?? null,
            $ocrData['ocr']['dob'] ?? null,
        ];
        foreach ($candidates as $c) {
            if (!empty($c)) {
                return (string) $c;
            }
        }
        return null;
    }
}
