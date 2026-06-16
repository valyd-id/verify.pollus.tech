<?php

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Worker counts (resolved from .env at config-load time)
|--------------------------------------------------------------------------
|
| IMPORTANT: do NOT call config('verification.*') from inside this file.
| Laravel loads config files alphabetically, so horizon.php is processed
| BEFORE verification.php — meaning config('verification.*') would always
| return the hardcoded defaults and silently ignore .env overrides.
|
| Read directly from env() here instead, matching the same defaults used in
| config/verification.php so behavior stays consistent if .env is missing.
*/
$defaultWorkers      = max(1, (int) env('VCS_DEFAULT_WORKERS', 3));
$verificationWorkers = max(1, (int) env('VCS_VERIFICATION_WORKERS', 6));
$retryWorkers        = max(1, (int) env('VCS_RETRY_WORKERS', 3));

return [

    'name' => env('HORIZON_NAME', env('APP_NAME', 'VCS').' Horizon'),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds (seconds)
    |--------------------------------------------------------------------------
    */
    'waits' => [
        'redis:default' => 120,
        'redis:provider-sync' => 300,
        'redis:provider-retry' => 600,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],
    'silenced_tags' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue priority (left = higher priority) — target: 5 + 15 + 5 processes
    |--------------------------------------------------------------------------
    |
    | Keep balance => false in production (see environments) so totals stay
    | exactly config('verification.workers.*'). balance => auto scales pools
    | and inflates process counts (e.g. 42 instead of 25).
    |
    | - default-supervisor (5): default → provider-sync → provider-retry.
    | - provider-sync-supervisor (15): provider-sync → provider-retry.
    | - provider-retry-supervisor (5): provider-retry → provider-sync (when
    |   retry is idle, these workers help sync backlog).
    |
    | VerifyLicenseJob uses the same timeout/memory on all pools.
    |
    */
    'defaults' => [
        'default-supervisor' => [
            'connection' => 'redis',
            'queue' => ['default', 'provider-sync', 'provider-retry'],
            'balance' => false,
            'minProcesses' => $defaultWorkers,
            'maxProcesses' => $defaultWorkers,
            'maxTime' => 3600,
            'maxJobs' => 500,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 420,
            'nice' => 0,
        ],

        'provider-sync-supervisor' => [
            'connection' => 'redis',
            'queue' => ['provider-sync', 'provider-retry'],
            'balance' => false,
            'minProcesses' => $verificationWorkers,
            'maxProcesses' => $verificationWorkers,
            'maxTime' => 3600,
            'maxJobs' => 200,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 420,
            'nice' => 5,
        ],

        'provider-retry-supervisor' => [
            'connection' => 'redis',
            'queue' => ['provider-retry', 'provider-sync'],
            'balance' => false,
            'minProcesses' => $retryWorkers,
            'maxProcesses' => $retryWorkers,
            'maxTime' => 3600,
            'maxJobs' => 200,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 420,
            'nice' => 10,
        ],
    ],

    'environments' => [
        /*
         * Fixed process counts: use balance => false (inherits from defaults above).
         * Do not use balance => auto here — Horizon will scale supervisors and
         * break the intended 5 + 15 + 5 = 25 total.
         */
        'production' => [],

        'local' => [
            'default-supervisor' => [
                'minProcesses' => $defaultWorkers,
                'maxProcesses' => $defaultWorkers,
            ],
            'provider-sync-supervisor' => [
                'minProcesses' => $verificationWorkers,
                'maxProcesses' => $verificationWorkers,
            ],
            'provider-retry-supervisor' => [
                'minProcesses' => $retryWorkers,
                'maxProcesses' => $retryWorkers,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
