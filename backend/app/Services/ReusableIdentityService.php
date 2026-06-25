<?php

namespace App\Services;

use App\Models\ReusableIdentity;
use App\Models\VerificationCheck;
use App\Models\VerificationSession;
use App\Services\FaceOnLive\FaceService;
use App\Support\ImageInput;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stores & matches reusable verified identities (the "verify once, reuse" store).
 * verify.pollus.tech is the system of record; the IdP only supplies the pollus_id.
 * Records are isolated per app — keyed by (project_id, pollus_id).
 */
class ReusableIdentityService
{
    public function __construct(private FaceService $faceService)
    {
    }

    public function find(int $projectId, string $pollusId): ?ReusableIdentity
    {
        return ReusableIdentity::where('project_id', $projectId)->where('pollus_id', $pollusId)->first();
    }

    /** A still-usable record (exists, not revoked, not expired). */
    public function findActive(int $projectId, string $pollusId): ?ReusableIdentity
    {
        $rec = $this->find($projectId, $pollusId);
        return $rec && $rec->isActive() ? $rec : null;
    }

    /**
     * Capture/refresh the reusable record from a completed verification session.
     * Called when a reuse-workflow session (with a pollus_id) reaches APPROVED.
     */
    public function captureFromSession(VerificationSession $session): ?ReusableIdentity
    {
        if (empty($session->pollus_id)) {
            return null;
        }

        $checks = $session->checks()->get()->keyBy('type');
        $id = $checks->get(VerificationCheck::TYPE_ID);
        $fields = $id?->data['fields'] ?? [];

        $fullName = $fields['full_name'] ?? null;
        $dob = $id?->data['dob'] ?? ($fields['date_of_birth'] ?? null);

        $ageBands = $checks->get(VerificationCheck::TYPE_AGE)?->data['bands'] ?? null;

        // Licenses: reflect any credential check that passed.
        $licenses = [];
        $cred = $checks->get(VerificationCheck::TYPE_CREDENTIAL);
        if ($cred && $cred->status === VerificationCheck::STATUS_PASSED) {
            $licenses[] = [
                'status' => 'verified',
                'data' => $cred->data['license'] ?? $cred->data ?? [],
                'checked_at' => Carbon::now()->toIso8601String(),
            ];
        }

        // Face embedding from the captured selfie (so a return visit can match).
        $embedding = $this->embeddingFromSelfie($session);

        return ReusableIdentity::updateOrCreate(
            ['project_id' => $session->project_id, 'pollus_id' => $session->pollus_id],
            [
                'vendor_data' => $session->vendor_data,
                'full_name' => $fullName,
                'dob' => $dob,
                'age_bands' => $ageBands,
                'face_embedding' => $embedding,
                'licenses' => $licenses,
                'source_session_id' => $session->id,
                'verified_at' => Carbon::now(),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Match a fresh selfie (base64 / data URL / raw bytes) against the stored face.
     * @return array{ok:bool, match:bool, score:?float}
     */
    public function matchSelfie(ReusableIdentity $rec, string $selfie): array
    {
        $stored = $rec->face_embedding;
        if (BiometricUtils::isEmptyOrZeroVector($stored)) {
            return ['ok' => true, 'match' => false, 'score' => null];
        }

        $bytes = ImageInput::fromString($selfie) ?? $selfie;
        try {
            $fresh = $this->faceService->getFeatureInfo($bytes)['feature'] ?? [];
            if (BiometricUtils::isEmptyOrZeroVector($fresh)) {
                return ['ok' => true, 'match' => false, 'score' => null];
            }
            $score = (float) $this->faceService->getFaceSimilarity($stored, $fresh, BiometricUtils::FEATURE_SIZE);
            return ['ok' => true, 'match' => $score >= BiometricUtils::TARGET_SIM, 'score' => $score];
        } catch (\Throwable $e) {
            Log::error('ReusableIdentity matchSelfie failed: ' . $e->getMessage());
            return ['ok' => false, 'match' => false, 'score' => null];
        }
    }

    /** Integrator-facing payload (decision API + on-demand read API). */
    public function present(ReusableIdentity $rec): array
    {
        return [
            'pollus_id' => $rec->pollus_id,
            'vendor_data' => $rec->vendor_data,
            'full_name' => $rec->full_name,
            'dob' => $rec->dob,
            'age_bands' => $rec->age_bands,
            'licenses' => $rec->licenses,
            'verified_at' => $rec->verified_at?->toIso8601String(),
            'reused' => true,
        ];
    }

    private function embeddingFromSelfie(VerificationSession $session): ?array
    {
        $doc = $session->documents()->where('type', 'selfie')->latest('id')->first();
        if (!$doc) {
            return null;
        }
        try {
            $disk = Storage::disk(config('verify.image_disk', 'local'));
            if (!$disk->exists($doc->storage_path)) {
                return null;
            }
            $feature = $this->faceService->getFeatureInfo($disk->get($doc->storage_path))['feature'] ?? [];
            return BiometricUtils::isEmptyOrZeroVector($feature) ? null : $feature;
        } catch (\Throwable $e) {
            Log::error('ReusableIdentity embeddingFromSelfie failed: ' . $e->getMessage());
            return null;
        }
    }
}
