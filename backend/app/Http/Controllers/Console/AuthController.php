<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Services\ConsoleProvisioner;
use App\Support\ConsoleToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Developer-console auth via Valyd SSO (OIDC relying-party against idp.pollus.tech).
 * Browser does PKCE + redirect; backend exchanges the code (with client secret),
 * provisions the ConsoleUser, and issues a console bearer token.
 */
class AuthController extends Controller
{
    public function __construct(private ConsoleProvisioner $provisioner)
    {
    }

    /** Public OIDC config the SPA needs to build the authorize URL (no secret). */
    public function config()
    {
        $cfg = config('services.valyd_oidc');
        $clientId = $cfg['client_id'] ?? null;
        return GlobalHelper::apiSuccess([
            'configured' => !empty($clientId),
            'client_id' => $clientId,
            'authorize_url' => rtrim($cfg['base_url'], '/') . '/' . ltrim($cfg['authorize_path'], '/'),
            'redirect_uri' => $cfg['redirect_uri'],
            'scopes' => $cfg['scopes'],
        ]);
    }

    /** Exchange the authorization code (+ PKCE verifier) for a console session. */
    public function callback(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'code_verifier' => 'nullable|string',
            'redirect_uri' => 'nullable|url',
        ]);

        $cfg = config('services.valyd_oidc');
        // First-party Valyd clients use PKCE (no client_secret); only client_id is required.
        if (empty($cfg['client_id'])) {
            return GlobalHelper::apiError('sso_not_configured', 'Valyd SSO is not configured on this server.', 503);
        }

        // Use the redirect_uri the browser actually used at /authorize (must match
        // at the token endpoint). Falls back to the configured default.
        $redirectUri = $validated['redirect_uri'] ?? $cfg['redirect_uri'];
        $tokenUrl = rtrim($cfg['base_url'], '/') . '/' . ltrim($cfg['token_path'], '/');

        try {
            $tokenRes = Http::asForm()->acceptJson()->timeout(20)->post($tokenUrl, array_filter([
                'grant_type' => 'authorization_code',
                'code' => $validated['code'],
                'redirect_uri' => $redirectUri,
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'code_verifier' => $validated['code_verifier'] ?? null,
            ]));

            if (!$tokenRes->successful()) {
                Log::warning('Valyd OIDC token exchange failed', ['status' => $tokenRes->status(), 'body' => $tokenRes->body()]);
                return GlobalHelper::apiError('sso_exchange_failed', 'Could not complete sign-in with Valyd.', 401);
            }

            $accessToken = $tokenRes->json('access_token');
            $idToken = $tokenRes->json('id_token');

            // Primary: read claims from the id_token (JWT). Fallback: OIDC userinfo.
            $claims = $this->decodeJwtClaims($idToken);
            if (empty($claims['email']) && $accessToken) {
                $userinfoUrl = rtrim($cfg['base_url'], '/') . '/' . ltrim($cfg['userinfo_path'], '/');
                $info = Http::withToken($accessToken)->acceptJson()->timeout(20)->get($userinfoUrl)->json();
                if (is_array($info)) {
                    $claims = array_merge($info, array_filter($claims));
                }
            }

            $email = $claims['email'] ?? null;
            $sub = $claims['sub'] ?? $claims['pollus_id'] ?? $claims['pollus_user_id'] ?? null;
            $name = $claims['name'] ?? trim(($claims['given_name'] ?? '') . ' ' . ($claims['family_name'] ?? '')) ?: null;

            // Email is optional: Valyd may not return one. We still sign the user
            // in (keyed by their Valyd id) and let them add an email later. We only
            // need *some* stable identifier to provision the account.
            if (!$sub && !$email) {
                return GlobalHelper::apiError('sso_no_identity', 'Valyd did not return any account identity.', 422);
            }

            $user = $this->provisioner->fromSso($sub ? (string) $sub : null, $email, $name);

            return GlobalHelper::apiSuccess([
                'token' => ConsoleToken::issue($user->id),
                'user' => ['id' => $user->id, 'email' => $user->email, 'name' => $user->name],
                'needs_email' => empty($user->email),
            ]);
        } catch (\Exception $e) {
            Log::error('Valyd OIDC callback error: ' . $e->getMessage());
            return GlobalHelper::apiError('sso_error', 'Sign-in failed. Please try again.', 500);
        }
    }

    /** Organization/account profile fields the console can edit. */
    private const PROFILE_FIELDS = [
        'org_name', 'legal_name', 'address1', 'address2', 'city', 'state',
        'postal_code', 'country', 'company_phone', 'tax_id', 'website',
        'tos_url', 'logo', 'require_2fa',
    ];

    private function presentUser(ConsoleUser $user): array
    {
        $profile = (array) ($user->profile ?? []);
        // Ensure every known field is present (so the UI binds cleanly).
        $profile = array_merge(array_fill_keys(self::PROFILE_FIELDS, null), $profile);
        $profile['require_2fa'] = (bool) ($profile['require_2fa'] ?? false);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'profile' => $profile,
        ];
    }

    public function me(Request $request)
    {
        $user = $request->attributes->get('console_user');
        return GlobalHelper::apiSuccess(['user' => $this->presentUser($user)]);
    }

    /** Update the signed-in developer's account/organization profile. */
    public function updateAccount(Request $request)
    {
        $user = $request->attributes->get('console_user');

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:160',
            'email' => 'sometimes|nullable|string|max:190',
            'org_name' => 'sometimes|nullable|string|max:190',
            'legal_name' => 'sometimes|nullable|string|max:190',
            'address1' => 'sometimes|nullable|string|max:190',
            'address2' => 'sometimes|nullable|string|max:190',
            'city' => 'sometimes|nullable|string|max:120',
            'state' => 'sometimes|nullable|string|max:120',
            'postal_code' => 'sometimes|nullable|string|max:40',
            'country' => 'sometimes|nullable|string|max:120',
            'company_phone' => 'sometimes|nullable|string|max:60',
            'tax_id' => 'sometimes|nullable|string|max:80',
            'website' => 'sometimes|nullable|string|max:190',
            'tos_url' => 'sometimes|nullable|string|max:190',
            'logo' => 'sometimes|nullable|string|max:700000',
            'require_2fa' => 'sometimes|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $email = trim((string) ($validated['email'] ?? ''));
            $user->email = $email !== '' ? $email : null;
        }

        $profile = (array) ($user->profile ?? []);
        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $profile[$field] = $validated[$field];
            }
        }
        $user->profile = $profile;
        $user->save();

        return GlobalHelper::apiSuccess(['user' => $this->presentUser($user)]);
    }

    /**
     * Let a signed-in developer add (or change) their email after SSO when Valyd
     * didn't provide one. Intentionally unvalidated — the user may type anything
     * or skip it entirely; an empty value just clears it.
     */
    public function setEmail(Request $request)
    {
        $user = $request->attributes->get('console_user');
        $email = trim((string) $request->input('email', ''));
        $user->email = $email !== '' ? $email : null;
        $user->save();

        return GlobalHelper::apiSuccess(['user' => $this->presentUser($user)]);
    }

    public function logout()
    {
        // Stateless tokens: the client simply discards it.
        return GlobalHelper::apiSuccess(['ok' => true]);
    }

    /** Decode (without verifying) the claims payload of a JWT id_token. */
    private function decodeJwtClaims(?string $jwt): array
    {
        if (!$jwt || substr_count($jwt, '.') < 2) {
            return [];
        }
        $payload = explode('.', $jwt)[1];
        $json = base64_decode(strtr($payload, '-_', '+/'));
        $claims = json_decode((string) $json, true);
        return is_array($claims) ? $claims : [];
    }
}
