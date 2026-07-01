<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\VerificationProject;
use App\Models\VerificationSession;
use App\Services\BillingService;
use App\Services\IdpClient;
use App\Services\ReusableIdentityService;
use App\Services\Checks\CheckResult;
use App\Services\SessionService;
use Illuminate\Http\Request;

/**
 * Hosted verification sessions (Didit-style /v2/session/*).
 * Authenticated by the project API key.
 */
class SessionController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private BillingService $billing,
        private ReusableIdentityService $reusable,
        private IdpClient $idp,
    ) {
    }

    private function project(Request $request): VerificationProject
    {
        return $request->attributes->get('verification_project');
    }

    public function store(Request $request)
    {
        $project = $this->project($request);

        $validated = $request->validate([
            'workflow_id' => 'required|uuid',
            'vendor_data' => 'nullable|string|max:255',
            'callback' => 'nullable|url',
            'redirect_url' => 'nullable|url',
            'ttl_seconds' => 'nullable|integer',
            'metadata' => 'nullable|array',
            // "Managed Identity by Valyd": the integrator logs the user in with Valyd
            // on their own site (TPSSO) and passes the resulting access token here so
            // we can bind the session to that Valyd identity. Never sent to the browser.
            'valyd_access_token' => 'nullable|string',
            // Optional pre-resolved identity (trusted, API-key-authenticated caller).
            'pollus_id' => 'nullable|string|max:255',
        ]);

        $workflow = $project->workflows()->where('is_active', true)->find($validated['workflow_id']);
        if (!$workflow) {
            return GlobalHelper::apiError('workflow_not_found', 'No active workflow found with that id for this project.', 404);
        }

        // Managed workflows ("Login with Valyd" / reuse) require a logged-in Valyd
        // user. Validate the integrator-supplied access token against the IdP and
        // bind the resulting pollus_id; the token's validity is the proof of login.
        $settings = $workflow->effectiveSettings();
        $requiresValyd = ($settings['product'] ?? null) === 'sso' || (bool) ($settings['reuse'] ?? false);
        $pollusId = null;
        if (!empty($validated['valyd_access_token'])) {
            $info = $this->idp->userinfo($validated['valyd_access_token']);
            if (!$info['ok']) {
                return GlobalHelper::apiError('valyd_login_required', $info['error']['message'] ?? 'Valyd login is required.', 401);
            }
            $pollusId = $info['pollus_id'];
        } elseif (!empty($validated['pollus_id'])) {
            $pollusId = $validated['pollus_id'];
        }
        if ($requiresValyd && !$pollusId) {
            return GlobalHelper::apiError(
                'valyd_login_required',
                'This workflow requires a logged-in Valyd user. Log the user in with Valyd and pass their valyd_access_token.',
                401,
            );
        }

        // Upfront guard: don't start a hosted flow the account can't pay for. Each
        // check is charged as the end-user completes it; this just blocks early
        // (402) if the balance can't cover the whole workflow. Throws → 402.
        if ($owner = $project->owner) {
            $this->billing->assertCanAfford($owner, $this->billing->costForFeatures($workflow->features));
        }

        $session = $this->sessions->create(
            $project,
            $workflow,
            $workflow->features,
            $settings,
            [
                'mode' => VerificationSession::MODE_HOSTED,
                'vendor_data' => $validated['vendor_data'] ?? null,
                'callback_url' => $validated['callback'] ?? $project->webhook_url,
                'redirect_url' => $validated['redirect_url'] ?? null,
                'ttl_seconds' => $validated['ttl_seconds'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
                'pollus_id' => $pollusId,
            ]
        );

        // Managed Identity reuse: pre-satisfy steps we ALREADY have for this Valyd
        // user so a returning, verified user isn't asked to redo them. KYC (id +
        // liveness) is satisfied by the account's human_verified flag; the license
        // (credential) by a stored/verified license. Face match is NEVER prefilled —
        // the returning user re-confirms with a live selfie. Location is per-visit.
        if ($pollusId && (bool) ($settings['reuse'] ?? false)) {
            $this->prefillManagedIdentity($session, $project, $pollusId, $validated['valyd_access_token'] ?? null);
            $session->refresh();
        }

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'url' => $this->sessions->hostedUrl($session),
            'session_token' => $session->session_token,
            'features' => $session->features,
            'reused_features' => $session->checks()->where('status', 'passed')->pluck('type'),
            'redirect_url' => $session->redirect_url,
            'expires_at' => $session->expires_at->toIso8601String(),
        ], 201);
    }

    /** Mark already-verified steps as passed (reused) for a returning Valyd user. */
    private function prefillManagedIdentity(VerificationSession $session, $project, string $pollusId, ?string $token): void
    {
        $features = $session->features;
        $ver = $token ? $this->idp->verifications($token) : ['human_verified' => false, 'id_verified' => false, 'licenses' => []];
        $rec = $this->reusable->findActive($project->id, $pollusId);
        $kyc = ($ver['human_verified'] ?? false) || ($ver['id_verified'] ?? false) || $rec !== null;
        // Only reuse licenses that are actually verified/active — never an expired or
        // pending one (a returning user must re-verify a lapsed license).
        $rawLicenses = $ver['licenses'] ?: ($rec?->licenses ?? []);
        $licenses = array_values(array_filter($rawLicenses, function ($l) {
            $status = strtolower((string) ($l['status'] ?? ''));
            return ($l['verified'] ?? false) === true || in_array($status, ['verified', 'active', 'valid', 'current'], true);
        }));

        $mark = function (string $type, array $data) use ($session) {
            $this->sessions->recordCheck($session, CheckResult::passed($type, null, array_merge(['reused' => true], $data)));
        };

        // KYC: id_verification + liveness satisfied by the account being human-verified.
        if ($kyc) {
            foreach (['id_verification', 'liveness'] as $f) {
                if (in_array($f, $features, true)) {
                    $mark($f, ['source' => 'valyd_account', 'human_verified' => true]);
                }
            }
        }
        // License: satisfied when a verified license is already stored on the account.
        if (in_array('credential', $features, true) && !empty($licenses)) {
            $mark('credential', ['source' => 'valyd_account', 'licenses' => array_values($licenses)]);
        }
        // Age bands, if stored.
        if (in_array('age', $features, true) && $rec && !empty($rec->age_bands)) {
            $mark('age', ['source' => 'valyd_account', 'age_bands' => $rec->age_bands]);
        }
        // face_match, location, location_match, evv_presence are intentionally left pending.
    }

    public function index(Request $request)
    {
        $query = $this->project($request)->sessions()->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($vendor = $request->query('vendor_data')) {
            $query->where('vendor_data', $vendor);
        }

        $sessions = $query->limit((int) $request->query('limit', 50))->get()
            ->map(fn ($s) => $this->summary($s));

        return GlobalHelper::apiSuccess(['sessions' => $sessions]);
    }

    public function show(Request $request, string $id)
    {
        $session = $this->find($request, $id);
        if (!$session) {
            return GlobalHelper::apiError('not_found', 'Session not found.', 404);
        }
        return GlobalHelper::apiSuccess($this->summary($session));
    }

    public function decision(Request $request, string $id)
    {
        $session = $this->find($request, $id);
        if (!$session) {
            return GlobalHelper::apiError('not_found', 'Session not found.', 404);
        }

        $checks = $session->checks()->get()->map(fn ($c) => [
            'type' => $c->type,
            'status' => $c->status,
            'score' => $c->score,
            'data' => $c->data,
            'error' => $c->error,
        ]);

        // For Valyd-linked sessions, surface the verified profile + licenses the
        // integrator is entitled to (from verify's own reusable-identity store).
        $identity = null;
        if (!empty($session->pollus_id)) {
            $rec = $this->reusable->find($session->project_id, $session->pollus_id);
            if ($rec) {
                $identity = $this->reusable->present($rec);
            }
        }

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'vendor_data' => $session->vendor_data,
            'pollus_id' => $session->pollus_id,
            'decision' => $session->decision,
            'checks' => $checks,
            'identity' => $identity,
            'decided_at' => $session->decided_at?->toIso8601String(),
        ]);
    }

    /**
     * Manual review override: force APPROVED or DECLINED on an IN_REVIEW session.
     */
    public function updateStatus(Request $request, string $id)
    {
        $session = $this->find($request, $id);
        if (!$session) {
            return GlobalHelper::apiError('not_found', 'Session not found.', 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:APPROVED,DECLINED',
        ]);

        // Manual override is only valid before the session reaches a terminal
        // state. IN_REVIEW / IN_PROGRESS / NOT_STARTED are all still open.
        if ($session->isTerminal()) {
            return GlobalHelper::apiError('already_decided', 'Session is already in a terminal state.', 409, [
                'status' => $session->status,
            ]);
        }

        $summary = $this->sessions->evaluateSummary($session);
        $this->sessions->finalize($session, $validated['status'], $summary);

        return GlobalHelper::apiSuccess($this->summary($session->refresh()));
    }

    private function find(Request $request, string $id): ?VerificationSession
    {
        return $this->project($request)->sessions()->find($id);
    }

    private function summary(VerificationSession $session): array
    {
        return [
            'session_id' => $session->id,
            'workflow_id' => $session->workflow_id,
            'status' => $session->status,
            'mode' => $session->mode,
            'vendor_data' => $session->vendor_data,
            'features' => $session->features,
            'decision' => $session->decision,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'decided_at' => $session->decided_at?->toIso8601String(),
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }
}
