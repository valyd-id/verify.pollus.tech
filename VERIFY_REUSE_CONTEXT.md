# Verify Once & Reuse — Implementation Context (handoff for Cursor)

This is a self-contained brief to implement (or finish) the **"verify once, then
reuse it"** feature in `verify.pollus.tech`, plus the **reverify-each-time** path.
It captures the architecture decision, the complete flows, what already exists, what
must change, and exact file/function references.

---

## 0. TL;DR of the decision

- **Login‑with‑Valyd (IdP TPSSO) = identity only.** We only rely on the stable
  **`pollus_id`** it returns. We do **NOT** read or store verified data in the IdP.
  Reason: the IdP is moving user data to **end‑to‑end encryption** (structure already
  scaffolded — vault_items, wrapped_ku_recovery, recovery phrases — plaintext for now
  but the server won't be able to share it), so other services can't depend on its DB.
- **verify.pollus.tech is the system of record** for the verified data (profile, DOB,
  age bands, **face embedding**, licenses), stored **per app**: unique
  `(project_id, pollus_id)`.
- Face matching runs entirely on **verify's own** FaceOnLive engine (no IdP call).

### Locked decisions
| Topic | Decision |
|------|----------|
| Reuse scope | **Per app** → unique `(project_id, pollus_id)` |
| Encryption | **Structure now, plaintext for testing** (encrypted casts added but off) |
| Reuse window | **Until revoked** (add `expires_at` column but leave null/unused) |
| Reuse model | **Session + consent** AND an **on‑demand read API** |
| Returned data | **Verified profile + licenses** (name, DOB, age bands, license status/expiry, check statuses) — no raw ID images |

---

## 1. The two products

- **Verify once & reuse** — workflow `settings.reuse = true`. Requires Login‑with‑Valyd
  (to get `pollus_id`). First time → full KYC + store a record; next time → selfie‑only.
- **Reverify each time** — `settings.reuse = false`. No login, full KYC every time,
  nothing stored. (This already works — it's the normal hosted/standalone flow.)

---

## 2. Complete flows

### Dev user (integrator) — one time, in the console
1. Sign in, create an **App** (API key), **top up balance**, add **webhook endpoint(s)**.
2. Create a **workflow** with the wizard → choose product (reuse on/off) + checks +
   recheck policy. Get a `workflow_id`.
3. Put a button on their site ("Verify with Valyd" or any label). On click, open our
   popup against a **session** they created with `workflow_id` (+ their own `vendor_data`).

### End user — FIRST time (reuse workflow)
1. Clicks button → **our popup** opens (verify hosted page).
2. Popup shows **Continue with Valyd** → redirect to IdP login *inside the popup* →
   back with `code`.
3. Verify backend exchanges `code` → **`pollus_id`** (+ access token). Create/find the
   per‑app record `(project_id, pollus_id)`. (May call TPSSO `userinfo` only to
   *prefill* a display name — NOT authoritative.)
4. No prior record → user completes the **full workflow KYC** (ID + selfie + liveness
   + license + …).
5. On success → **populate the record** from our own verification result: `full_name`,
   `dob`, age bands, **face embedding from the selfie**, `licenses`, `verified_at`.
6. Result to integrator via **webhook** + `GET /api/v2/session/{id}/decision`
   (now includes the verified profile + licenses).

### End user — RETURNING (reuse)
1. Button → popup → Continue with Valyd → `code` → `pollus_id`.
2. Verify finds the `(project_id, pollus_id)` record.
3. User takes **just a selfie** → verify extracts its embedding and compares to the
   stored embedding locally (threshold **0.95**).
4. Match → re‑check license per the workflow's recheck policy, refresh the record →
   return verified profile + licenses (webhook + decision). **No ID re‑scan.**
5. No match / (later) expired → fall back to full KYC.

### End user — REVERIFY each time (non‑reuse)
Button → popup → straight into full KYC (no Valyd login, no stored record) → webhook
each time. (Already implemented.)

### How a third party reuses data
1. **Session + consent (primary):** integrator starts a session; returning user logs
   in with Valyd + selfie; we return re‑checked **profile + licenses** via webhook +
   decision API. Always consented + fresh.
2. **On‑demand read API:** integrator can also fetch a previously‑verified user's
   profile + licenses by reference (`vendor_data` or `pollus_id`) **without a new
   session** (back‑office). Scoped to that app's own records only. Auth: project API key.
3. Integrators correlate users with their own **`vendor_data`**, stored on the record.

---

## 3. Current state (what's already built — needs REWIRING)

The reuse feature was first built against the **IdP as the store** (wrong per the new
decision). These pieces exist and must be repointed to verify's own DB:

- `app/Services/IdpClient.php` — methods: `exchangeCode` (✅ keep — gives `pollus_id`),
  `authorizeUrl` (✅ keep), and `verifications` / `faceMatch` / `recordLicense` /
  `setUserVerified` (❌ stop using — these read/write the IdP).
- `app/Http/Controllers/HostedController.php`:
  - `valydAuthorizeUrl(Request)` — ✅ keep (returns TPSSO authorize URL, `state` = session token).
  - `valydCallback(Request)` — 🔧 change: exchange `code` → `pollus_id`; store on
    session; look up **verify's own** record (not IdP `verifications`); set
    `reuse_eligible = (record exists)`.
  - `reuseFace(Request)` — 🔧 change: match selfie **locally** (FaceService) against the
    stored embedding instead of calling the IdP `/internal/face-match`.
  - `maybeWatchCredential(...)` — ✅ keep (credential re‑check engine).
- Hosted routes in `routes/api.php` (inside `prefix('hosted/{token}')`):
  `GET valyd/authorize-url`, `POST valyd/callback`, `POST reuse/face` — ✅ keep paths.
- Sessions already have `pollus_id` + `reused` columns (migration
  `2026_06_19_000002`). `session.metadata['idp']` stash → replace with the verify record.
- Frontend `src/components/HostedFlow.tsx` already has the `valyd` + `reuse` phases and
  `src/lib/api.ts` has `valydAuthorizeUrl` / `valydCallback` / `reuseFace`,
  and `App.tsx` has the `/verify/valyd-callback` popup relay — ✅ keep, behavior unchanged
  client‑side (still: login → callback → selfie reuse or full KYC).
- The IdP endpoint `POST /api/internal/face-match` we added earlier becomes **unused**
  (leave it; harmless/additive). Do not depend on it.

---

## 4. What to build

### 4.1 New table — `reusable_identities`
Migration prefix: next is `2026_06_19_000004_*` (latest existing is `_000003`). DB is
**Postgres** (`verify_valyd`). No pgvector in verify — store the embedding as base64
text or `bytea`.

Columns:
- `id`
- `project_id` (FK → verification_projects, cascade)
- `pollus_id` (string, index)
- **unique(`project_id`, `pollus_id`)**  ← per‑app isolation
- `vendor_data` (string, nullable, index) — integrator's own user id (for on‑demand lookup)
- `full_name` (text, nullable)        ← wrap with encrypted cast later
- `dob` (string/date, nullable)       ← encrypted cast later
- `age_bands` (json, nullable)        ← {is_18_plus: true, …}
- `face_embedding` (longtext/`bytea`, nullable) — base64 of the 2056‑int FaceOnLive
  feature (or JSON array). Encrypted cast later.
- `licenses` (json, nullable)         ← [{license_type, license_state, license_number, status, expire_at}]
- `source_session_id` (uuid, nullable) — the session that created/last refreshed it
- `verified_at` (timestamp, nullable)
- `expires_at` (timestamp, nullable)  ← structure for the reuse window; leave null (until‑revoked)
- `revoked_at` (timestamp, nullable)
- timestamps

Model `app/Models/ReusableIdentity.php`: casts `age_bands`/`licenses` → array; add
**encrypted casts** definitions but commented/flagged off for now (structure‑only).
Relationship `project()`.

> Encryption note: true client‑side E2E is impossible here because verify must decrypt
> the embedding to match. The "encryption" here is **at‑rest** (Laravel `Crypt` /
> `encrypted` casts using `APP_KEY`). Add the cast wiring now, keep it OFF for testing.

### 4.2 Service — `app/Services/ReusableIdentityService.php`
- `find(project_id, pollus_id): ?ReusableIdentity`
- `captureFromSession(VerificationSession $session): void` — called when a **reuse
  workflow** session with a `pollus_id` reaches **APPROVED** the first time:
  - read OCR fields from the `id_verification` check (`full_name`, `dob`) — see
    `IdVerificationRunner` data shape below;
  - read the **selfie** document bytes (`HostedController::docBytes($session,'selfie')`)
    and extract the embedding via `FaceService::getFeatureInfo()` → store the `feature` array;
  - gather age bands (from `age` check if present) + licenses (from credential check
    data / the credential inputs);
  - upsert `(project_id, pollus_id)` with `vendor_data`, `verified_at = now`, `source_session_id`.
- `matchSelfie(ReusableIdentity $rec, string $selfieBytesOrBase64): array{match:bool, score:float}` —
  `getFeatureInfo` on the selfie → `getFaceSimilarity(stored, fresh)` → compare to
  `BiometricUtils::TARGET_SIM` (0.95).
- `present(ReusableIdentity $rec): array` — the integrator‑facing payload
  (profile + licenses), reused by the decision API + on‑demand read API.

### 4.3 HostedController changes
- `valydCallback`: after `exchangeCode`, set `session.pollus_id`; `rec =
  ReusableIdentityService::find(project_id, pollus_id)`; return
  `{ pollus_id, reuse_eligible: rec !== null }` (drop the IdP `verifications` read +
  the `metadata['idp']` stash).
- `reuseFace`: require `session.pollus_id`; load `rec`; if none → error/fall back;
  selfie bytes → `matchSelfie`; on match → record identity checks **passed** (id,
  liveness, face_match) + credential from `rec.licenses` (apply recheck policy);
  refresh `rec`; finalize. Charge `face_match` via `BillingService::runCharged`
  (existing pattern). On no match → record `face_match` failed.
- First‑time capture hook: when a reuse session APPROVES via normal KYC, call
  `ReusableIdentityService::captureFromSession($session)`. Cleanest single hook:
  in the runKyc/credential completion path in `HostedController` (guard:
  `session.pollus_id && (session.settings['reuse'] ?? false) && status APPROVED && !reused`).

### 4.4 Decision API + webhook payload
- Extend `GET /api/v2/session/{id}/decision` (and the result the integrator sees) to
  include the **verified profile + licenses** for sessions the integrator owns
  (pull from the `reusable_identities` record when `pollus_id` is set, else from the
  session's own checks). See `SessionController@decision`.
- Webhook payload may include a compact profile summary; full data via the decision API.

### 4.5 On‑demand read API (new)
- `GET /api/v2/identity?vendor_data=...` or `GET /api/v2/identity/{pollus_id}` (project
  API‑key auth). Returns `present(rec)` for the **caller's own project** only
  (`where project_id = caller`). 404 if none / revoked / expired.
- Add a revoke endpoint: `DELETE /api/v2/identity/{pollus_id}` → set `revoked_at`.
- Routes go in the `v2` group (project.key middleware) in `routes/api.php`.
- These are read/maintenance ops — decide billing (probably free or a tiny fee).

---

## 5. Key existing code to reuse (verified by exploration)

### Face engine — `app/Services/FaceOnLive/FaceService.php`
```php
// Extract a 2056-int face embedding from an image (file path | bytes | base64).
public function getFeatureInfo($image): array
// returns ['bbox'=>..,'liveness'=>int,'feature'=>int[2056],'feature_size'=>int]

// Compare two embeddings → similarity 0..1
public function getFaceSimilarity(array $feat1, array $feat2, int $featureSize = 2056): float
```
`app/Services/.../BiometricUtils.php`: `FEATURE_SIZE = 2056`, `TARGET_SIM = 0.95`,
`normalizeVector()`, `isEmptyOrZeroVector()`. Verify already does doc↔selfie matching
in `app/Services/Checks/FaceMatchRunner.php`.

### ID OCR data — `app/Services/Checks/IdVerificationRunner.php`
`CheckResult->data` = `['fields'=>['full_name','date_of_birth',...], 'portrait'=>base64|null, 'ocr_data'=>..., 'authenticity'=>..., 'dob'=>'YYYY-MM-DD'|null]`.

### Identity from IdP — `app/Services/IdpClient.php`
`exchangeCode($code)` → `['ok'=>bool,'pollus_id'=>string,'access_token'=>string,'user'=>array]`.
`authorizeUrl($state)` → TPSSO authorize URL. (TPSSO `token`/`userinfo` currently
return plaintext `pollus_id` + name/dob/licenses, but **only `pollus_id` is reliable
long‑term** — don't depend on the rest.)

### Sessions / checks / billing
- `app/Services/SessionService.php`: `recordCheck`, `reevaluate`, `finalize`,
  `dispatchWebhook` (fans out to multiple `webhook_endpoints`, hosted‑only).
- `app/Services/BillingService.php`: `runCharged($owner, $feature, fn, $ref)` (charge
  before, auto‑refund on throw / JsonResponse), `assertCanAfford`.
- `app/Http/Controllers/HostedController.php`: `docBytes($session,$type)`,
  `session($request)`, the `run/{check}` pipeline, `maybeWatchCredential`.
- `app/Models/VerificationSession.php`: has `pollus_id`, `reused`, `metadata`,
  `settings` (`reuse`, `recheck`, `recheck_interval`, `storage`, `product`, `mode`).
- Credential re‑check engine: `credential_watches` table + `App\Console\Commands\RecheckCredentials`
  (scheduled) + `App\Jobs\SendCredentialChangedWebhookJob`.

### DB / config
- Postgres, DB `verify_valyd`. `config/services.php` → `idp` block
  (`base_url`, `internal_auth_key`, `tpsso_client_id/secret`, `tpsso_redirect_uri`,
  `tpsso_scopes`). `APP_KEY` present (for encrypted casts later).

### Frontend (already wired — no change needed for the rewire)
- `src/components/HostedFlow.tsx`: `valyd` phase (Continue with Valyd → popup →
  postMessage `code`) + `reuse` phase (selfie via `CameraCapture` → `reuseFace`).
- `src/lib/api.ts`: `valydAuthorizeUrl`, `valydCallback`, `reuseFace`, `getState`(returns `reuse`).
- `src/App.tsx`: `/verify/valyd-callback` relays the TPSSO `code` to the opener.
- `valyd-verify-js` modal/popup SDK opens the hosted session.

---

## 6. Implementation checklist
1. Migration `reusable_identities` (+ unique `(project_id, pollus_id)`).
2. `ReusableIdentity` model (+ encrypted‑cast wiring, off for now).
3. `ReusableIdentityService` (find / captureFromSession / matchSelfie / present).
4. Rewire `HostedController::valydCallback` + `reuseFace` to verify's store + local
   face match; add the first‑time capture hook on APPROVED.
5. Extend `SessionController@decision` (+ webhook) to include profile + licenses.
6. On‑demand read + revoke API in the `v2` group; trim `IdpClient` usage to
   `exchangeCode`/`authorizeUrl`.
7. Frontend unchanged (verify the existing reuse phases still work end‑to‑end).

## 7. Test plan
- Postgres reachable; run migration.
- **First time:** reuse workflow → Valyd login (mock `code`) → full KYC (location/age
  for no‑engine testing; ID/selfie need live FaceOnLive) → record created with
  embedding + fields.
- **Reuse:** same `pollus_id` → selfie → local match ≥ 0.95 → APPROVED, no ID scan.
- **On‑demand read:** `GET /api/v2/identity?vendor_data=…` returns profile + licenses;
  only for the owning project; 404 after revoke.
- **Decision API/webhook** include profile + licenses.
- **Billing:** reuse charges `face_match`; refunds on our‑side error.
- Build frontend (`npx tsc -b && npm run build`).

## 8. Go‑live config (verify `.env`)
`IDP_BASE_URL`, `IDP_INTERNAL_AUTH_KEY`, `IDP_TPSSO_CLIENT_ID`,
`IDP_TPSSO_CLIENT_SECRET`, `IDP_TPSSO_REDIRECT_URI=https://verify.pollus.tech/verify/valyd-callback`,
`IDP_TPSSO_SCOPES="profile verifications zkp"`. Register the TPSSO client at the dev
portal. (We only consume `pollus_id` from it.)

---

## 9. Wider context (already shipped this cycle, for reference)
Dark/teal console theme; full‑page **workflow wizard** (product→mode→checks→recheck→
storage, no recipe step, creates the workflow); **prepaid billing** (charge/refund/402
+ Billing page + navbar pill); **license re‑check engine**; **multiple webhook
destinations** (per‑endpoint secret + event filter + fan‑out, hosted‑only); **Location
(GPS) check**; Settings trimmed to Account. See `FLOW.md` and `UPDATE.md`.
