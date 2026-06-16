#!/usr/bin/env bash
#
# Register (or update) the "verify-console" FIRST-PARTY OIDC client at the Valyd IdP.
#
# First-party OIDC clients live in the IdP's `oidc_clients` table and use PKCE
# (NO client_secret). They are NOT created via /api/internal/clients (that endpoint
# manages TPSSO OAuthClients). The correct way is to upsert the OidcClient row using
# the IdP application's own Eloquent model — run this ON THE IDP HOST.
#
# Usage (run on the idp.pollus.tech server):
#   ./register-verify-console.sh
#
# Optional env overrides:
#   IDP_DIR        default /var/www/pollus_main_servers/idp.pollus.tech/backend
#   CLIENT_ID      default verify-console
#   REDIRECTS      default both prod + local dev (comma-separated)
#                  e.g. REDIRECTS="https://verify.pollus.tech/login,http://localhost:5180/login"
#   SCOPES         default openid,profile,email   (comma-separated)
#
set -euo pipefail

IDP_DIR="${IDP_DIR:-/var/www/pollus_main_servers/idp.pollus.tech/backend}"
CLIENT_ID="${CLIENT_ID:-verify-console}"
REDIRECTS="${REDIRECTS:-https://verify.pollus.tech/login,http://localhost:5180/login}"
SCOPES="${SCOPES:-openid,profile,email}"

if [[ ! -f "$IDP_DIR/artisan" ]]; then
  echo "ERROR: IdP app not found at $IDP_DIR (set IDP_DIR=...)." >&2
  exit 1
fi

# Turn comma lists into PHP array literals.
to_php_array() { IFS=',' read -ra A <<< "$1"; out=""; for x in "${A[@]}"; do out+="\"${x}\","; done; echo "[${out%,}]"; }
REDIRECTS_PHP="$(to_php_array "$REDIRECTS")"
SCOPES_PHP="$(to_php_array "$SCOPES")"

echo "→ Upserting first-party OIDC client '${CLIENT_ID}' in the IdP database"
echo "  redirect_uris=${REDIRECTS_PHP}  scopes=${SCOPES_PHP}"

cd "$IDP_DIR"
php artisan tinker --execute="
\$c = \App\Models\OidcClient::updateOrCreate(
  ['client_id' => '${CLIENT_ID}'],
  [
    'client_secret_hash' => null,
    'allowed_redirect_uris' => ${REDIRECTS_PHP},
    'allowed_scopes' => ${SCOPES_PHP},
    'name' => 'Verify Console',
    'description' => 'Developer console for verify.pollus.tech',
    'client_type' => 'first_party_oidc',
    'is_trusted' => true,
    'public_client' => true,
    'pkce_required' => true,
    'skip_consent' => true,
    'is_active' => true,
    'is_test' => false,
  ]
);
echo 'OK '.\$c->client_id.' ('.\$c->client_type.')'.PHP_EOL;
"

cat <<ENV

✓ Done. Set these in verify.pollus.tech/backend/.env (no client secret — PKCE):

VALYD_OIDC_BASE_URL=https://idp.pollus.tech
VALYD_OIDC_CLIENT_ID=${CLIENT_ID}
VALYD_OIDC_REDIRECT_URI=https://verify.pollus.tech/login
VALYD_OIDC_SCOPES="$(echo "$SCOPES" | tr ',' ' ')"

Then:  cd verify.pollus.tech/backend && php artisan config:cache
(For local dev, set VALYD_OIDC_REDIRECT_URI=http://localhost:5180/login instead.)
ENV
