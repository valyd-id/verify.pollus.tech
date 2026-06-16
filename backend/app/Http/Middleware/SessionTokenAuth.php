<?php

namespace App\Http\Middleware;

use App\Helpers\GlobalHelper;
use App\Models\VerificationSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a hosted-page request by its short-lived session_token (the
 * {token} route segment). Attaches the resolved session as `verification_session`.
 */
class SessionTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        if ($token === '') {
            return GlobalHelper::apiError('token_missing', 'Session token is required.', 401);
        }

        $session = VerificationSession::where('session_token', $token)->first();

        if (!$session) {
            return GlobalHelper::apiError('invalid_token', 'Invalid session token.', 401);
        }

        if ($session->isTerminal()) {
            return GlobalHelper::apiError('session_closed', 'This verification session is already closed.', 409, [
                'status' => $session->status,
            ]);
        }

        if ($session->isExpired()) {
            // Lazily finalize as expired so the client gets a webhook too.
            app(\App\Services\SessionService::class)->expire($session);
            return GlobalHelper::apiError('session_expired', 'This verification session has expired.', 410);
        }

        $request->attributes->set('verification_session', $session);

        return $next($request);
    }
}
