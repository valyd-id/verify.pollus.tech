<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\VerificationProject;
use App\Models\VerificationSession;
use App\Models\VerificationWorkflow;
use App\Services\SessionService;
use Illuminate\Support\Str;

/**
 * Public, no-auth demo (like demos.didit.me). Creates sessions server-side
 * against a dedicated "Public Demo" project so no API key is exposed to the
 * browser. Rate-limited at the route level.
 */
class DemoController extends Controller
{
    public const PROJECT_NAME = 'Public Demo';
    public const WORKFLOW_NAME = 'Demo KYC';
    public const FEATURES = ['id_verification', 'liveness', 'face_match'];

    public function __construct(private SessionService $sessions)
    {
    }

    /** Lazily ensure the demo project + workflow exist; returns both. */
    private function ensureDemo(): array
    {
        $project = VerificationProject::firstOrCreate(
            ['name' => self::PROJECT_NAME],
            [
                'api_key_hash' => VerificationProject::hashKey('demo_' . Str::random(40)),
                'api_key_prefix' => 'demo',
                'webhook_url' => null,
                'webhook_signing_secret' => 'whsec_' . Str::random(40),
                'is_active' => true,
            ]
        );

        $workflow = VerificationWorkflow::firstOrCreate(
            ['project_id' => $project->id, 'name' => self::WORKFLOW_NAME],
            [
                'id' => (string) Str::uuid(),
                'features' => self::FEATURES,
                'settings' => ['auto_approve' => true],
                'is_active' => true,
            ]
        );

        return [$project, $workflow];
    }

    /** Start a new demo verification session. Optional `features[]` chooses the flow. */
    public function start(\Illuminate\Http\Request $request)
    {
        [$project, $workflow] = $this->ensureDemo();

        // Allow the demo UI to request a specific subset of features (per card).
        $requested = (array) $request->input('features', []);
        $allowed = config('verify.features');
        $features = array_values(array_filter($requested, fn ($f) => in_array($f, $allowed, true)));
        if (empty($features)) {
            $features = $workflow->features; // default = Demo KYC
        }

        $session = $this->sessions->create(
            $project,
            $workflow,
            $features,
            ['required_features' => $features, 'auto_approve' => true],
            [
                'mode' => VerificationSession::MODE_HOSTED,
                'vendor_data' => 'demo-' . Str::random(8),
                'callback_url' => null, // demo: no real webhook
                'ttl_seconds' => 1800,
            ]
        );

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'session_token' => $session->session_token,
            'features' => $session->features,
        ]);
    }

    /**
     * Read the final (or current) state of a demo session — works even after
     * the session is terminal (unlike the token-scoped hosted endpoints).
     */
    public function status(string $id)
    {
        [$project] = $this->ensureDemo();

        $session = VerificationSession::where('project_id', $project->id)->find($id);
        if (!$session) {
            return GlobalHelper::apiError('not_found', 'Demo session not found.', 404);
        }

        $checks = $session->checks()->get()->map(fn ($c) => [
            'type' => $c->type,
            'status' => $c->status,
            'score' => $c->score,
            'error' => $c->error,
        ]);

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'features' => $session->features,
            'decision' => $session->decision,
            'checks' => $checks,
            'expires_at' => $session->expires_at?->toIso8601String(),
        ]);
    }
}
