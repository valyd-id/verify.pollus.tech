<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\SendCredentialChangedWebhookJob;
use App\Models\CredentialWatch;
use App\Models\VerificationCheck;
use App\Services\BillingService;
use App\Services\Checks\CredentialRunner;
use App\Services\IdpClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Re-verifies watched professional licenses on their cadence (scheduled) or as
 * they near expiry, so a lapse/suspension is caught between renewals. On a status
 * change it updates the IdP license record and fires a credential_changed webhook.
 */
class RecheckCredentials extends Command
{
    protected $signature = 'verify:recheck-credentials {--limit=200}';

    protected $description = 'Re-run due credential watches and notify on any status change.';

    public function handle(CredentialRunner $runner, BillingService $billing, IdpClient $idp): int
    {
        $now = Carbon::now();
        $due = CredentialWatch::where('is_active', true)
            ->whereNotNull('next_recheck_at')
            ->where('next_recheck_at', '<=', $now)
            ->limit((int) $this->option('limit'))
            ->get();

        $changed = 0;
        foreach ($due as $watch) {
            if ($this->recheck($watch, $runner, $billing, $idp)) {
                $changed++;
            }
        }

        $this->info("Re-checked {$due->count()} license(s); {$changed} changed.");
        return self::SUCCESS;
    }

    private function recheck(CredentialWatch $watch, CredentialRunner $runner, BillingService $billing, IdpClient $idp): bool
    {
        $owner = $watch->project?->owner;

        try {
            $result = $billing->runCharged($owner, 'credential', fn () => $runner->run($watch->credential ?? []), $watch->session_id);
        } catch (InsufficientBalanceException $e) {
            // Can't pay for the re-check right now — try again tomorrow, don't drop it.
            $watch->update(['next_recheck_at' => Carbon::now()->addDay()]);
            return false;
        }

        $old = $watch->last_status;
        $new = $result->status;
        $expireAt = $this->extractLicenseExpiry($result->data) ?? $watch->expire_at;

        $next = $watch->policy === CredentialWatch::POLICY_SCHEDULED
            ? Carbon::now()->add($watch->interval === 'weekly' ? '1 week' : '1 day')
            : ($expireAt ? $expireAt->copy()->subDays(3) : Carbon::now()->addMonth());

        $watch->update([
            'last_status' => $new,
            'last_checked_at' => Carbon::now(),
            'next_recheck_at' => $next,
            'expire_at' => $expireAt,
        ]);

        if ($new === $old) {
            return false;
        }

        // Status changed → update the IdP record (system of record) + notify the integrator.
        if ($watch->pollus_id) {
            $idp->recordLicense($watch->pollus_id, [
                'license_type' => $watch->license_type ?: 'license',
                'status' => $new === VerificationCheck::STATUS_PASSED ? 'verified'
                    : ($new === VerificationCheck::STATUS_FAILED ? 'failed' : 'pending'),
                'external_ref' => $watch->session_id,
                'expire_at' => optional($expireAt)->toDateString(),
            ]);
        }

        $queue = config('verify.webhook_queue', 'default');
        $data = is_array($result->data) ? $result->data : [];
        // Legacy single webhook + fan out to every active endpoint.
        SendCredentialChangedWebhookJob::dispatch($watch->id, $old, (string) $new, $data)->onQueue($queue);
        $endpoints = $watch->project ? $watch->project->webhookEndpoints()->where('is_active', true)->get() : collect();
        foreach ($endpoints as $endpoint) {
            SendCredentialChangedWebhookJob::dispatch($watch->id, $old, (string) $new, $data, $endpoint->id)->onQueue($queue);
        }

        return true;
    }

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
                    // ignore
                }
            }
        });
        return $found;
    }
}
