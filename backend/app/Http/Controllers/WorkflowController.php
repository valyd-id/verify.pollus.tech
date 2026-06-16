<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\VerificationProject;
use App\Models\VerificationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    private function project(Request $request): VerificationProject
    {
        return $request->attributes->get('verification_project');
    }

    public function index(Request $request)
    {
        $workflows = $this->project($request)->workflows()->orderByDesc('created_at')->get();
        return GlobalHelper::apiSuccess(['workflows' => $workflows]);
    }

    public function store(Request $request)
    {
        $project = $this->project($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'features' => 'required|array|min:1',
            'features.*' => 'string|in:' . implode(',', config('verify.features')),
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        foreach ($validated['features'] as $feature) {
            if (!$project->allowsFeature($feature)) {
                return GlobalHelper::apiError('feature_not_allowed', "Feature '{$feature}' is not enabled for this project.", 403);
            }
        }

        $workflow = VerificationWorkflow::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name' => $validated['name'],
            'features' => array_values(array_unique($validated['features'])),
            'settings' => $validated['settings'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return GlobalHelper::apiSuccess(['workflow' => $workflow], 201);
    }

    public function show(Request $request, string $id)
    {
        $workflow = $this->project($request)->workflows()->find($id);
        if (!$workflow) {
            return GlobalHelper::apiError('not_found', 'Workflow not found.', 404);
        }
        return GlobalHelper::apiSuccess(['workflow' => $workflow]);
    }

    public function update(Request $request, string $id)
    {
        $project = $this->project($request);
        $workflow = $project->workflows()->find($id);
        if (!$workflow) {
            return GlobalHelper::apiError('not_found', 'Workflow not found.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'features' => 'sometimes|array|min:1',
            'features.*' => 'string|in:' . implode(',', config('verify.features')),
            'settings' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['features'])) {
            foreach ($validated['features'] as $feature) {
                if (!$project->allowsFeature($feature)) {
                    return GlobalHelper::apiError('feature_not_allowed', "Feature '{$feature}' is not enabled for this project.", 403);
                }
            }
            $validated['features'] = array_values(array_unique($validated['features']));
        }

        $workflow->update($validated);
        return GlobalHelper::apiSuccess(['workflow' => $workflow->refresh()]);
    }

    public function destroy(Request $request, string $id)
    {
        $workflow = $this->project($request)->workflows()->find($id);
        if (!$workflow) {
            return GlobalHelper::apiError('not_found', 'Workflow not found.', 404);
        }
        $workflow->delete();
        return GlobalHelper::apiSuccess(['deleted' => true]);
    }
}
