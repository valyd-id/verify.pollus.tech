<?php

namespace App\Http\Middleware;

use App\Helpers\GlobalHelper;
use App\Models\ConsoleUser;
use App\Support\ConsoleToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a developer-console request by the Bearer console token
 * (issued after Valyd SSO login). Attaches the ConsoleUser as `console_user`.
 */
class ConsoleAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = null;
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m)) {
            $token = trim($m[1]);
        }

        $userId = ConsoleToken::verify($token);
        if (!$userId) {
            return GlobalHelper::apiError('unauthenticated', 'Sign in to continue.', 401);
        }

        $user = ConsoleUser::find($userId);
        if (!$user) {
            return GlobalHelper::apiError('unauthenticated', 'Account not found.', 401);
        }

        $request->attributes->set('console_user', $user);
        return $next($request);
    }
}
