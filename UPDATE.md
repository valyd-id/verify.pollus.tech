# Valyd Verify — Update (what changed)

This release replaces the old in-popup "Continue with Valyd" OIDC reuse model with a cleaner
**dev-passed-token** model, and reorganizes the product around **two clear products** plus a
single merged SDK. See `FLOW.md` for diagrams and `INTEGRATION.md` for the guide.

---

## 1. New identity model — the dev passes the user's Valyd token

**Before:** the verify hosted page itself showed a **"Continue with Valyd"** button that did an
**OIDC redirect** to the IdP and back to a verify callback to learn the user's `pollus_id`.

**Now:** login is the **developer's own TPSSO/OAuth2 integration** with the IdP (their own
OAuth client from **dev.pollus.tech**). The dev logs the user in, holds the user's **Valyd
access token**, and passes it **server-to-server** when creating a verify session. There is no
end-user OIDC bounce inside the verify flow anymore.

- `POST /api/v2/session` now accepts an optional **`valyd_access_token`** (the SDK
  `CreateSessionParams` gains an optional `valydAccessToken`).
- verify **validates** that token against the IdP (`GET /api/auth/tpsso/userinfo`), reads the
  `pollus_id`, and **binds it to the session**.
- If the token is missing / invalid / expired, verify responds **`HTTP 401
  valyd_login_required`** — the dev should log the user in with Valyd first.
- The token is **never** put in a browser/popup URL — server-to-server only.

## 2. Write-back to the IdP + a verify-side copy

On a passing **Managed** check, verify now **writes the result back to the IdP** (the system of
record — marks `id_verified`, upserts the verified license) **and keeps its own copy** (cache +
audit) for reuse. Returning users (we already hold a verified record for this `pollus_id` in
this app) re-verify with a **selfie only** — no second ID scan.

## 3. Removed: the end-user OIDC login path

The whole in-verify OIDC callback path is gone:
- Removed the verify hosted-flow **`valyd`/`reuse` OIDC phases** and the
  **`/verify/valyd-callback`** route + its `ValydCallbackRoute`.
- Removed `lib/pkce.ts` and the `continueWithValyd` OIDC redirect.
- The IdP client drops `exchangeOidcCode`/OIDC config; it keeps `userinfo($token)` (validate +
  read `pollus_id`) and the internal write-back methods.
- The developer **console** login (sign in to the dashboard with Valyd) is unchanged.

## 4. Three-product wizard

The workflow wizard now leads with the product choice:
- **Managed Identity by Valyd** *(recommended)* — login with Valyd, verify once, reuse;
  results live on the IdP + a verify copy. The Managed branch also links out to
  **dev.pollus.tech** to register the login OAuth client and shows the Login-with-Valyd +
  session-with-token recipe.
- **Verify Fresh Every Time** — no login, nothing retained (hosted or standalone).
- **Self-Managed Infrastructure** — shown but **disabled ("coming soon")**.

## 5. One merged SDK

There is now a **single** SDK package — `valyd-verify-sdk` — exposing a unified **`Valyd`**
class that wraps both login and verify:
```js
import { Valyd } from "valyd-verify-sdk";
const valyd = new Valyd({ clientId, clientSecret, apiKey, webhookSecret });
const url = valyd.auth.getAuthorizationUrl({ scope: ["profile","verifications"], redirectUri });
const { accessToken, user } = await valyd.auth.exchangeCode(code);   // user.pollus_id
const session = await valyd.verify.sessions.create({
  workflowId, vendorData: user.pollus_id, valydAccessToken: accessToken, redirectUrl,
});
const decision = await valyd.verify.sessions.decision(session.sessionId);
```
- `valyd.auth` — the IdP / "Login with Valyd" client (`getAuthorizationUrl`, `exchangeCode`,
  `parseCallback`, `userinfo`, `licenses`, `verifications`).
- `valyd.verify` — the Verify client (`sessions`, `workflows`, `standalone.*`,
  `webhooks.constructEvent`, `sessions.decision`).
- The browser popup SDK **`valyd-verify-js`** is unchanged and separate (a popup/modal helper).

---

## Carried over (still true)

- **Billing**: per-API prepaid credits; charge before each check, auto-refund our-side
  failures; hosted sessions guarded up front (`402 insufficient_balance`).
- **License re-check**: every action / scheduled / at expiry; a background job updates the
  record and fires `verification.credential_changed`.
- **Webhooks**: multiple destinations, each with its own signing secret + optional event
  filter; fan-out on hosted decisions; standalone stays synchronous. Signature unchanged:
  `HMAC-SHA256(timestamp + "." + rawBody, secret)` in `X-Valyd-Signature`.
- **On-demand read**: `GET /api/v2/identity?vendor_data=|pollus_id=` (now reads the verify
  copy) + `DELETE /api/v2/identity/{pollus_id}` to revoke.
- **Standalone** single-purpose endpoints unchanged: `/api/v2/{id-verification, liveness,
  face-match, age-verification, credential-verification, kyc-credential, location}`.

## Migration notes (from the old reuse model)

- Move login out of the verify popup: register your OAuth client at **dev.pollus.tech**, add a
  "Login with Valyd" flow in your app, and store the user's access token + `pollus_id`
  server-side.
- When starting a Managed check, pass that token as **`valyd_access_token`** on
  `POST /api/v2/session`; handle the new **`401 valyd_login_required`**.
- Drop any reliance on the verify-hosted "Continue with Valyd" button and the
  `/verify/valyd-callback` redirect URI.
- Swap separate login/verify SDKs for the single `valyd-verify-sdk` `Valyd` client.
