<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use App\Services\BiometricUtils;
use App\Services\FaceOnLive\FaceService;

/**
 * 1:1 doc-to-selfie face match. Extracts the face embedding from the ID
 * document portrait and from the selfie, then compares them. No enrolled Valyd
 * user is involved — this is what makes the flow standalone / anonymous.
 */
class FaceMatchRunner
{
    public function __construct(private FaceService $faceService)
    {
    }

    /**
     * @param string $idImageBytes Raw bytes of the ID document image (portrait side).
     * @param string $selfieBytes  Raw bytes of the selfie.
     * @param float|null $threshold Similarity threshold (0..1); defaults to config.
     */
    public function run(string $idImageBytes, string $selfieBytes, ?float $threshold = null): CheckResult
    {
        $threshold = $threshold ?? (float) config('verify.face_match_threshold', 0.95);

        try {
            $idFeat = $this->faceService->getFeatureInfo($idImageBytes)['feature'] ?? [];
            $selfieFeat = $this->faceService->getFeatureInfo($selfieBytes)['feature'] ?? [];
        } catch (\Exception $e) {
            return CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Feature extraction failed: ' . $e->getMessage());
        }

        if (BiometricUtils::isEmptyOrZeroVector($idFeat)) {
            return CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'No face detected on the ID document');
        }
        if (BiometricUtils::isEmptyOrZeroVector($selfieFeat)) {
            return CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'No face detected in the selfie');
        }

        try {
            $score = (float) $this->faceService->getFaceSimilarity($idFeat, $selfieFeat, BiometricUtils::FEATURE_SIZE);
        } catch (\Exception $e) {
            return CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Similarity comparison failed: ' . $e->getMessage());
        }

        $matched = $score >= $threshold;
        $data = ['similarity' => $score, 'threshold' => $threshold];

        return $matched
            ? CheckResult::passed(VerificationCheck::TYPE_FACE_MATCH, $score, $data)
            : CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Face does not match the ID document', $score, $data);
    }
}
