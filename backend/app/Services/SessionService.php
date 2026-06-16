<?php

namespace App\Services;

use App\Jobs\SendVerificationWebhookJob;
use App\Models\VerificationProject;
use App\Models\VerificationSession;
use App\Models\VerificationWorkflow;
use App\Services\Checks\CheckResult;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SessionService
{
    public function __construct(private DecisionService $decisionService)
    {
    }

    /**
     * Create a hosted (or standalone audit) session from a workflow.
     */
    public function create(
        VerificationProject $project,
        ?VerificationWorkflow $workflow,
        array $features,
        array $settings,
        array $opts = []
    ): VerificationSession {
        $ttl = (int) ($opts['ttl_seconds'] ?? config('verify.default_session_ttl', 1800));
        $ttl = max((int) config('verify.min_session_ttl', 60), min((int) config('verify.max_session_ttl', 86400), $ttl));

        return VerificationSession::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'workflow_id' => $workflow?->id,
            'status' => VerificationSession::STATUS_NOT_STARTED,
            'mode' => $opts['mode'] ?? VerificationSession::MODE_HOSTED,
            'vendor_data' => $opts['vendor_data'] ?? null,
            'callback_url' => $opts['callback_url'] ?? $project->webhook_url,
            'redirect_url' => $opts['redirect_url'] ?? null,
            'session_token' => 'vst_' . Str::random(48),
            'features' => $features,
            'settings' => $settings,
            'metadata' => $opts['metadata'] ?? [],
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);
    }

    public function hostedUrl(VerificationSession $session): string
    {
        return rtrim((string) config('verify.hosted_base_url'), '/') . '/verify?session=' . $session->session_token;
    }

    /**
     * Persist a single check result, mark the session in progress, then
     * re-evaluate the decision and finalize + webhook if it became terminal.
     */
    public function recordCheck(VerificationSession $session, CheckResult $result): VerificationSession
    {
        $session->checks()->updateOrCreate(
            ['type' => $result->type],
            [
                'status' => $result->status,
                'score' => $result->score,
                'data' => $result->data,
                'error' => $result->error,
            ]
        );

        if ($session->status === VerificationSession::STATUS_NOT_STARTED) {
            $session->update(['status' => VerificationSession::STATUS_IN_PROGRESS]);
        }

        return $this->reevaluate($session);
    }

    /**
     * Recompute the session decision; finalize (and emit a webhook) if terminal.
     */
    public function reevaluate(VerificationSession $session): VerificationSession
    {
        if ($session->isTerminal()) {
            return $session;
        }

        $outcome = $this->decisionService->evaluate($session);

        if ($outcome['complete']) {
            return $this->finalize($session, $outcome['status'], $outcome['summary']);
        }

        $session->update(['status' => $outcome['status']]);
        return $session->refresh();
    }

    public function finalize(VerificationSession $session, string $status, array $summary = []): VerificationSession
    {
        if ($session->isTerminal()) {
            return $session;
        }

        $session->update([
            'status' => $status,
            'decided_at' => Carbon::now(),
            'decision' => [
                'status' => $status,
                'features' => $summary,
                'decided_at' => Carbon::now()->toIso8601String(),
            ],
        ]);

        $this->dispatchWebhook($session->refresh());
        return $session;
    }

    /** Per-feature summary for the current checks (used by manual overrides). */
    public function evaluateSummary(VerificationSession $session): array
    {
        return $this->decisionService->evaluate($session)['summary'];
    }

    public function decline(VerificationSession $session): VerificationSession
    {
        return $this->finalize($session, VerificationSession::STATUS_DECLINED, $this->decisionService->evaluate($session)['summary']);
    }

    public function expire(VerificationSession $session): VerificationSession
    {
        if ($session->isTerminal()) {
            return $session;
        }
        return $this->finalize($session, VerificationSession::STATUS_EXPIRED);
    }

    public function dispatchWebhook(VerificationSession $session): void
    {
        if (empty($session->callback_url)) {
            return;
        }
        SendVerificationWebhookJob::dispatch($session->id)
            ->onQueue(config('verify.webhook_queue', 'default'));
    }
}
