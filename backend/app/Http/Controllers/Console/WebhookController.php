<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Models\VerificationProject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    private function app(Request $request, int $app): VerificationProject
    {
        /** @var ConsoleUser $user */
        $user = $request->attributes->get('console_user');
        return $user->projects()->findOrFail($app);
    }

    /** Show webhook config. Signing secret is returned masked unless just regenerated. */
    public function show(Request $request, int $app)
    {
        $p = $this->app($request, $app);
        return GlobalHelper::apiSuccess([
            'webhook_url' => $p->webhook_url,
            'has_signing_secret' => !empty($p->webhook_signing_secret),
            'signing_secret_hint' => $p->webhook_signing_secret ? substr($p->webhook_signing_secret, 0, 10) . '…' : null,
        ]);
    }

    public function update(Request $request, int $app)
    {
        $p = $this->app($request, $app);
        $validated = $request->validate(['webhook_url' => 'nullable|url']);
        $p->update(['webhook_url' => $validated['webhook_url'] ?? null]);
        return $this->show($request, $app);
    }

    /** Regenerate the signing secret; returns the full value ONCE. */
    public function rotateSecret(Request $request, int $app)
    {
        $p = $this->app($request, $app);
        $secret = 'whsec_' . Str::random(40);
        $p->update(['webhook_signing_secret' => $secret]);
        return GlobalHelper::apiSuccess(['signing_secret' => $secret]);
    }
}
