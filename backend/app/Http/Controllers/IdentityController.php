<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Models\ReusableIdentity;
use App\Models\VerificationProject;
use App\Services\ReusableIdentityService;
use Illuminate\Http\Request;

/**
 * On-demand read of a previously-verified ("verify once, reuse") identity, scoped
 * to the calling project. Lets an integrator fetch a returning user's verified
 * profile + licenses by their own vendor_data (or the pollus_id) without starting
 * a new session — and revoke a stored record. Project API-key authenticated.
 */
class IdentityController extends Controller
{
    public function __construct(private ReusableIdentityService $reusable)
    {
    }

    private function project(Request $request): VerificationProject
    {
        return $request->attributes->get('verification_project');
    }

    /** GET /api/v2/identity?vendor_data=... (or ?pollus_id=...) */
    public function show(Request $request)
    {
        $project = $this->project($request);
        $pollusId = $request->query('pollus_id');
        $vendorData = $request->query('vendor_data');

        $query = ReusableIdentity::where('project_id', $project->id);
        if ($pollusId) {
            $query->where('pollus_id', $pollusId);
        } elseif ($vendorData) {
            $query->where('vendor_data', $vendorData);
        } else {
            return GlobalHelper::apiError('missing_parameter', 'Provide pollus_id or vendor_data.', 400);
        }

        $rec = $query->first();
        if (!$rec || !$rec->isActive()) {
            return GlobalHelper::apiError('not_found', 'No reusable verified identity found.', 404);
        }

        return GlobalHelper::apiSuccess(['identity' => $this->reusable->present($rec)]);
    }

    /** DELETE /api/v2/identity/{pollus_id} — revoke a stored verification. */
    public function revoke(Request $request, string $pollusId)
    {
        $project = $this->project($request);
        $rec = ReusableIdentity::where('project_id', $project->id)->where('pollus_id', $pollusId)->first();
        if (!$rec) {
            return GlobalHelper::apiError('not_found', 'No reusable verified identity found.', 404);
        }
        $rec->update(['revoked_at' => now()]);
        return GlobalHelper::apiSuccess(['revoked' => true]);
    }
}
