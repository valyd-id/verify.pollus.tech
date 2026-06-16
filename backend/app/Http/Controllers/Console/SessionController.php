<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Models\VerificationProject;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    private function app(Request $request, int $app): VerificationProject
    {
        /** @var ConsoleUser $user */
        $user = $request->attributes->get('console_user');
        return $user->projects()->findOrFail($app);
    }

    public function index(Request $request, int $app)
    {
        $project = $this->app($request, $app);
        $query = $project->sessions()->orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $sessions = $query->limit((int) $request->query('limit', 50))->get()->map(fn ($s) => [
            'session_id' => $s->id,
            'status' => $s->status,
            'mode' => $s->mode,
            'vendor_data' => $s->vendor_data,
            'features' => $s->features,
            'created_at' => $s->created_at?->toIso8601String(),
            'decided_at' => $s->decided_at?->toIso8601String(),
        ]);

        // Lightweight stat summary for the Overview page.
        $counts = $project->sessions()
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return GlobalHelper::apiSuccess([
            'sessions' => $sessions,
            'stats' => [
                'total' => (int) $counts->sum(),
                'approved' => (int) ($counts['APPROVED'] ?? 0),
                'declined' => (int) ($counts['DECLINED'] ?? 0),
                'in_review' => (int) ($counts['IN_REVIEW'] ?? 0),
            ],
        ]);
    }
}
