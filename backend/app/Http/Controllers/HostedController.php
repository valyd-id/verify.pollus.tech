<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\CredentialWatch;
use App\Models\VerificationCheck;
use App\Models\VerificationDocument;
use App\Models\VerificationSession;
use App\Services\Checks\AgeRunner;
use App\Services\Checks\CredentialRunner;
use App\Services\Checks\FaceMatchRunner;
use App\Services\Checks\IdVerificationRunner;
use App\Services\Checks\LivenessRunner;
use App\Services\Checks\CheckResult;
use App\Services\Checks\LocationRunner;
use App\Services\BillingService;
use App\Services\IdpClient;
use App\Services\ReusableIdentityService;
use App\Services\SessionService;
use App\Support\ImageInput;
use Carbon\Carbon;
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
        private \App\Services\Checks\LocationMatchRunner $locationMatchRunner,
        private \App\Services\IpGeoService $ipGeo,
        private BillingService $billing,
        private IdpClient $idp,
        private ReusableIdentityService $reusable,
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
            // Managed Identity by Valyd. The session is bound to a Valyd identity at
            // creation (the integrator passes the user's token), so `pollus_id` is
            // already set here — there is no in-popup login step.
            'reuse' => (bool) ($session->settings['reuse'] ?? false) || (($session->settings['product'] ?? null) === 'sso'),
            'pollus_id' => $session->pollus_id,
            // True when we already hold a verified record for this user in this app →
            // they can re-verify with a selfie only (no second ID scan).
            'reuse_eligible' => $session->pollus_id
                ? $this->reusable->findActive($session->project_id, $session->pollus_id) !== null
                : false,
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
            VerificationCheck::TYPE_LOCATION_MATCH => $this->runLocationMatch($session, $request),
            VerificationCheck::TYPE_EVV_PRESENCE => $this->runEvvPresence($session, $request),
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

    // --- Managed Identity by Valyd (returning-user reuse) -------------------

    /**
     * Returning-user reuse: match a selfie LOCALLY against the stored face embedding
     * in verify's own store. On a match we satisfy the workflow's identity features
     * from the stored record and reflect its verified licenses — no second ID scan.
     */
    public function reuseFace(Request $request)
    {
        $session = $this->session($request);
        if (empty($session->pollus_id)) {
            return GlobalHelper::apiError('not_linked', 'Sign in with Valyd before reusing your identity.', 400);
        }
        $rec = $this->reusable->findActive($session->project_id, $session->pollus_id);
        if (!$rec) {
            return GlobalHelper::apiError('no_reusable_record', 'No reusable verification found — please verify fully.', 400);
        }
        $selfie = $request->input('selfie');
        if (!is_string($selfie) || $selfie === '') {
            return GlobalHelper::apiError('invalid_image', 'A selfie is required.', 400);
        }

        $owner = $session->project?->owner;
        $match = $this->billing->runCharged($owner, 'face_match', fn () => $this->reusable->matchSelfie($rec, $selfie), $session->id);

        if (!$match['ok']) {
            return GlobalHelper::apiError('face_match_unavailable', 'Could not match your face right now. Please try again.', 502);
        }
        if (!$match['match']) {
            $session = $this->sessions->recordCheck(
                $session,
                CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Face did not match the stored identity', $match['score']),
            );
            return GlobalHelper::apiSuccess(['match' => false, 'session_status' => $session->status]);
        }

        // Match: trust the stored verified identity for the workflow's identity
        // features, and reflect its verified licenses for the credential feature.
        $session->update(['reused' => true]);
        $rec->update(['verified_at' => Carbon::now()]);
        $verifiedLicense = collect($rec->licenses ?? [])->first(function ($l) {
            return ($l['status'] ?? null) === 'verified';
        });

        foreach ($session->features as $f) {
            if (in_array($f, [VerificationCheck::TYPE_ID, VerificationCheck::TYPE_LIVENESS, VerificationCheck::TYPE_FACE_MATCH], true)) {
                $score = $f === VerificationCheck::TYPE_FACE_MATCH ? $match['score'] : null;
                $session = $this->sessions->recordCheck($session, CheckResult::passed($f, $score, ['source' => 'reuse', 'pollus_id' => $session->pollus_id]));
            } elseif ($f === VerificationCheck::TYPE_CREDENTIAL) {
                $session = $verifiedLicense
                    ? $this->sessions->recordCheck($session, CheckResult::passed(VerificationCheck::TYPE_CREDENTIAL, null, ['source' => 'reuse', 'license' => $verifiedLicense]))
                    : $this->sessions->recordCheck($session, CheckResult::review(VerificationCheck::TYPE_CREDENTIAL, ['source' => 'reuse', 'reason' => 'no_verified_license']));
            }
        }

        return GlobalHelper::apiSuccess(['match' => true, 'score' => $match['score'], 'session_status' => $session->refresh()->status]);
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
        $selfie = $this->docBytes($session, VerificationDocument::TYPE_SELFIE);
        if ($selfie === null) {
            return GlobalHelper::apiError('missing_document', 'A selfie is required for face-match.', 400);
        }
        $idBytes = $this->docBytes($session, VerificationDocument::TYPE_ID_FRONT);
        // ACCOUNT session with no ID document (KYC reused) → match the selfie against
        // the user's STORED Valyd face vector instead of a re-scanned ID.
        if ($idBytes === null && $session->pollus_id) {
            $m = $this->idp->faceMatch($session->pollus_id, base64_encode($selfie));
            if (!($m['ok'] ?? false)) {
                // Face service unreachable — not a mismatch. Return an error so billing
                // refunds and the user can retry (mirrors reuseFace).
                return GlobalHelper::apiError('face_match_unavailable', 'Could not match your face right now. Please try again.', 502);
            }
            return ($m['match'] ?? false)
                ? \App\Services\Checks\CheckResult::passed(VerificationCheck::TYPE_FACE_MATCH, $m['score'] ?? null, ['similarity' => $m['score'] ?? null, 'source' => 'valyd_account'])
                : \App\Services\Checks\CheckResult::failed(VerificationCheck::TYPE_FACE_MATCH, 'Face does not match the Valyd account', $m['score'] ?? null, ['similarity' => $m['score'] ?? null, 'source' => 'valyd_account']);
        }
        if ($idBytes === null) {
            return GlobalHelper::apiError('missing_document', 'A reference (id_front) or a Valyd account is required for face-match.', 400);
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

    /**
     * Hosted location MATCH: the device GPS is captured here; the EXPECTED point
     * is supplied by the integrator at session-create via metadata
     * (expected_lat/expected_lng, optional radius_m or expected_address later).
     */
    private function runLocationMatch(VerificationSession $session, Request $request)
    {
        $meta = $session->metadata ?? [];
        $expLat = $meta['expected_lat'] ?? $meta['expected_latitude'] ?? null;
        $expLon = $meta['expected_lng'] ?? $meta['expected_longitude'] ?? null;
        if ($expLat === null || $expLon === null) {
            return GlobalHelper::apiError('missing_expected_location', 'This session has no expected location. Set metadata.expected_lat/expected_lng at session create.', 400);
        }
        $v = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);
        $result = $this->locationMatchRunner->run(
            (float) $v['latitude'],
            (float) $v['longitude'],
            (float) $expLat,
            (float) $expLon,
            isset($meta['radius_m']) ? (float) $meta['radius_m'] : null,
            isset($v['accuracy']) ? (float) $v['accuracy'] : null,
        );
        // HOSTED-only: the end-user's browser hit us directly, so we see their real
        // IP (via the proxy's X-Forwarded-For). Coarse cross-check → soft `review`.
        return $this->applyIpCrossCheck($result, $request, (float) $v['latitude'], (float) $v['longitude']);
    }

    /** Attach a coarse IP↔GPS consistency signal (soft: downgrades to review, never fails). */
    private function applyIpCrossCheck($result, Request $request, float $gpsLat, float $gpsLon)
    {
        $xff = $request->header('X-Forwarded-For');
        $userIp = $xff ? trim(explode(',', $xff)[0]) : $request->ip();
        $ipLoc = $this->ipGeo->locate($userIp);

        if (!$ipLoc) {
            $result->data['ip_check'] = 'unavailable';
            return $result;
        }
        $km = \App\Services\IpGeoService::haversineKm($gpsLat, $gpsLon, $ipLoc['lat'], $ipLoc['lon']);
        $threshold = (float) config('verify.location_ip_mismatch_km', 200);
        $result->data['ip_check'] = $km > $threshold ? 'mismatch' : 'consistent';
        $result->data['ip_distance_km'] = round($km, 0);
        $result->data['ip_city'] = $ipLoc['city'];

        if ($km > $threshold && $result->status === \App\Models\VerificationCheck::STATUS_PASSED) {
            // Right geofence but the IP is a region away → likely spoofed GPS. Flag for review.
            $result->status = \App\Models\VerificationCheck::STATUS_REVIEW;
            $result->data['review_reason'] = 'ip_gps_mismatch';
        }
        return $result;
    }

    /**
     * Hosted EVV Presence: face match (id_front vs selfie captured in this flow)
     * + location match (captured GPS vs the session's expected location).
     */
    private function runEvvPresence(VerificationSession $session, Request $request)
    {
        $face = $this->runFaceMatch($session);
        if ($face instanceof \Illuminate\Http\JsonResponse) {
            return $face;
        }
        $loc = $this->runLocationMatch($session, $request);
        if ($loc instanceof \Illuminate\Http\JsonResponse) {
            return $loc;
        }
        $ok = $face->status === VerificationCheck::STATUS_PASSED && $loc->status === VerificationCheck::STATUS_PASSED;
        $data = ['face_match' => $face->toArray(), 'location_match' => $loc->toArray(), 'verified' => $ok];
        return $ok
            ? CheckResult::passed(VerificationCheck::TYPE_EVV_PRESENCE, $face->score, $data)
            : CheckResult::failed(VerificationCheck::TYPE_EVV_PRESENCE, 'presence_not_verified', $face->score, $data);
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

        $result = $this->credentialRunner->run($input);
        $this->maybeWatchCredential($session, $input, $result);
        return $result;
    }

    /**
     * If this workflow re-checks the license on a cadence (scheduled) or near
     * expiry, register/refresh a credential watch so the background job keeps the
     * license status fresh. `per_action` workflows are re-run live every time, so
     * they get no watch.
     */
    private function maybeWatchCredential(VerificationSession $session, array $input, CheckResult $result): void
    {
        $policy = $session->settings['recheck'] ?? null;
        if (!in_array($policy, [CredentialWatch::POLICY_SCHEDULED, CredentialWatch::POLICY_EXPIRY], true)) {
            return;
        }
        if ($result->status === VerificationCheck::STATUS_FAILED) {
            return; // only watch licenses we actually confirmed
        }

        $interval = $session->settings['recheck_interval'] ?? 'daily';
        $expireAt = $this->extractLicenseExpiry($result->data);
        $next = $policy === CredentialWatch::POLICY_SCHEDULED
            ? Carbon::now()->add($interval === 'weekly' ? '1 week' : '1 day')
            : ($expireAt ? $expireAt->copy()->subDays(3) : Carbon::now()->addMonth());

        CredentialWatch::updateOrCreate(
            [
                'project_id' => $session->project_id,
                'session_id' => $session->id,
                'license_number' => $input['license_number'] ?? ($input['license_no'] ?? null),
                'license_state' => $input['license_state'] ?? ($input['state'] ?? null),
            ],
            [
                'pollus_id' => $session->pollus_id,
                'credential' => $input,
                'license_type' => $input['license_type'] ?? ($input['provider_code'] ?? null),
                'policy' => $policy,
                'interval' => $interval,
                'last_status' => $result->status,
                'expire_at' => $expireAt,
                'last_checked_at' => Carbon::now(),
                'next_recheck_at' => $next,
                'is_active' => true,
            ],
        );
    }

    /** Best-effort: pull a license expiry date out of the credential result data. */
    private function extractLicenseExpiry(array $data): ?Carbon
    {
        $found = null;
        array_walk_recursive($data, function ($v, $k) use (&$found) {
            if ($found || !is_string($k) || !is_scalar($v)) {
                return;
            }
            if (stripos((string) $k, 'expir') !== false) {
                try {
                    $found = Carbon::parse((string) $v);
                } catch (\Throwable $e) {
                    // ignore unparseable
                }
            }
        });
        return $found;
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
