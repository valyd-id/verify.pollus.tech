<?php

use App\Console\Commands\ExpireStaleSessions;
use Illuminate\Support\Facades\Schedule;

// Expire sessions whose expires_at has passed; emits webhooks for them.
Schedule::command(ExpireStaleSessions::class)->everyMinute()->withoutOverlapping();
