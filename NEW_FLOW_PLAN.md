# Valyd Verify — New flow plan (Login with Valyd + Verify)

> Status: **plan / proposal** (not yet implemented). Confirmed decisions:
> 1. **Login** = the dev integrates **TPSSO directly with the IdP** (their own OAuth client
>    from dev.pollus.tech). verify.pollus.tech is the console/guide for this part.
> 2. **Verification actions** (KYC, license, etc.) **run on verify.pollus.tech**, launched
>    after the user is logged into Valyd.
> 3. **Identity hand-off**: the dev **passes the user's Valyd access token** when launching
>    verify; verify **validates it against the IdP** (`/tpsso/userinfo`) to learn the
>    `pollus_id` — no second login bounce.
> 4. **Results**: verify **writes the result back to the IdP** (so it joins the user's Valyd
>    identity) **and keeps its own copy** (cache + audit).

---

## 1. The big picture — two products

The first wizard choice is the one that changes everything: **does a verified identity
persist (login with Valyd, reuse the result), or is every action checked fresh and
discarded?**

| Product | What it is | Login | Where checks run | Where results live |
|--------|-----------|-------|------------------|--------------------|
| **Managed Identity by Valyd** (`sso`) — *Recommended* | User logs in with Valyd. When they need a check (KYC/license), they do it once on verify; the result attaches to their Valyd identity and is reusable. | **Dev ↔ IdP** (TPSSO, dev's own client) | **verify.pollus.tech** | **IdP** (system of record) **+ a verify copy** |
| **Verify Fresh Every Time** (`verify`) | Full KYC in the moment, nothing retained. | none | verify.pollus.tech | nothing retained |
| Self-Managed Infrastructure | Dev hosts biometrics themselves. | — | — | disabled ("coming soon") |

**Who does what in the Managed product:**
- **IdP** (`idp.pollus.tech` / `idp.valyd.id`) — authenticates the user (Login with Valyd),
  and is the **system of record** for the verified identity (`human_verifications`,
  `license_verifications`, `id_verified`).
- **dev.pollus.tech** — where the **dev registers their OAuth client** (`client_id`,
  `client_secret`, redirect URIs, scopes) for the login.
- **verify.pollus.tech** — **performs the verification work** (ID/OCR, liveness, face match,
  age, license registry, location) via its engines, then writes the result back to the IdP
  and keeps a copy. Also the **console/wizard** the dev uses to set this up.

---

## 2. Your key question — answered

> **"When the user comes to the verify popup, how do we know they're logged into Valyd, so
> we can say 'log in first' otherwise proceed?"**

Because the dev has **already logged the user in with Valyd** (TPSSO, directly with the
IdP), the dev's backend is holding that user's **Valyd access token**. So the hand-off is:

1. The dev's **backend** opens a verify session by calling
   `POST /api/v2/session` (authenticated with the dev's `X-API-Key`) and **includes the
   user's Valyd `access_token`** (server-to-server — the token never goes in a browser URL).
2. verify's **backend validates that token against the IdP**:
   `GET /api/auth/tpsso/userinfo` with `Authorization: Bearer <token>` →
   - **Valid** → the IdP returns the user (`sub` = **`pollus_id`**, `id_verified`, profile).
     verify **binds that `pollus_id` to the session** → the user is "logged in" → **proceed**
     with the check.
   - **Missing / invalid / expired** → verify shows **"Log in with Valyd first."** (Default
     handling: the verify page tells the user to return to the app and sign in; see Open
     item #1 for an optional in-verify login bounce.)
3. After the check runs, verify's backend calls the **IdP backend** to **record the result**
   against that `pollus_id` (internal endpoints), and keeps a verify-side copy.

**So the source of truth for "is this user logged in" is the Valyd access token the dev
hands over, validated server-side by verify against the IdP.** No cross-origin cookie
reading, no second login screen in the happy path. The token's validity *is* the proof of
login; the `pollus_id` inside it is the stable user key.

---

## 3. Managed Identity by Valyd — the full flow

### 3a. Dev setup (one time)
1. In the verify console wizard → pick **Managed Identity by Valyd** → choose the checks
   they'll offer (KYC, license, etc.) and the scopes their login needs.
2. The wizard links them to **dev.pollus.tech** → register an app →
   copy **`client_id`** + **`client_secret`**, register redirect URI(s), pick scopes
   (`profile`, `verifications`, `doctor_license`, `zkp`).
3. In the verify console → create an **App** → copy the **verify API key** (server-side)
   and **top up balance** (the checks run on verify, so they're billed by verify).

### 3b. Runtime — login then verify
```
End user           Dev app (their backend + site)        IdP                  verify.pollus.tech
   | "Login with Valyd"      |                            |                          |
   |------------------------>| TPSSO authorize ---------->| login (face) / silent    |
   |<------ code ------------|<-------- code -------------|                          |
   |                         | POST /tpsso/token -------->| access_token + pollus_id |
   |                         |<-- token -------------------|                          |
   |   (dev session set)     |                            |                          |
   |                         |                            |                          |
   |  "Verify my license"    |                            |                          |
   |------------------------>| POST /api/v2/session  (X-API-Key + valyd access_token)|
   |                         |---------------------------------------------------->  |
   |                         |                            |  GET /tpsso/userinfo <----|  (validate token)
   |                         |                            |-- pollus_id ------------> |  (bind to session)
   |                         |<------------- { url } ----------------------------     |
   |  open url (popup/redirect)                           |                          |
   |--------------------------------------------------------------------------------->|  capture + run checks
   |                         |                            |  record result <----------|  (write-back to IdP)
   |<------- result / redirect back ------------------------------------------------- |  + keep verify copy
   |                         |<-- webhook + GET /session/{id}/decision --------------- |
```

Steps in words:
1. **Login** (dev ↔ IdP, TPSSO): user clicks "Login with Valyd" on the dev's site → IdP
   authenticates → dev's backend exchanges the code for the **access token + `pollus_id`**
   and sets its own session. (IdP shows the face/login screen only if the user has no Valyd
   session; otherwise it's silent SSO. First-time-vs-returning is invisible to the dev.)
2. **Start a check**: user clicks "Verify my license / Do KYC". The dev's **backend** calls
   `POST /api/v2/session` with `X-API-Key`, the chosen `workflow_id`, the user's
   **`valyd_access_token`**, `vendor_data`, and a `redirect_url`.
3. **verify validates + binds**: verify calls `GET /tpsso/userinfo` with that token →
   confirms login, reads `pollus_id`, binds it to the session, returns `{ url }`.
4. **Capture**: the dev opens `url` (popup or redirect). verify runs the checks (ID/OCR,
   liveness, face match, age, license registry, location) on its engines, charging balance
   per check.
5. **Write-back + copy**: on a pass, verify calls the **IdP internal endpoints** to record
   the result against the `pollus_id` (e.g. license verified + expiry, `id_verified`), and
   stores its own copy (cache + audit).
6. **Return**: verify redirects the user back to `redirect_url` and sends the dev a signed
   **webhook**; the dev can also pull `GET /api/v2/session/{id}/decision`.
7. **Reuse**: next time the dev (or the IdP) already has the verified license/KYC on the
   user's Valyd identity — no re-capture needed unless a re-check policy says so.

### 3c. Endpoints involved
**On the IdP (login + validate + write-back):**
| Purpose | Endpoint | Auth |
|---------|----------|------|
| Login authorize (dev's browser) | `GET /api/auth/tpsso/authorize` | IdP session |
| Token exchange (dev backend) | `POST /api/auth/tpsso/token` | client_secret |
| **Validate token / get pollus_id (verify backend)** | `GET /api/auth/tpsso/userinfo` | `Bearer <user token>` |
| Read live licenses/verifications (dev backend) | `GET /api/auth/tpsso/licenses`, `/verifications` | `Bearer` (`verifications`) |
| **Write-back result (verify backend)** | internal endpoints (`record license`, `set user verified`) | `X-Internal-Auth` shared secret |

**On verify (the checks):**
- `POST /api/v2/session` (now also accepts `valyd_access_token`) → `{ url }`
- Hosted capture flow → `GET /api/v2/session/{id}/decision`, webhooks
- Standalone endpoints unchanged.

---

## 4. Verify Fresh Every Time — unchanged

- **Hosted**: `POST /api/v2/session {workflow_id, redirect_url, callback, vendor_data}` (no
  Valyd token) → redirect to `url` → result via webhook + decision API.
- **Standalone**: `/api/v2/{id-verification|liveness|face-match|age-verification|credential-verification|kyc-credential|location}` server-to-server.
- Billing + multi-destination webhooks apply.

---

## 5. What we change on verify.pollus.tech

### A. Session can be bound to a Valyd identity
- `POST /api/v2/session` accepts an optional **`valyd_access_token`** (and/or `pollus_id`).
- `SessionService` / `SessionController`: when a token is present, call
  `IdpClient::userinfo($token)` to validate and extract `pollus_id`; store `pollus_id` on
  the session. Reject (or fall back to "log in first") if invalid/expired.
- This **replaces the old OIDC `/verify/valyd-callback` login bounce** with a
  dev-supplied-token model (decision #3).

### B. Write-back to the IdP + keep a verify copy
- On a passing Managed check, call the IdP internal endpoints to record the result against
  the `pollus_id` (reinstate/keep `IdpClient::recordLicense`, `setUserVerified`, etc.).
- **Keep a verify-side copy** (decision #4): retain a lightweight record (reuse the existing
  `reusable_identities` table or a slimmer equivalent) for caching/reuse + audit. Source of
  truth stays the IdP.

### C. Wizard redesign (frontend)
Port `verify.pollus.tech-new-flow/src/valyd-setup-wizard.html` into the console
(`WorkflowWizard.tsx`, route `workflows/new`): dark/teal styling, 3-step stepper
(Connect → Configure → Integrate), side "Your setup" panel, 3 product cards
(**Managed** recommended / **Verify Fresh** / **Self-Managed** disabled).
- `sso` branch → **Checks (scopes)** → **Recipe** with the **Login-with-Valyd (TPSSO)**
  snippet + **"Register at dev.pollus.tech →"** deep link + the verify-session-with-token
  snippet for the verification step. (May still create a verify **workflow** so the dev has
  a `workflow_id` for the check step.)
- `verify` branch → **Mode** → **Checks** → **Recheck** (if credential) → **Storage** (if
  standalone) → creates a workflow (as today) + recipe.

### D. End-user hosted flow
- Keep the capture phases (intro/capture/location/credential/processing/result).
- Replace the `valyd`/`reuse` OIDC phases: a Managed session arrives **already bound** to a
  `pollus_id` (from the dev-passed token), so verify goes straight to the check. If the
  session has no valid Valyd identity, show **"Log in with Valyd first"** (Open item #1).
- Remove `App.tsx` `ValydCallbackRoute` + `/verify/valyd-callback`, `lib/pkce.ts`, and the
  `continueWithValyd` OIDC redirect (no longer the hand-off mechanism). Keep developer
  **console** login intact (separate concern).

### E. IdP client
- `IdpClient`: ensure a `userinfo($token)` method (validate + read `pollus_id`); keep the
  internal write-back methods; drop `exchangeOidcCode` + OIDC config (end-user OIDC login is
  replaced by the dev-passed token).

### F. On-demand read API (optional, keep)
- `GET /api/v2/identity?vendor_data=|pollus_id=` can stay, now reading the **verify copy**
  (or proxying the IdP) for back-office reuse. `DELETE` revokes the copy.

### G. Docs rewrite
- `INTEGRATION.md`, `FLOW.md`, `TEST_APP_PROMPT.md`, `UPDATE.md` → the two-product model:
  Managed (TPSSO login + verify-runs-checks + token hand-off + IdP write-back) vs Verify
  Fresh. Include §2's token-validation explanation and §3b's sequence.

---

## 6. File-level change list (proposed)

**Backend**
- `Http/Controllers/SessionController.php` + `Services/SessionService.php` — accept
  `valyd_access_token`, validate via IdP, bind `pollus_id`.
- `Services/IdpClient.php` — `userinfo($token)`; keep internal write-back; drop `exchangeOidcCode`/OIDC config.
- `Http/Controllers/HostedController.php` — remove `valydCallback`/`reuseFace`/OIDC reuse; a bound session goes straight to capture.
- Write-back hook in the finalize path (record license / set verified to IdP) + keep verify copy (`ReusableIdentity*` retained/slimmed).
- `Console/WorkflowController.php`, `Models/VerificationWorkflow.php` — product settings for Managed vs Verify.

**Frontend**
- `dashboard/pages/WorkflowWizard.tsx` (+ `WorkflowNew.tsx`/route) — new 3-product wizard + recipes + dev.pollus.tech deep link.
- `components/HostedFlow.tsx` — drop `valyd`/`reuse` phases; handle pre-bound session + "log in first" state.
- `App.tsx` — remove `ValydCallbackRoute` + `/verify/valyd-callback`.
- `lib/api.ts` — `createSession` accepts token; drop OIDC reuse helpers; `lib/pkce.ts` removed; keep console-login bits.

**Docs** — rewrite the four markdown files.

---

## 7. Open items (decide during build)

1. **Missing/expired token UX**: when verify gets no valid Valyd token, do we (a) just show
   "return to the app and log in", or (b) let verify itself start a Login-with-Valyd bounce
   (needs verify to register its own OAuth client)? Default: (a); add (b) later if wanted.
2. **Token transport**: pass `valyd_access_token` only in the server-to-server
   `POST /api/v2/session` body — never in the popup URL. Confirm the dev's token has the
   scopes verify needs to validate (`profile` at minimum).
3. **pollus_id → IdP user mapping** for write-back: confirm the internal endpoints key by
   `pollus_id`/`anon_id` so verify can attach results.
4. **Billing**: the checks run on verify, so verify's prepaid balance covers them (good —
   no IdP metering bridge needed for the Managed *checks*; the IdP only stores results).
5. **dev.pollus.tech deep link**: confirm the exact registration URL/route.
6. **Workflow for the check step**: decide whether Managed still creates a verify
   `workflow_id` (recommended, so the check step is configured) or uses a default.

---

## 8. Suggested build order
1. Session-binds-to-Valyd-token: `POST /api/v2/session` accepts + validates `valyd_access_token` → `pollus_id`.
2. Write-back to IdP on pass + keep verify copy.
3. Hosted flow: pre-bound session goes straight to capture; "log in first" fallback.
4. Wizard redesign (3 products) + recipes + dev.pollus.tech deep link.
5. Remove the dead OIDC end-user login path (callback route, pkce, exchangeOidcCode).
6. Rewrite the four docs; build + tsc clean; smoke-test Managed + Verify Fresh end-to-end.
