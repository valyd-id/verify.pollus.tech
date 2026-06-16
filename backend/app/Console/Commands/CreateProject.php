<?php

namespace App\Console\Commands;

use App\Models\VerificationProject;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateProject extends Command
{
    protected $signature = 'verify:create-project
        {name : Display name for the project}
        {--webhook= : Default webhook callback URL}
        {--features=* : Restrict to these feature keys (default: all)}';

    protected $description = 'Provision a verification project and print its API key + webhook signing secret (shown once).';

    public function handle(): int
    {
        $signingSecret = 'whsec_' . Str::random(40);

        [$project, $rawKey] = VerificationProject::provision($this->argument('name'), [
            'webhook_url' => $this->option('webhook') ?: null,
            'webhook_signing_secret' => $signingSecret,
            'allowed_features' => $this->option('features') ?: null,
        ]);

        $this->info('Project created.');
        $this->table(['Field', 'Value'], [
            ['id', $project->id],
            ['name', $project->name],
            ['api_key', $rawKey],
            ['webhook_signing_secret', $signingSecret],
            ['webhook_url', $project->webhook_url ?? '(none)'],
        ]);
        $this->warn('Store the api_key and webhook_signing_secret now — they will not be shown again.');

        return self::SUCCESS;
    }
}
