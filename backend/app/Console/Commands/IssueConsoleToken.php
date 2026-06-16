<?php

namespace App\Console\Commands;

use App\Services\ConsoleProvisioner;
use App\Support\ConsoleToken;
use Illuminate\Console\Command;

/**
 * CLI-only: provision/find a console user and print a bearer token.
 * Used for local testing of the console before Valyd SSO is configured.
 * This is NOT exposed over HTTP.
 */
class IssueConsoleToken extends Command
{
    protected $signature = 'console:issue-token {email} {--name=}';

    protected $description = 'Provision a console user (auto-creates their first app) and print a bearer token.';

    public function handle(ConsoleProvisioner $provisioner): int
    {
        $email = $this->argument('email');
        $user = $provisioner->fromSso(null, $email, $this->option('name') ?: null);
        $token = ConsoleToken::issue($user->id);

        $this->info("Console user #{$user->id} <{$user->email}>");
        $this->line('Bearer token (7-day):');
        $this->line($token);
        return self::SUCCESS;
    }
}
