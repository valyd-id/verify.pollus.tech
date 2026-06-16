<?php

namespace App\Services;

use App\Models\VerificationCheck;
use App\Models\VerificationSession;

/**
 * Maps the per-check results of a session onto an overall session status,
 * applying the workflow's settings (required features, auto-approve).
 */
class DecisionService
{
    /**
     * @return array{status: string, complete: bool, summary: array}
     */
    public function evaluate(VerificationSession $session): array
    {
        $settings = $session->settings ?? [];
        $features = $session->features ?? [];
        $required = $settings['required_features'] ?? $features;
        $autoApprove = $settings['auto_approve'] ?? true;

        // Latest check per type.
        $checks = $session->checks()->get()->keyBy('type');

        $summary = [];
        $anyFailed = false;
        $anyReview = false;
        $allRequiredDone = true;

        foreach ($features as $feature) {
            $check = $checks->get($feature);
            $summary[$feature] = [
                'status' => $check->status ?? 'pending',
                'score' => $check->score ?? null,
            ];
        }

        foreach ($required as $feature) {
            $check = $checks->get($feature);
            if (!$check) {
                $allRequiredDone = false;
                continue;
            }
            if ($check->status === VerificationCheck::STATUS_FAILED) {
                $anyFailed = true;
            } elseif ($check->status === VerificationCheck::STATUS_REVIEW) {
                $anyReview = true;
            } elseif ($check->status !== VerificationCheck::STATUS_PASSED) {
                $allRequiredDone = false;
            }
        }

        // Decisioning precedence: a failed required check declines immediately.
        if ($anyFailed) {
            return ['status' => VerificationSession::STATUS_DECLINED, 'complete' => true, 'summary' => $summary];
        }

        if (!$allRequiredDone) {
            return ['status' => VerificationSession::STATUS_IN_PROGRESS, 'complete' => false, 'summary' => $summary];
        }

        // All required checks have run and none failed.
        if ($anyReview || !$autoApprove) {
            return ['status' => VerificationSession::STATUS_IN_REVIEW, 'complete' => true, 'summary' => $summary];
        }

        return ['status' => VerificationSession::STATUS_APPROVED, 'complete' => true, 'summary' => $summary];
    }
}
