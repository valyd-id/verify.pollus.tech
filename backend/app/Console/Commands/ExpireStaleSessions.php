<?php

namespace App\Console\Commands;

use App\Models\VerificationSession;
use App\Services\SessionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireStaleSessions extends Command
{
    protected $signature = 'verify:expire-stale';

    protected $description = 'Expire non-terminal verification sessions past their expires_at and emit webhooks.';

    public function handle(SessionService $sessions): int
    {
        $stale = VerificationSession::whereNotIn('status', VerificationSession::TERMINAL_STATUSES)
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($stale as $session) {
            $sessions->expire($session);
        }

        $this->info("Expired {$stale->count()} stale session(s).");
        return self::SUCCESS;
    }
}
