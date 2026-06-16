<?php

namespace App\Http\Controllers;

use App\Helpers\GlobalHelper;
use App\Services\CredentialClient;
use Illuminate\Http\Request;

/**
 * Credential discovery — exposes vc.pollus.tech's public state/provider catalog
 * so the hosted UI (and standalone integrators) can populate the state and
 * license-type pickers before running a credential check. Pure metadata: no
 * audit session is created.
 *
 * Mounted under both the standalone API (project key) and the hosted page
 * (session token). The hosted routes carry a leading {token} segment which is
 * consumed by the session.token middleware and ignored here — Laravel binds the
 * {state} route parameter by name, so the same methods serve both groups.
 */
class CredentialDiscoveryController extends Controller
{
    public function __construct(private CredentialClient $credentials)
    {
    }

    public function states()
    {
        return GlobalHelper::apiSuccess($this->credentials->states());
    }

    /**
     * Laravel fills scalar controller arguments from route parameters positionally
     * (not by name), so the leading {token} on the hosted route lands in the first
     * scalar slot. Accept both shapes: standalone passes {state} only ($a), hosted
     * passes {token},{state} ($a=token, $b=state). The real state is `$b ?? $a`.
     */
    public function providers(Request $request, string $a, ?string $b = null)
    {
        $state = $b ?? $a;
        return GlobalHelper::apiSuccess($this->credentials->providers($state));
    }
}
