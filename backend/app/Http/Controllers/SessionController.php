<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\VerificationProject;
use App\Models\VerificationSession;
use App\Services\SessionService;
use Illuminate\Http\Request;

/**
 * Hosted verification sessions (Didit-style /v2/session/*).
 * Authenticated by the project API key.
 */
class SessionController extends Controller
{
    public function __construct(private SessionService $sessions)
    {
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
        ]);

        $workflow = $project->workflows()->where('is_active', true)->find($validated['workflow_id']);
        if (!$workflow) {
            return GlobalHelper::apiError('workflow_not_found', 'No active workflow found with that id for this project.', 404);
        }

        $session = $this->sessions->create(
            $project,
            $workflow,
            $workflow->features,
            $workflow->effectiveSettings(),
            [
                'mode' => VerificationSession::MODE_HOSTED,
                'vendor_data' => $validated['vendor_data'] ?? null,
                'callback_url' => $validated['callback'] ?? $project->webhook_url,
                'redirect_url' => $validated['redirect_url'] ?? null,
                'ttl_seconds' => $validated['ttl_seconds'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'url' => $this->sessions->hostedUrl($session),
            'session_token' => $session->session_token,
            'features' => $session->features,
            'redirect_url' => $session->redirect_url,
            'expires_at' => $session->expires_at->toIso8601String(),
        ], 201);
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

        return GlobalHelper::apiSuccess([
            'session_id' => $session->id,
            'status' => $session->status,
            'vendor_data' => $session->vendor_data,
            'decision' => $session->decision,
            'checks' => $checks,
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
