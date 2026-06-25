<?php

namespace App\Jobs;

use App\Models\CredentialWatch;
use App\Models\VerificationSession;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifies the integrator when a watched license's status changes on re-check
 * (e.g. lapsed/suspended/expired). Signed identically to verification webhooks:
 * HMAC-SHA256(timestamp + "." + rawBody, project.webhook_signing_secret).
 */
class SendCredentialChangedWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public array $backoff = [5, 30, 120, 600, 1800];

    public function __construct(
        public int $watchId,
        public ?string $oldStatus,
        public string $newStatus,
        public array $license = [],
        public ?int $endpointId = null,
    ) {
    }

    public function handle(): void
    {
        $watch = CredentialWatch::with('project')->find($this->watchId);
        if (!$watch) {
            return;
        }
        $project = $watch->project;
        $session = $watch->session_id ? VerificationSession::find($watch->session_id) : null;

        if ($this->endpointId !== null) {
            $endpoint = WebhookEndpoint::find($this->endpointId);
            if (!$endpoint || !$endpoint->is_active || !$endpoint->wantsEvent('verification.credential_changed')) {
                return;
            }
            $callbackUrl = $endpoint->url;
            $secret = $endpoint->signing_secret;
        } else {
            $callbackUrl = $session?->callback_url ?: $project?->webhook_url;
            $secret = $project?->webhook_signing_secret;
        }

        if (empty($callbackUrl) || empty($secret)) {
            Log::info('SendCredentialChangedWebhookJob no callback/secret, skipping', ['watch_id' => $this->watchId]);
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'type' => 'verification.credential_changed',
            'session_id' => $watch->session_id,
            'pollus_id' => $watch->pollus_id,
            'vendor_data' => $session?->vendor_data,
            'credential' => [
                'license_type' => $watch->license_type,
                'license_state' => $watch->license_state,
                'license_number' => $watch->license_number,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'expire_at' => optional($watch->expire_at)->toIso8601String(),
                'license' => $this->license,
            ],
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
        ])->withBody($rawBody, 'application/json')->timeout(30)->post($callbackUrl);

        if ($response->successful()) {
            return;
        }
        $this->release($this->backoff[$this->attempts() - 1] ?? 1800);
    }
}
