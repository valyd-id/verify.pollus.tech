# Valyd Verify (`verify.valyd.net`)

Didit-style standalone identity-verification product. Exposes two integration
modes over the same verification engines (ID OCR, liveness, doc-to-selfie face
match, ZK age proofs, professional-license verification):

- **Hosted** — create a session, redirect the end user to a hosted URL, receive
  the result via signed webhook + decision API.
- **Standalone** — call direct, synchronous per-capability endpoints and build
  your own UI.

The end user does **not** need a Valyd account; the selfie is matched 1:1
against the photo on the uploaded ID.

## Architecture

`verify` is a thin orchestration layer. The actual ML runs on existing Valyd
microservices, which it calls as a client:

| Capability | Engine |
|---|---|
| ID OCR + authenticity | `faceonlive-ocr.pollus.us` (`App\Services\FaceOnLive\IdOcrService`) |
| Liveness / feature / similarity | `faceonlive-face.pollus.us` (`App\Services\FaceOnLive\FaceService`) |
| Age bands (ZKP) | ZK verify service (`App\Services\ZkVerifyService`) |
| Professional license | `vc.pollus.tech` bulk verify (`App\Services\CredentialClient`) |

Check runners in `App\Services\Checks\*` wrap each engine and produce a
normalised `CheckResult`. `DecisionService` aggregates a session's checks into a
status; `SessionService` owns the lifecycle and webhook dispatch.

## Data model

`verification_projects` (API client + webhook secret) → `verification_workflows`
(reusable feature bundle) → `verification_sessions` → `verification_checks` /
`verification_documents`.

## Setup

```bash
cp .env.example .env          # set DB_* + engine URLs + VC_API_KEY
php artisan key:generate
php artisan migrate
php artisan verify:create-project "Acme KYC" --webhook=https://acme.test/webhooks/valyd
# prints api_key + webhook_signing_secret (shown once)
```

Session expiry is driven by the scheduler (`verify:expire-stale`, every minute);
run `php artisan schedule:work` (or a cron entry) in deployment.

## Auth

- **v2 API** (`/api/v2/*`): `X-API-Key: <project key>` (or `Authorization: Bearer`).
- **Hosted page** (`/api/hosted/{token}/*`): the per-session `session_token` in the path.

All responses use `{ "success": bool, "data": ..., "error": { code, message } }`.

## Hosted flow

```bash
# 1. Create a workflow (once)
curl -X POST $BASE/api/v2/workflows -H "X-API-Key: $KEY" -H 'Content-Type: application/json' \
  -d '{"name":"Full KYC","features":["id_verification","liveness","face_match"]}'

# 2. Create a session
curl -X POST $BASE/api/v2/session -H "X-API-Key: $KEY" -H 'Content-Type: application/json' \
  -d '{"workflow_id":"<workflow uuid>","vendor_data":"user-123"}'
# -> { session_id, url, session_token, expires_at }

# 3. Redirect the user to `url`. They complete capture on the hosted page.
# 4. Receive a signed webhook at your callback; then:
curl $BASE/api/v2/session/<session_id>/decision -H "X-API-Key: $KEY"
```

### Webhook verification

Header `X-Valyd-Signature = HMAC_SHA256(X-Valyd-Timestamp + "." + rawBody,
webhook_signing_secret)`. Recompute and compare in constant time.

## Standalone flow

```bash
curl -X POST $BASE/api/v2/id-verification -H "X-API-Key: $KEY" \
  -F front_image=@front.jpg -F back_image=@back.jpg

curl -X POST $BASE/api/v2/face-match -H "X-API-Key: $KEY" \
  -F image1=@id_portrait.jpg -F image2=@selfie.jpg     # image2 = selfie

curl -X POST $BASE/api/v2/liveness -H "X-API-Key: $KEY" -F image=@selfie.jpg

curl -X POST $BASE/api/v2/age-verification -H "X-API-Key: $KEY" -H 'Content-Type: application/json' \
  -d '{"dob":"1990-05-01","bands":["is_18_plus","is_21_plus"]}'

curl -X POST $BASE/api/v2/credential-verification -H "X-API-Key: $KEY" -H 'Content-Type: application/json' \
  -d '{"first_name":"Jane","last_name":"Doe","license_type":"nurse","license_state":"CA","license_number":"12345"}'
```

Each standalone call records a `mode=standalone` audit session (no webhook).

## Session statuses

`NOT_STARTED → IN_PROGRESS → (IN_REVIEW) → APPROVED | DECLINED`, plus
`ABANDONED` / `EXPIRED`.
