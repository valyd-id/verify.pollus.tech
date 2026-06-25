<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Models\VerificationProject;
use App\Services\ConsoleProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppController extends Controller
{
    /** Max accepted logo size as a base64 data URL (~350KB image). */
    private const MAX_LOGO_BYTES = 500_000;

    public function __construct(private ConsoleProvisioner $provisioner)
    {
    }

    private function user(Request $request): ConsoleUser
    {
        return $request->attributes->get('console_user');
    }

    private function present(VerificationProject $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'app_id' => $p->app_id,                   // public-facing App ID (UUID)
            'description' => $p->description,
            'logo' => $p->logo,                       // base64 data URL or null
            'api_key_prefix' => $p->api_key_prefix,
            // Full key so the console can reveal it anytime (null for keys created
            // before storage was added — rotate to populate).
            'api_key' => $p->api_key,
            'webhook_url' => $p->webhook_url,
            'is_active' => $p->is_active,
            'is_default' => $p->is_default,
            'created_at' => $p->created_at?->toIso8601String(),
            'workflows_count' => $p->workflows()->count(),
        ];
    }

    public function index(Request $request)
    {
        $apps = $this->user($request)->projects()
            ->orderByDesc('is_default')->orderBy('id')
            ->get()->map(fn ($p) => $this->present($p));

        return GlobalHelper::apiSuccess(['apps' => $apps]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'logo' => 'nullable|string',
        ]);

        if (($logo = $this->validateLogo($validated['logo'] ?? null)) instanceof \Illuminate\Http\JsonResponse) {
            return $logo;
        }

        [$project, $rawKey] = $this->provisioner->createApp($this->user($request), $validated['name'], array_filter([
            'description' => $validated['description'] ?? null,
            'logo' => $logo,
        ], fn ($v) => $v !== null));

        return GlobalHelper::apiSuccess([
            'app' => $this->present($project),
            // The raw API key is shown ONCE, only at creation.
            'api_key' => $rawKey,
        ], 201);
    }

    /** Update name / description / logo and (optionally) make this the default app. */
    public function update(Request $request, int $app)
    {
        $project = $this->user($request)->projects()->findOrFail($app);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'description' => 'sometimes|nullable|string|max:2000',
            'logo' => 'sometimes|nullable|string',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($request->has('logo')) {
            if (($logo = $this->validateLogo($validated['logo'] ?? null)) instanceof \Illuminate\Http\JsonResponse) {
                return $logo;
            }
            $project->logo = $logo;
        }
        if ($request->has('name')) {
            $project->name = $validated['name'];
        }
        if ($request->has('description')) {
            $project->description = $validated['description'];
        }

        DB::transaction(function () use ($request, $project, $validated) {
            // Making an app default clears the flag on the user's other apps.
            if (($validated['is_default'] ?? false) === true && !$project->is_default) {
                $this->user($request)->projects()->where('id', '!=', $project->id)->update(['is_default' => false]);
                $project->is_default = true;
            }
            $project->save();
        });

        return GlobalHelper::apiSuccess(['app' => $this->present($project->refresh())]);
    }

    /** Delete an app. The default app is protected and cannot be deleted. */
    public function destroy(Request $request, int $app)
    {
        $project = $this->user($request)->projects()->findOrFail($app);

        if ($project->is_default) {
            return GlobalHelper::apiError('app_default_protected', 'The default app cannot be deleted. Set another app as default first.', 422);
        }

        $project->delete(); // workflows + sessions cascade at the DB level

        return GlobalHelper::apiSuccess(['deleted' => true]);
    }

    /** Rotate the API key for an app (returns the new raw key once). */
    public function rotateKey(Request $request, int $app)
    {
        $project = $this->user($request)->projects()->findOrFail($app);
        $rawKey = VerificationProject::generateApiKey();
        $project->update([
            'api_key_hash' => VerificationProject::hashKey($rawKey),
            'api_key_prefix' => substr($rawKey, 0, 6),
            'api_key' => $rawKey,
        ]);

        return GlobalHelper::apiSuccess(['app' => $this->present($project->refresh()), 'api_key' => $rawKey]);
    }

    /**
     * Validate an optional logo data URL. Returns the (normalised) string, null,
     * or a JsonResponse error when the payload is rejected.
     */
    private function validateLogo(?string $logo)
    {
        if ($logo === null || $logo === '') {
            return null;
        }
        if (!str_starts_with($logo, 'data:image/')) {
            return GlobalHelper::apiError('logo_invalid', 'Logo must be an image data URL.', 422);
        }
        if (strlen($logo) > self::MAX_LOGO_BYTES) {
            return GlobalHelper::apiError('logo_too_large', 'Logo image is too large. Please use a smaller file.', 422);
        }
        return $logo;
    }
}
