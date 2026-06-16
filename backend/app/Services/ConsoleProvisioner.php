<?php

namespace App\Services;

use App\Models\ConsoleUser;
use App\Models\VerificationProject;

class ConsoleProvisioner
{
    /**
     * Find or create a console user from SSO claims, auto-creating their first
     * App (project) on initial signup.
     */
    public function fromSso(?string $valydUserId, ?string $email, ?string $name): ConsoleUser
    {
        $user = null;
        if ($valydUserId) {
            $user = ConsoleUser::where('valyd_user_id', $valydUserId)->first();
        }
        if (!$user && $email) {
            $user = ConsoleUser::where('email', $email)->first();
        }

        if (!$user) {
            $user = ConsoleUser::create([
                'valyd_user_id' => $valydUserId,
                'email' => $email,
                'name' => $name,
            ]);
            // Auto-create the first app for a brand-new user.
            $this->createApp($user, 'Default app');
        } else {
            $user->fill(array_filter([
                'valyd_user_id' => $valydUserId ?: $user->valyd_user_id,
                'name' => $name ?: $user->name,
                // Backfill an email only if we have one and the user still lacks it.
                'email' => $user->email ?: $email,
            ]))->save();
        }

        return $user;
    }

    /** Create a new app (project) owned by the user. Returns [project, rawApiKey]. */
    public function createApp(ConsoleUser $user, string $name, array $attributes = []): array
    {
        // The user's very first app becomes their (undeletable) default.
        $isFirst = $user->projects()->count() === 0;

        return VerificationProject::provision($name, array_merge([
            'user_id' => $user->id,
            'webhook_signing_secret' => 'whsec_' . \Illuminate\Support\Str::random(40),
            'is_default' => $isFirst,
        ], $attributes));
    }
}
