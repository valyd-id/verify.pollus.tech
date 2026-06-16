<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Models\VerificationProject;
use App\Models\VerificationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    private function app(Request $request, int $app): VerificationProject
    {
        /** @var ConsoleUser $user */
        $user = $request->attributes->get('console_user');
        return $user->projects()->findOrFail($app);
    }

    private function present(VerificationWorkflow $w): array
    {
        return [
            'id' => $w->id, // unique workflow id used at session creation
            'name' => $w->name,
            'features' => $w->features,
            'settings' => $w->settings,
            'is_active' => $w->is_active,
            'created_at' => $w->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request, int $app)
    {
        $workflows = $this->app($request, $app)->workflows()->orderByDesc('created_at')->get()
            ->map(fn ($w) => $this->present($w));
        return GlobalHelper::apiSuccess(['workflows' => $workflows]);
    }

    public function store(Request $request, int $app)
    {
        $project = $this->app($request, $app);
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'features' => 'required|array|min:1',
            'features.*' => 'string|in:' . implode(',', config('verify.features')),
            'settings' => 'nullable|array',
        ]);

        $workflow = VerificationWorkflow::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name' => $validated['name'],
            'features' => array_values(array_unique($validated['features'])),
            'settings' => $validated['settings'] ?? ['auto_approve' => true],
            'is_active' => true,
        ]);

        return GlobalHelper::apiSuccess(['workflow' => $this->present($workflow)], 201);
    }

    public function update(Request $request, int $app, string $id)
    {
        $workflow = $this->app($request, $app)->workflows()->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'features' => 'sometimes|array|min:1',
            'features.*' => 'string|in:' . implode(',', config('verify.features')),
            'settings' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);
        if (isset($validated['features'])) {
            $validated['features'] = array_values(array_unique($validated['features']));
        }
        $workflow->update($validated);
        return GlobalHelper::apiSuccess(['workflow' => $this->present($workflow->refresh())]);
    }

    public function destroy(Request $request, int $app, string $id)
    {
        $workflow = $this->app($request, $app)->workflows()->findOrFail($id);
        $workflow->delete();
        return GlobalHelper::apiSuccess(['deleted' => true]);
    }
}
