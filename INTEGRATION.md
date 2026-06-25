# Valyd Verify — Integration guide (for developers)

Valyd Verify has **two products**. Pick the one that matches your use case:

| Product | Login? | When to use | Results |
|---------|--------|-------------|---------|
| **Managed Identity by Valyd** *(recommended)* | Yes — "Login with Valyd" | The user has (or creates) a Valyd identity; you verify them **once** and reuse the result across sessions/apps | Stored on the user's Valyd identity (the IdP) **+ a verify-side copy** |
| **Verify Fresh Every Time** | No | One-off KYC in the moment; nothing retained | Returned for that session only |

*(A third product, **Self-Managed Infrastructure**, is "coming soon" and disabled.)*

Three phases for either product: **(1) create your account**, **(2) set up in the console**,
**(3) integrate on your site**. See `FLOW.md` for diagrams.

---

## Phase 1 — Create your Valyd account

1. Go to **https://verify.pollus.tech/dashboard**.
2. Click **Login with Valyd** → you sign in at **idp.pollus.tech**. On return, your
   developer (console) account is created automatically — no separate signup.

---

## Phase 2 — Set up in the console

1. **Create an App** → you get a **verify API key** (shown once). Keep it **server-side only**.
2. **Top up balance** (Billing) — every check costs credits; our-side failures auto-refund.
3. **Create a Workflow** in the wizard (Workflows → New workflow):
   - **Product** — *Managed Identity by Valyd* or *Verify Fresh Every Time*.
   - **Checks** — ID, liveness, face match, age, professional license, location.
   - **Re-check policy** (if a license is included) — every action / scheduled / at expiry.
   - You get a **`workflow_id`**.
4. **(Managed only) Register your login OAuth client at https://dev.pollus.tech** →
   copy your **`client_id`** + **`client_secret`**, register your redirect URI(s), and pick
   the scopes you need: `profile`, `verifications`, `doctor_license`, `zkp`. This client is
   how *your* app does "Login with Valyd" directly with the IdP.
5. **Add Webhook destination(s)** (Webhooks) — one or many URLs, each with its own
   **signing secret**. Events fan out to every active endpoint.

> **What lives where (Managed):** the **IdP** (`idp.pollus.tech` / `idp.valyd.id`) authenticates
> the user and is the **system of record** for their verified identity. **dev.pollus.tech** is
> where you register your login OAuth client. **verify.pollus.tech** runs the actual checks,
> writes the result back to the IdP, and keeps a copy for reuse/audit.

---

## Phase 3 — Integrate

### One SDK (Node)

There is a single SDK — `valyd-verify-sdk` — exposing a unified `Valyd` client:

```js
import { Valyd } from "valyd-verify-sdk";

const valyd = new Valyd({
  clientId:      process.env.VALYD_CLIENT_ID,      // login (dev.pollus.tech) — Managed only
  clientSecret:  process.env.VALYD_CLIENT_SECRET,  // login — server-side only
  apiKey:        process.env.VALYD_API_KEY,         // verify — server-side only
  webhookSecret: process.env.VALYD_WEBHOOK_SECRET,  // webhook signing secret
});

// valyd.auth   → the IdP / "Login with Valyd" client
//   .getAuthorizationUrl(), .exchangeCode(), .parseCallback(), .userinfo(), .licenses(), .verifications()
// valyd.verify → the Verify client
//   .sessions.create(), .sessions.decision(), .workflows, .standalone.*, .webhooks.constructEvent()
```

All of `clientSecret`, `apiKey`, `webhookSecret`, and the user's Valyd access token are
**server-side only** — never send them to the browser.

---

## Product A — Managed Identity by Valyd (recommended)

Three moving parts: **(1) log the user in with Valyd**, **(2) open a verify session that
carries the user's Valyd token**, **(3) read the result**.

### A1 — Login with Valyd (your app ↔ the IdP, TPSSO/OAuth2)

Add a **"Login with Valyd"** button. It is a direct OAuth2 integration between your app and
the IdP using the `client_id`/`client_secret` you registered at dev.pollus.tech.

```js
// 1) Send the user to the IdP authorize URL
const url = valyd.auth.getAuthorizationUrl({
  scope: ["profile", "verifications"],            // add doctor_license / zkp if needed
  redirectUri: "https://app.yoursite.com/auth/valyd/callback",
});
// redirect the browser to `url`
```

The IdP authenticates the user — it shows the face/login screen **only** if the user has no
Valyd session; otherwise it is **silent SSO**. It then redirects back to your `redirect_uri`
with a `?code=…`. Your **backend** exchanges that code:

```js
// 2) On your callback route — exchange the code (server-side, uses client_secret)
const { accessToken, user } = await valyd.auth.exchangeCode(code);
// user.pollus_id  → the stable user key
// user.id_verified → whether they already have a verified Valyd identity
// store accessToken + user.pollus_id in YOUR session
```

Raw HTTP equivalent of the token exchange:

```bash
curl -X POST https://idp.pollus.tech/api/auth/tpsso/token \
  -d grant_type=authorization_code \
  -d client_id=$VALYD_CLIENT_ID -d client_secret=$VALYD_CLIENT_SECRET \
  -d code=$CODE
# → { access_token, refresh_token, user: { pollus_id, id_verified, ... } }
```

You now hold the user's **Valyd access token** in your session. That token is the proof the
user is logged into Valyd.

### A2 — Verify the user (verify session carrying the Valyd token)

When the logged-in user needs a check (KYC, license…), your **backend** opens a verify
session and **passes the Valyd access token** server-to-server (never in a browser URL):

```bash
curl -X POST https://verify.pollus.tech/api/v2/session \
  -H "X-API-Key: $VALYD_API_KEY" -H "Content-Type: application/json" \
  -d '{ "workflow_id": "<workflow_id>",
        "valyd_access_token": "<the user'\''s Valyd access token>",
        "vendor_data": "<your_user_id or pollus_id>",
        "redirect_url": "https://app.yoursite.com/verify/done",
        "callback": "https://api.yoursite.com/webhooks/valyd" }'
# → { "url": "...", "session_id": "...", "session_token": "..." }
```

With the SDK:

```js
const session = await valyd.verify.sessions.create({
  workflowId: "<workflow_id>",
  valydAccessToken: accessToken,            // ← the user's Valyd token from login
  vendorData: user.pollus_id,
  redirectUrl: "https://app.yoursite.com/verify/done",
  callback: "https://api.yoursite.com/webhooks/valyd",
});
// return session.url to the browser
```

What verify does with the token: it calls `GET https://idp.pollus.tech/api/auth/tpsso/userinfo`
with `Authorization: Bearer <token>` to **validate** it and read the user's `pollus_id`, then
**binds that `pollus_id`** to the session and returns `{ url }`.

- If the token is **missing / invalid / expired**, verify responds **`HTTP 401
  valyd_login_required`** — log the user in with Valyd first (step A1), then retry.

Then redirect the browser to `session.url` (full-page redirect recommended; the
`valyd-verify-js` popup works for simple flows):

```js
window.location.href = url;
```

- **First-time** users complete the full workflow.
- **Returning** users (we already hold a verified record for this `pollus_id` in this app)
  re-verify with a **selfie only** — no second ID scan.

On approval, verify **writes the result back to the IdP** (marks `id_verified`, upserts the
verified license) and keeps its own copy, then redirects the user back to your `redirect_url`.

### A3 — Read the result

Two authoritative ways (use either or both):

- **Webhook** to your `callback` / configured endpoints. Verify the signature:
  `HMAC-SHA256(timestamp + "." + rawBody, signing_secret)` == `X-Valyd-Signature`
  (also sent: `X-Valyd-Timestamp`, `X-Valyd-Event-Id`). Events:
  `verification.approved | declined | in_review | expired | abandoned`,
  `verification.credential_changed`.
- **Decision API**:
  ```bash
  curl https://verify.pollus.tech/api/v2/session/<session_id>/decision \
    -H "X-API-Key: $VALYD_API_KEY"
  # → { status, checks[], pollus_id, identity }
  # identity = { full_name, dob, age_bands, licenses, verified_at } | null
  ```
  ```js
  const decision = await valyd.verify.sessions.decision(session.sessionId);
  ```

### A4 — On-demand reuse (no new session)

For back-office reuse of an already-verified user:

```bash
curl "https://verify.pollus.tech/api/v2/identity?vendor_data=<your_user_id>" \
  -H "X-API-Key: $VALYD_API_KEY"
# or ?pollus_id=<pollus_id>
# → { identity: { full_name, dob, age_bands, licenses, verified_at } }   (404 if not verified)

curl -X DELETE "https://verify.pollus.tech/api/v2/identity/<pollus_id>" \
  -H "X-API-Key: $VALYD_API_KEY"   # revoke the verify-side copy
```

Records are isolated **per app** and reusable **until you revoke** them; license freshness is
kept by the re-check engine.

---

## Product B — Verify Fresh Every Time (no login)

Nothing is retained. No Valyd token, no `pollus_id`.

### Hosted (we host the camera page)

```bash
curl -X POST https://verify.pollus.tech/api/v2/session \
  -H "X-API-Key: $VALYD_API_KEY" -H "Content-Type: application/json" \
  -d '{ "workflow_id": "<workflow_id>", "vendor_data": "<your_user_id>",
        "redirect_url": "https://app.yoursite.com/verify/done",
        "callback": "https://api.yoursite.com/webhooks/valyd" }'
# → { url, session_id, session_token }   (note: NO valyd_access_token)
```

```js
const session = await valyd.verify.sessions.create({
  workflowId: "<workflow_id>",
  vendorData: "<your_user_id>",
  redirectUrl: "https://app.yoursite.com/verify/done",
  callback: "https://api.yoursite.com/webhooks/valyd",
});
window.location.href = session.url; // or valyd-verify-js popup
```

Read the result the same way as A3 — webhook + `GET /api/v2/session/{id}/decision`.

### Standalone (your own UI, synchronous server-to-server)

Capture images yourself and call a single-purpose endpoint; the result comes back in the
response:

```bash
curl -X POST https://verify.pollus.tech/api/v2/kyc-credential \
  -H "X-API-Key: $VALYD_API_KEY" \
  -F "front_image=@id_front.jpg" -F "selfie=@selfie.jpg" \
  -F "license_state=CA" -F "license_number=A12345"
```

Endpoints: `/api/v2/{id-verification, liveness, face-match, age-verification,
credential-verification, kyc-credential, location}`. With the SDK these are under
`valyd.verify.standalone.*`. (No webhook — you already have the answer.)

---

## Quick checklist

1. Login with Valyd at `/dashboard` → console account created.
2. Create App → copy **verify API key** (server-side only); top up balance.
3. Create a Workflow → pick **Managed** or **Verify Fresh** → copy `workflow_id`.
4. **(Managed)** Register your login client at **dev.pollus.tech** → copy `client_id` +
   `client_secret`, set redirect URIs + scopes.
5. Add a Webhook destination → copy its signing secret.
6. **(Managed)** Add "Login with Valyd" → `getAuthorizationUrl` → callback → `exchangeCode`
   → store `access_token` + `pollus_id` in your session.
7. Create a verify session **with `valyd_access_token`** (Managed) or **without** it (Verify
   Fresh); redirect the browser to `url`. Handle `401 valyd_login_required` (Managed) and
   `402 insufficient_balance`.
8. Read the result on your `redirect_url` via the decision API and/or the webhook (verify the
   signature).
9. **(Managed)** Reuse on demand via `/api/v2/identity`; revoke via `DELETE`.

> Security: the verify **API key**, the OAuth **client_secret**, and the user's **Valyd access
> token** are all server-side only. Never put them in the browser or in a popup/redirect URL.
