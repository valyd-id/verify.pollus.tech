<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\VerificationCheck;
use App\Models\VerificationDocument;
use App\Models\VerificationSession;
use App\Services\Checks\AgeRunner;
use App\Services\Checks\CredentialRunner;
use App\Services\Checks\FaceMatchRunner;
use App\Services\Checks\IdVerificationRunner;
use App\Services\Checks\LivenessRunner;
use App\Services\Checks\LocationRunner;
use App\Services\BillingService;
use App\Services\SessionService;
use App\Support\ImageInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * End-user hosted page endpoints. Authenticated by the session_token (resolved
 * by SessionTokenAuth middleware into `verification_session`).
 */
class HostedController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private IdVerificationRunner $idRunner,
        private LivenessRunner $livenessRunner,
        private FaceMatchRunner $faceMatchRunner,
        private AgeRunner $ageRunner,
        private CredentialRunner $credentialRunner,
        private LocationRunner $locationRunner,
        private BillingService $billing,
    ) {
    }

    private function session(Request $request): VerificationSession
    {
        return $request->attributes->get('verification_session');
    }

    private function disk()
    {
        return Storage::disk(config('verify.image_disk', 'local'));
    }

    public function state(Request $request)
    {
        $session = $this->session($request);
        $checks = $session->checks()->get()->keyBy('type');

        $steps = [];
        foreach ($session->features as $feature) {
            $steps[] = [
                'feature' => $feature,
                'status' => $checks->get($feature)->status ?? 'pending',
            ];
        }

        $docs = $session->documents()->pluck('type')->unique()->values();
        $next = collect($steps)->firstWhere('status', 'pending')['feature'] ?? null;

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'features' => $session->features,
            'steps' => $steps,
            'documents' => $docs,
            'next_step' => $next,
            'expires_at' => $session->expires_at->toIso8601String(),
            'redirect_url' => $session->redirect_url,
        ]);
    }

    /**
     * Authoritative result for a session, resolved directly by session_token.
     * NOT behind session.token middleware so it works on terminal sessions.
     */
    public function result(Request $request, string $token)
    {
        $session = VerificationSession::where('session_token', $token)->first();
        if (!$session) {
            return GlobalHelper::apiError('not_found', 'Session not found.', 404);
        }

        $checks = $session->checks()->get()->map(fn ($c) => [
            'type' => $c->type,
            'status' => $c->status,
            'error' => $c->error,
        ])->values();

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'decision' => $session->decision,
            'checks' => $checks,
            'redirect_url' => $session->redirect_url,
        ]);
    }

    public function uploadDocument(Request $request)
    {
        $session = $this->session($request);

        $validated = $request->validate([
            'type' => 'required|string|in:id_front,id_back,selfie',
        ]);

        $bytes = ImageInput::bytes($request, 'image');
        if ($bytes === null) {
            return GlobalHelper::apiError('invalid_image', 'A valid `image` (file or base64) is required.', 400);
        }

        $path = "verify/{$session->id}/{$validated['type']}_" . Str::random(8) . '.img';
        $this->disk()->put($path, $bytes);

        $doc = VerificationDocument::updateOrCreate(
            ['session_id' => $session->id, 'type' => $validated['type']],
            ['storage_path' => $path, 'mime' => $request->file('image')?->getMimeType()]
        );

        if ($session->status === VerificationSession::STATUS_NOT_STARTED) {
            $session->update(['status' => VerificationSession::STATUS_IN_PROGRESS]);
        }

        return GlobalHelper::apiSuccess(['document_id' => $doc->id, 'type' => $doc->type]);
    }

    /**
     * Run one workflow check using previously-uploaded documents.
     * {check} is one of: id-verification | liveness | face-match | age | credential
     */
    public function runCheck(Request $request, string $token, string $check)
    {
        $session = $this->session($request);
        $feature = str_replace('-', '_', $check);

        if (!in_array($feature, $session->features, true)) {
            return GlobalHelper::apiError('feature_not_in_workflow', "Feature '{$feature}' is not part of this session's workflow.", 400);
        }

        // Charge the project's account for this check before running it; the
        // wrapper refunds automatically if it throws or returns a validation
        // error (i.e. no billable work happened). Ownerless sessions aren't billed.
        $owner = $session->project?->owner;
        $result = $this->billing->runCharged($owner, $feature, fn () => match ($feature) {
            VerificationCheck::TYPE_ID => $this->runId($session),
            VerificationCheck::TYPE_LIVENESS => $this->runLiveness($session),
            VerificationCheck::TYPE_FACE_MATCH => $this->runFaceMatch($session),
            VerificationCheck::TYPE_AGE => $this->runAge($session, $request),
            VerificationCheck::TYPE_CREDENTIAL => $this->runCredential($session, $request),
            VerificationCheck::TYPE_LOCATION => $this->runLocation($session, $request),
            default => null,
        }, $session->id);

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result; // an early validation error (already refunded)
        }

        $session = $this->sessions->recordCheck($session, $result);

        return GlobalHelper::apiSuccess([
            'check' => $result->toArray(),
            'session_status' => $session->status,
        ]);
    }

    public function decline(Request $request)
    {
        $session = $this->session($request);
        $this->sessions->decline($session);
        return GlobalHelper::apiSuccess(['status' => $session->refresh()->status]);
    }

    // --- check helpers -------------------------------------------------------

    private function docBytes(VerificationSession $session, string $type): ?string
    {
        $doc = $session->documents()->where('type', $type)->latest('id')->first();
        if (!$doc || !$this->disk()->exists($doc->storage_path)) {
            return null;
        }
        return $this->disk()->get($doc->storage_path);
    }

    private function tmpFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vrf_');
        file_put_contents($path, $bytes);
        return $path;
    }

    private function runId(VerificationSession $session)
    {
        $frontBytes = $this->docBytes($session, VerificationDocument::TYPE_ID_FRONT);
        if ($frontBytes === null) {
            return GlobalHelper::apiError('missing_document', 'Upload an id_front document before running id-verification.', 400);
        }
        $backBytes = $this->docBytes($session, VerificationDocument::TYPE_ID_BACK);

        $frontPath = $this->tmpFile($frontBytes);
        $backPath = $backBytes !== null ? $this->tmpFile($backBytes) : null;

        try {
            return $this->idRunner->run($frontPath, $backPath);
        } finally {
            @unlink($frontPath);
            if ($backPath) {
                @unlink($backPath);
            }
        }
    }

    private function runLiveness(VerificationSession $session)
    {
        $selfie = $this->docBytes($session, VerificationDocument::TYPE_SELFIE);
        if ($selfie === null) {
            return GlobalHelper::apiError('missing_document', 'Upload a selfie before running liveness.', 400);
        }
        return $this->livenessRunner->run($selfie, $session->settings['liveness_threshold'] ?? null);
    }

    private function runFaceMatch(VerificationSession $session)
    {
        $idBytes = $this->docBytes($session, VerificationDocument::TYPE_ID_FRONT);
        $selfie = $this->docBytes($session, VerificationDocument::TYPE_SELFIE);
        if ($idBytes === null || $selfie === null) {
            return GlobalHelper::apiError('missing_document', 'Both id_front and selfie are required for face-match.', 400);
        }
        return $this->faceMatchRunner->run($idBytes, $selfie, $session->settings['face_match_threshold'] ?? null);
    }

    private function runAge(VerificationSession $session, Request $request)
    {
        $dob = $request->input('dob') ?? $this->extractDob($session);
        if (!$dob) {
            return GlobalHelper::apiError('missing_dob', 'A date of birth is required (provide `dob` or run id-verification first).', 400);
        }
        $bands = $session->settings['age_bands'] ?? [];
        return $this->ageRunner->run((string) $dob, $bands);
    }

    private function runLocation(VerificationSession $session, Request $request)
    {
        $denied = $request->boolean('denied');
        if ($denied) {
            return $this->locationRunner->run(null, null, null, true);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);
        return $this->locationRunner->run(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
        );
    }

    private function runCredential(VerificationSession $session, Request $request)
    {
        $input = $request->validate([
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'full_name' => 'nullable|string',
            'license_type' => 'nullable|string',
            'provider_code' => 'nullable|string',
            'license_state' => 'required_without:state|string',
            'state' => 'nullable|string',
            'license_number' => 'required_without:license_no|string',
            'license_no' => 'nullable|string',
            'npi' => 'nullable|string',
        ]);

        // KYC + License mode: when this workflow also verifies an ID document, the
        // license is checked against the KYC-verified name — taken server-side from
        // the completed ID check so the client cannot substitute a different
        // identity. The user only supplies the state, license type and number.
        if (in_array(VerificationCheck::TYPE_ID, $session->features, true)) {
            $name = $this->kycName($session);
            if ($name === null) {
                return GlobalHelper::apiError('kyc_required', 'Complete ID verification before verifying the license.', 400);
            }
            unset($input['first_name'], $input['last_name']);
            $input['full_name'] = $name;
        }

        return $this->credentialRunner->run($input);
    }

    /** The verified full name from the completed ID-verification (KYC) check, if any. */
    private function kycName(VerificationSession $session): ?string
    {
        $check = $session->checks()->where('type', VerificationCheck::TYPE_ID)->first();
        $name = $check?->data['fields']['full_name'] ?? null;
        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /** Pull a DOB out of a completed id_verification check (KYC), if any. */
    private function extractDob(VerificationSession $session): ?string
    {
        $check = $session->checks()->where('type', VerificationCheck::TYPE_ID)->first();
        // The id_verification runner already normalises a DOB onto the check data.
        if (!empty($check?->data['dob'])) {
            return (string) $check->data['dob'];
        }
        return \App\Services\Checks\IdVerificationRunner::extractDob($check?->data['ocr_data'] ?? []);
    }
}
