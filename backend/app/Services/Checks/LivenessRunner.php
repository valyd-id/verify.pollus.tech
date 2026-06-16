<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;
use App\Services\FaceOnLive\FaceService;

/**
 * Passive liveness on a selfie image.
 */
class LivenessRunner
{
    public function __construct(private FaceService $faceService)
    {
    }

    /**
     * @param string $selfieBytes Raw selfie image bytes.
     * @param int|null $threshold Minimum live score (0..100); defaults to config.
     */
    public function run(string $selfieBytes, ?int $threshold = null): CheckResult
    {
        $info = $this->faceService->getLivenessInfo($selfieBytes);

        if (!($info['success'] ?? false)) {
            return CheckResult::failed(
                VerificationCheck::TYPE_LIVENESS,
                $info['error']['message'] ?? 'Liveness check failed',
            );
        }

        // FaceOnLive's /face/liveness returns a VERDICT FLAG, not a 0-100 confidence:
        //   live_score === 1  → genuine / live
        //   live_score === 0  → spoof
        //   negative (e.g. -102) → no face detected
        // (Matches idp.pollus.tech, which fails whenever live_score != 1.)
        $score = (int) ($info['live_score'] ?? 0);
        $result = $info['result'] ?? null;
        $isLive = $score === 1;

        $data = ['live_score' => $score, 'result' => $result];

        if ($isLive) {
            return CheckResult::passed(VerificationCheck::TYPE_LIVENESS, (float) $score, $data);
        }

        $reason = $result && strtolower((string) $result) === 'spoof'
            ? 'Liveness failed — possible spoof or poor image quality'
            : ($result ?: 'Liveness check failed');
        return CheckResult::failed(VerificationCheck::TYPE_LIVENESS, $reason, (float) $score, $data);
    }
}
