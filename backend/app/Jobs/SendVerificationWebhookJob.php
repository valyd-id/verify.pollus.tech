<?php

namespace App\Jobs;

use App\Models\VerificationSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Delivers a signed verification result to the project's callback_url.
 * Signature: HMAC-SHA256(timestamp + "." + rawBody, project.webhook_signing_secret),
 * sent in X-Valyd-Signature (with X-Valyd-Timestamp / X-Valyd-Event-Id).
 */
class SendVerificationWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public array $backoff = [5, 30, 120, 600, 1800];

    public function __construct(public string $sessionId)
    {
    }

    public function handle(): void
    {
        $session = VerificationSession::with('project')->find($this->sessionId);
        if (!$session) {
            Log::warning('SendVerificationWebhookJob session not found', ['session_id' => $this->sessionId]);
            return;
        }
        if (empty($session->callback_url)) {
            Log::info('SendVerificationWebhookJob no callback_url, skipping', ['session_id' => $this->sessionId]);
            return;
        }

        $secret = $session->project?->webhook_signing_secret;
        if (empty($secret)) {
            Log::warning('SendVerificationWebhookJob no signing secret on project', ['session_id' => $this->sessionId]);
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'type' => 'verification.' . strtolower($session->status),
            'session_id' => $session->id,
            'status' => $session->status,
            'vendor_data' => $session->vendor_data,
            'decision' => $session->decision,
            'occurred_at' => now()->toIso8601String(),
        ];

        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Valyd-Timestamp' => $timestamp,
            'X-Valyd-Event-Id' => $eventId,
            'X-Valyd-Signature' => $signature,
        ])->withBody($rawBody, 'application/json')->timeout(30)->post($session->callback_url);

        if ($response->successful()) {
            Log::info('SendVerificationWebhookJob delivered', ['session_id' => $session->id, 'status' => $response->status()]);
            return;
        }

        Log::warning('SendVerificationWebhookJob failed, will retry', [
            'session_id' => $session->id,
            'http_status' => $response->status(),
            'attempt' => $this->attempts(),
        ]);
        $this->release($this->backoff[$this->attempts() - 1] ?? 1800);
    }
}
