<?php

use App\Console\Commands\ExpireStaleSessions;
use App\Console\Commands\RecheckCredentials;
use Illuminate\Support\Facades\Schedule;

// Expire sessions whose expires_at has passed; emits webhooks for them.
Schedule::command(ExpireStaleSessions::class)->everyMinute()->withoutOverlapping();

// Re-verify watched licenses on their cadence / near expiry (next_recheck_at gates work).
Schedule::command(RecheckCredentials::class)->everyFiveMinutes()->withoutOverlapping();
