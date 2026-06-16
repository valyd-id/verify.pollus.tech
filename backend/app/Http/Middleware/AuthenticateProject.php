<?php

namespace App\Http\Middleware;

use App\Helpers\GlobalHelper;
use App\Models\VerificationProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the calling VerificationProject from an X-API-Key (or Bearer) header
 * and attaches it to the request as `verification_project`.
 */
class AuthenticateProject
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return GlobalHelper::apiError('API_KEY_MISSING', 'API key is required. Provide X-API-Key or Authorization: Bearer.', 401);
        }

        $project = VerificationProject::where('api_key_hash', VerificationProject::hashKey($apiKey))
            ->where('is_active', true)
            ->first();

        if (!$project) {
            return GlobalHelper::apiError('API_KEY_INVALID', 'Invalid or inactive API key.', 401);
        }

        $request->attributes->set('verification_project', $project);

        return $next($request);
    }

    private function extractApiKey(Request $request): ?string
    {
        if ($request->hasHeader('X-API-Key')) {
            return trim((string) $request->header('X-API-Key'));
        }
        if ($request->hasHeader('Authorization')) {
            if (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }
}
