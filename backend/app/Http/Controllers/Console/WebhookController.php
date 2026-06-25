<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Models\VerificationProject;
use App\Models\WebhookEndpoint;
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
            'signing_secret' => $p->webhook_signing_secret,
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

    // --- Multiple webhook endpoints (destinations) --------------------------

    private function present(WebhookEndpoint $e, ?string $secret = null): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'url' => $e->url,
            'events' => $e->events, // null = all
            'is_active' => $e->is_active,
            'secret_hint' => $e->signing_secret ? substr($e->signing_secret, 0, 12) . '…' : null,
            // Full secret is always returned so the console can reveal it anytime.
            'signing_secret' => $secret ?? $e->signing_secret,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    public function list(Request $request, int $app)
    {
        $p = $this->app($request, $app);
        $endpoints = $p->webhookEndpoints()->orderBy('id')->get()->map(fn ($e) => $this->present($e));
        return GlobalHelper::apiSuccess(['endpoints' => $endpoints]);
    }

    public function create(Request $request, int $app)
    {
        $p = $this->app($request, $app);
        $v = $request->validate([
            'name' => 'required|string|max:120',
            'url' => 'required|url',
            'events' => 'nullable|array',
            'events.*' => 'string',
        ]);
        $secret = WebhookEndpoint::newSecret();
        $e = $p->webhookEndpoints()->create([
            'name' => $v['name'],
            'url' => $v['url'],
            'events' => $v['events'] ?? null,
            'signing_secret' => $secret,
            'is_active' => true,
        ]);
        return GlobalHelper::apiSuccess(['endpoint' => $this->present($e, $secret)], 201);
    }

    public function updateEndpoint(Request $request, int $app, int $id)
    {
        $p = $this->app($request, $app);
        $e = $p->webhookEndpoints()->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:120',
            'url' => 'sometimes|url',
            'events' => 'sometimes|nullable|array',
            'events.*' => 'string',
            'is_active' => 'sometimes|boolean',
        ]);
        $e->update($v);
        return GlobalHelper::apiSuccess(['endpoint' => $this->present($e->refresh())]);
    }

    public function destroyEndpoint(Request $request, int $app, int $id)
    {
        $p = $this->app($request, $app);
        $p->webhookEndpoints()->findOrFail($id)->delete();
        return GlobalHelper::apiSuccess(['deleted' => true]);
    }

    public function rotateEndpointSecret(Request $request, int $app, int $id)
    {
        $p = $this->app($request, $app);
        $e = $p->webhookEndpoints()->findOrFail($id);
        $secret = WebhookEndpoint::newSecret();
        $e->update(['signing_secret' => $secret]);
        return GlobalHelper::apiSuccess(['endpoint' => $this->present($e->refresh(), $secret)]);
    }
}
