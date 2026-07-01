<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\ConsoleUser;
use App\Models\VerificationCheck;
use App\Models\VerificationProject;
use App\Models\VerificationSession;
use App\Services\Checks\AgeRunner;
use App\Services\Checks\CheckResult;
use App\Services\Checks\CredentialRunner;
use App\Services\Checks\FaceMatchRunner;
use App\Services\Checks\IdVerificationRunner;
use App\Services\Checks\LivenessRunner;
use App\Services\Checks\LocationRunner;
use App\Services\Checks\LocationMatchRunner;
use App\Services\BillingService;
use App\Services\CredentialClient;
use App\Services\IdpClient;
use App\Services\SessionService;
use App\Support\ImageInput;
use Illuminate\Http\Request;

/**
 * Standalone (direct, synchronous) verification APIs. The client supplies the
 * data/images and gets a result back immediately — no hosted UI, no webhook.
 * Each call records a lightweight audit session (mode=standalone, no callback).
 */
class StandaloneController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private IdVerificationRunner $idRunner,
        private LivenessRunner $livenessRunner,
        private FaceMatchRunner $faceMatchRunner,
        private AgeRunner $ageRunner,
        private CredentialRunner $credentialRunner,
        private LocationRunner $locationRunner,
        private LocationMatchRunner $locationMatchRunner,
        private BillingService $billing,
        private IdpClient $idp,
        private CredentialClient $credential,
    ) {
    }

    /**
     * Auto-resolve a `provider_code` (+ credential_code) from state + a friendly
     * license type (default "md"). So integrators pass state + "MD"/"RN"/… and
     * never need to know internal provider codes like `apps2_colorado_gov`.
     *
     * @return array{provider_code:?string, credential_code:?string}
     */
    private function resolveProvider(?string $state, ?string $type): array
    {
        $type = strtolower(trim((string) ($type ?: 'md')));
        // Friendly type → keywords to match against the provider's credential.
        $wants = match (true) {
            str_contains($type, 'md'), str_contains($type, 'physician'), str_contains($type, 'medical'), str_contains($type, 'doctor') => ['physician', 'medical', 'md'],
            str_contains($type, 'do'), str_contains($type, 'osteo') => ['osteopath', 'do'],
            str_contains($type, 'np'), str_contains($type, 'nurse practitioner') => ['nurse practitioner', 'np'],
            str_contains($type, 'rn'), str_contains($type, 'nurse') => ['registered nurse', 'nurse', 'rn'],
            str_contains($type, 'pa'), str_contains($type, 'assistant') => ['physician assistant', 'pa'],
            default => [$type],
        };
        try {
            $providers = $this->credential->providers((string) $state);
            $list = $providers['data']['providers'] ?? $providers['providers'] ?? (is_array($providers) ? $providers : []);
            foreach ($list as $p) {
                $hay = strtolower(($p['credential_name'] ?? '') . ' ' . ($p['credential_code'] ?? '') . ' ' . ($p['remote_code'] ?? ''));
                if ($hay === '') {
                    continue;
                }
                foreach ($wants as $w) {
                    // Short codes (md/do/pa/np) must match whole-word, or they hit substrings.
                    $ok = strlen($w) <= 3
                        ? (bool) preg_match('/\b' . preg_quote($w, '/') . '\b/', $hay)
                        : str_contains($hay, $w);
                    if ($ok) {
                        return [
                            'provider_code' => $p['provider_code'] ?? null,
                            'credential_code' => $p['credential_code'] ?? ($p['remote_code'] ?? null),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through — caller keeps whatever was provided
        }
        return ['provider_code' => null, 'credential_code' => null];
    }

    private function project(Request $request): VerificationProject
    {
        return $request->attributes->get('verification_project');
    }

    /** The account billed for this project's usage (null = ownerless, not billed). */
    private function owner(Request $request): ?ConsoleUser
    {
        return $this->project($request)->owner;
    }

    private function guardFeature(Request $request, string $feature): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->project($request)->allowsFeature($feature)) {
            return GlobalHelper::apiError('feature_not_allowed', "Feature '{$feature}' is not enabled for this project.", 403);
        }
        return null;
    }

    public function idVerification(Request $request)
    {
        if ($err = $this->guardFeature($request, 'id_verification')) {
            return $err;
        }

        $frontBytes = ImageInput::bytes($request, 'front_image');
        if ($frontBytes === null) {
            return GlobalHelper::apiError('invalid_image', 'A valid `front_image` (file or base64) is required.', 400);
        }
        $backBytes = ImageInput::bytes($request, 'back_image');

        $frontPath = $this->tmpFile($frontBytes);
        $backPath = $backBytes !== null ? $this->tmpFile($backBytes) : null;

        $result = $this->billing->runCharged($this->owner($request), 'id_verification', function () use ($frontPath, $backPath) {
            try {
                return $this->idRunner->run($frontPath, $backPath);
            } finally {
                @unlink($frontPath);
                if ($backPath) {
                    @unlink($backPath);
                }
            }
        });

        return $this->respond($request, 'id_verification', $result);
    }

    public function liveness(Request $request)
    {
        if ($err = $this->guardFeature($request, 'liveness')) {
            return $err;
        }
        $bytes = ImageInput::bytes($request, 'image');
        if ($bytes === null) {
            return GlobalHelper::apiError('invalid_image', 'A valid `image` (file or base64) is required.', 400);
        }
        $result = $this->billing->runCharged($this->owner($request), 'liveness', fn () => $this->livenessRunner->run($bytes));
        return $this->respond($request, 'liveness', $result);
    }

    public function faceMatch(Request $request)
    {
        if ($err = $this->guardFeature($request, 'face_match')) {
            return $err;
        }
        // image1 = ID/reference image, image2 = selfie.
        $idBytes = ImageInput::bytes($request, 'image1') ?? ImageInput::bytes($request, 'id_image');
        $selfieBytes = ImageInput::bytes($request, 'image2') ?? ImageInput::bytes($request, 'selfie');
        if ($idBytes === null || $selfieBytes === null) {
            return GlobalHelper::apiError('invalid_image', 'Two images are required: `image1` (reference) and `image2` (selfie).', 400);
        }
        $result = $this->billing->runCharged($this->owner($request), 'face_match', fn () => $this->faceMatchRunner->run($idBytes, $selfieBytes));
        return $this->respond($request, 'face_match', $result);
    }

    public function ageVerification(Request $request)
    {
        if ($err = $this->guardFeature($request, 'age')) {
            return $err;
        }
        $validated = $request->validate([
            'dob' => 'required|string',
            'bands' => 'nullable|array',
            'bands.*' => 'string',
        ]);
        $result = $this->billing->runCharged($this->owner($request), 'age', fn () => $this->ageRunner->run($validated['dob'], $validated['bands'] ?? []));
        return $this->respond($request, 'age', $result);
    }

    public function location(Request $request)
    {
        if ($err = $this->guardFeature($request, 'location')) {
            return $err;
        }
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);
        $result = $this->billing->runCharged($this->owner($request), 'location', fn () => $this->locationRunner->run(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
        ));
        return $this->respond($request, 'location', $result);
    }

    /**
     * Location MATCH: captured GPS vs an expected point → { match, distance_m }.
     * The sellable "proof of presence" check (unlike `location`, which only geocodes).
     */
    public function locationMatch(Request $request)
    {
        if ($err = $this->guardFeature($request, 'location_match')) {
            return $err;
        }
        $v = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'expected_latitude' => 'required|numeric',
            'expected_longitude' => 'required|numeric',
            'radius_m' => 'nullable|numeric',
        ]);
        $result = $this->billing->runCharged($this->owner($request), 'location_match', fn () => $this->locationMatchRunner->run(
            (float) $v['latitude'],
            (float) $v['longitude'],
            (float) $v['expected_latitude'],
            (float) $v['expected_longitude'],
            isset($v['radius_m']) ? (float) $v['radius_m'] : null,
            isset($v['accuracy']) ? (float) $v['accuracy'] : null,
        ));
        return $this->respond($request, 'location_match', $result);
    }

    /**
     * EVV Presence bundle: face match (selfie vs reference) + location match
     * (captured vs expected) in one call → proves the right person is at the
     * right place. Billed once as `evv_presence` (below the sum of the parts).
     */
    public function evvPresence(Request $request)
    {
        if ($err = $this->guardFeature($request, 'evv_presence')) {
            return $err;
        }
        // ACCOUNT mode: pass a `valyd_access_token` (or `pollus_id`) + only a `selfie`.
        // The selfie is matched against the user's STORED Valyd face vector — no ID image.
        // FRESH mode: pass two images (`image1` reference + `image2` selfie).
        $accountToken = $request->input('valyd_access_token');
        $pollusId = $request->input('pollus_id');
        $selfieBytes = ImageInput::bytes($request, 'image2') ?? ImageInput::bytes($request, 'selfie');
        $isAccount = !empty($accountToken) || !empty($pollusId);

        if ($selfieBytes === null) {
            return GlobalHelper::apiError('invalid_image', 'A `selfie` is required.', 400);
        }
        $idBytes = null;
        if (!$isAccount) {
            $idBytes = ImageInput::bytes($request, 'image1') ?? ImageInput::bytes($request, 'id_image');
            if ($idBytes === null) {
                return GlobalHelper::apiError('invalid_image', 'Provide `valyd_access_token` (account) OR `image1` (reference) for a fresh face match.', 400);
            }
        }
        // Validate coordinates BEFORE any network call so a bad request 422s cheaply.
        $v = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'expected_latitude' => 'required|numeric',
            'expected_longitude' => 'required|numeric',
            'radius_m' => 'nullable|numeric',
        ]);

        // Resolve the stable pollus_id for account mode.
        if ($isAccount && empty($pollusId)) {
            $info = $this->idp->userinfo($accountToken);
            if (!($info['ok'] ?? false) || empty($info['pollus_id'])) {
                return GlobalHelper::apiError('valyd_login_required', 'valyd_access_token is invalid or expired.', 401);
            }
            $pollusId = $info['pollus_id'];
        }

        $result = $this->billing->runCharged($this->owner($request), 'evv_presence', function () use ($idBytes, $selfieBytes, $isAccount, $pollusId, $v) {
            if ($isAccount) {
                // Selfie vs the stored Valyd face vector (only the selfie leaves the integrator).
                $m = $this->idp->faceMatch($pollusId, base64_encode($selfieBytes));
                if (!($m['ok'] ?? false)) {
                    // Face service unreachable — not a mismatch. Return an error so billing refunds.
                    return GlobalHelper::apiError('face_match_unavailable', 'Could not match the face right now. Please try again.', 502);
                }
                $face = ($m['match'] ?? false)
                    ? CheckResult::passed(VerificationCheck::TYPE_FACE_MATCH, $m['score'] ?? null, ['similarity' => $m['score'] ?? null, 'source' => 'valyd_account'])
                    : CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Face does not match the Valyd account', $m['score'] ?? null, ['similarity' => $m['score'] ?? null, 'source' => 'valyd_account']);
            } else {
                $face = $this->faceMatchRunner->run($idBytes, $selfieBytes);
            }
            $loc = $this->locationMatchRunner->run(
                (float) $v['latitude'],
                (float) $v['longitude'],
                (float) $v['expected_latitude'],
                (float) $v['expected_longitude'],
                isset($v['radius_m']) ? (float) $v['radius_m'] : null,
                isset($v['accuracy']) ? (float) $v['accuracy'] : null,
            );
            $ok = $face->status === VerificationCheck::STATUS_PASSED && $loc->status === VerificationCheck::STATUS_PASSED;
            $data = [
                'face_match' => $face->toArray(),
                'location_match' => $loc->toArray(),
                'verified' => $ok,
            ];
            return $ok
                ? CheckResult::passed(VerificationCheck::TYPE_EVV_PRESENCE, $face->score, $data)
                : CheckResult::failed(VerificationCheck::TYPE_EVV_PRESENCE, 'presence_not_verified', $face->score, $data);
        });
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result; // face service unavailable (already refunded)
        }
        return $this->respond($request, 'evv_presence', $result);
    }

    public function credentialVerification(Request $request)
    {
        if ($err = $this->guardFeature($request, 'credential')) {
            return $err;
        }
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
        // If no provider_code was given, resolve it from state + license_type
        // (default "md"), so integrators only need state + a friendly type.
        if (empty($input['provider_code'])) {
            $state = $input['license_state'] ?? ($input['state'] ?? null);
            $resolved = $this->resolveProvider($state, $input['license_type'] ?? null);
            if (!empty($resolved['provider_code'])) {
                $input['provider_code'] = $resolved['provider_code'];
                // Use the resolved board-specific credential_code (e.g. co_medical_doctor)
                // in place of the friendly type the caller sent ("MD").
                if (!empty($resolved['credential_code'])) {
                    $input['license_type'] = $resolved['credential_code'];
                }
            }
        }
        $result = $this->billing->runCharged($this->owner($request), 'credential', fn () => $this->credentialRunner->run($input));
        return $this->respond($request, 'credential', $result);
    }

    /**
     * KYC + License (combined, synchronous): runs ID verification, liveness and a
     * 1:1 face match, then verifies a professional license AGAINST THE NAME read
     * from the ID document (never a client-supplied name). The selfie proves the
     * holder is present and matches the ID; the OCR'd name is what's checked at
     * the registry, so a passing result means the license belongs to the verified
     * person. Mirrors the hosted KYC+License workflow in one call.
     */
    public function kycCredential(Request $request)
    {
        foreach (['id_verification', 'credential'] as $feature) {
            if ($err = $this->guardFeature($request, $feature)) {
                return $err;
            }
        }

        $front = ImageInput::bytes($request, 'front_image');
        if ($front === null) {
            return GlobalHelper::apiError('invalid_image', 'A valid `front_image` (file or base64) is required.', 400);
        }
        $selfie = ImageInput::bytes($request, 'selfie');
        if ($selfie === null) {
            return GlobalHelper::apiError('invalid_image', 'A valid `selfie` (file or base64) is required.', 400);
        }
        $back = ImageInput::bytes($request, 'back_image');

        // Upfront guard: this combined call may run up to four checks. Make sure
        // the account can cover the lot before doing any (slow) work; each check
        // is then charged as it actually runs, and refunded on our-side errors.
        $owner = $this->owner($request);
        if ($owner) {
            $this->billing->assertCanAfford(
                $owner,
                $this->billing->costForFeatures(['id_verification', 'liveness', 'face_match', 'credential']),
            );
        }

        $license = $request->validate([
            'provider_code' => 'nullable|string',
            'license_type' => 'nullable|string',
            'license_state' => 'required_without:state|string',
            'state' => 'nullable|string',
            'license_number' => 'required_without:license_no|string',
            'license_no' => 'nullable|string',
            'npi' => 'nullable|string',
        ]);

        $features = ['id_verification', 'liveness', 'face_match', 'credential'];
        $session = $this->sessions->create(
            $this->project($request),
            null,
            $features,
            ['required_features' => $features, 'auto_approve' => true],
            [
                'mode' => VerificationSession::MODE_STANDALONE,
                'vendor_data' => $request->input('vendor_data'),
                'callback_url' => null,
                'ttl_seconds' => 600,
            ]
        );

        // 1) ID verification (OCR) — the source of the authoritative name.
        $frontPath = $this->tmpFile($front);
        $backPath = $back !== null ? $this->tmpFile($back) : null;
        $idResult = $this->billing->runCharged($owner, 'id_verification', function () use ($frontPath, $backPath) {
            try {
                return $this->idRunner->run($frontPath, $backPath);
            } finally {
                @unlink($frontPath);
                if ($backPath) {
                    @unlink($backPath);
                }
            }
        }, $session->id);
        $session = $this->sessions->recordCheck($session, $idResult);
        $checks = [$idResult->toArray()];

        $name = $idResult->data['fields']['full_name'] ?? null;
        $name = is_string($name) ? trim($name) : '';
        if ($idResult->status !== VerificationCheck::STATUS_PASSED || $name === '') {
            // No verified identity → cannot check a license against it.
            return $this->kycCredentialResponse($session, $checks, $name, $idResult);
        }

        // 2) Liveness + 3) 1:1 face match (selfie vs ID portrait).
        $liveness = $this->billing->runCharged($owner, 'liveness', fn () => $this->livenessRunner->run($selfie), $session->id);
        $session = $this->sessions->recordCheck($session, $liveness);
        $checks[] = $liveness->toArray();
        $face = $this->billing->runCharged($owner, 'face_match', fn () => $this->faceMatchRunner->run($front, $selfie), $session->id);
        $session = $this->sessions->recordCheck($session, $face);
        $checks[] = $face->toArray();

        // If the identity already failed (spoof / face mismatch), skip the slow
        // registry lookup — the session is already declined.
        if (!$session->isTerminal()) {
            unset($license['first_name'], $license['last_name']);
            $license['full_name'] = $name;
            // Auto-resolve the provider from state + type (default MD) when omitted.
            if (empty($license['provider_code'])) {
                $resolved = $this->resolveProvider($license['license_state'] ?? ($license['state'] ?? null), $license['license_type'] ?? null);
                if (!empty($resolved['provider_code'])) {
                    $license['provider_code'] = $resolved['provider_code'];
                    if (!empty($resolved['credential_code'])) {
                        $license['license_type'] = $resolved['credential_code'];
                    }
                }
            }
            $credential = $this->billing->runCharged($owner, 'credential', fn () => $this->credentialRunner->run($license), $session->id);
            $session = $this->sessions->recordCheck($session, $credential);
            $checks[] = $credential->toArray();
        }

        return $this->kycCredentialResponse($session, $checks, $name, $idResult);
    }

    private function kycCredentialResponse(VerificationSession $session, array $checks, string $name, $idResult)
    {
        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->refresh()->status,
            'checks' => $checks,
            'identity' => [
                'name' => $name !== '' ? $name : null,
                'dob' => $idResult->data['dob'] ?? null,
            ],
        ]);
    }

    // --- helpers -------------------------------------------------------------

    private function tmpFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vrf_');
        file_put_contents($path, $bytes);
        return $path;
    }

    /**
     * Record a standalone audit session for the single check and return the
     * result synchronously.
     */
    private function respond(Request $request, string $feature, CheckResult $result)
    {
        $project = $this->project($request);

        $session = $this->sessions->create(
            $project,
            null,
            [$feature],
            ['required_features' => [$feature], 'auto_approve' => true],
            [
                'mode' => VerificationSession::MODE_STANDALONE,
                'vendor_data' => $request->input('vendor_data'),
                'callback_url' => null, // standalone never emits a webhook
                'ttl_seconds' => 300,
            ]
        );

        $session = $this->sessions->recordCheck($session, $result);

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'check' => $result->toArray(),
        ]);
    }
}
