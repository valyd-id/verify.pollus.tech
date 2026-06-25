# Prompt — build a Valyd Verify test app (Managed Identity by Valyd)

Copy everything below the line into Cursor / your AI builder. It builds a small
**Next.js (React + TypeScript)** third-party app that integrates the **Managed Identity by
Valyd** product: the user **logs in with Valyd** (TPSSO/OAuth2), then a **"Verify my
license"** button runs a verification on verify.pollus.tech that carries the user's Valyd
token. The OAuth `client_secret`, the verify `API key`, and the user's **Valyd access token**
are all **server-side only** — they never reach the browser. Use the unified
`valyd-verify-sdk` `Valyd` client.

---

You are building a **test integrator app** for **Valyd Verify** using the **Managed Identity
by Valyd** product. Use **Next.js (App Router) + TypeScript**. Three secrets — the OAuth
`client_secret`, the verify `API key`, and the user's **Valyd access token** — **must never
reach the browser**; all of them live in server-side route handlers / the server session.
Follow these steps exactly.

## 1. Scaffold
- `npx create-next-app@latest valyd-test --ts --app --eslint --no-tailwind`.
- `npm i valyd-verify-sdk`.
- Use a simple server-side session (e.g. `iron-session` or an HTTP-only signed cookie) to
  store the logged-in user's **Valyd `access_token`** and **`pollus_id`**. Never expose these
  to client components.

## 2. Environment (`.env.local`) — I will fill these in
```
# Login with Valyd (OAuth client registered at dev.pollus.tech) — server-side only
VALYD_CLIENT_ID=
VALYD_CLIENT_SECRET=
# Verify (App created in the verify.pollus.tech console) — server-side only
VALYD_API_KEY=
VALYD_WORKFLOW_ID=        # a workflow with product = "Managed Identity by Valyd"
VALYD_WEBHOOK_SECRET=     # (optional) signing secret of a webhook destination
# This app
APP_URL=http://localhost:3000
```
> None of these may be prefixed with `NEXT_PUBLIC_`. The browser never sees them.

## 3. SDK helper (`lib/valyd.ts`) — server-only
```ts
import { Valyd } from "valyd-verify-sdk";
export const valyd = new Valyd({
  clientId:      process.env.VALYD_CLIENT_ID!,
  clientSecret:  process.env.VALYD_CLIENT_SECRET!,
  apiKey:        process.env.VALYD_API_KEY!,
  webhookSecret: process.env.VALYD_WEBHOOK_SECRET,
});
```

## 4. Login with Valyd — start  (`app/api/auth/login/route.ts`)
- `GET` handler. Build the IdP authorize URL and redirect the browser to it:
  ```ts
  import { valyd } from "@/lib/valyd";
  export async function GET() {
    const url = valyd.auth.getAuthorizationUrl({
      scope: ["profile", "verifications", "doctor_license"],
      redirectUri: `${process.env.APP_URL}/api/auth/callback`,
    });
    return Response.redirect(url);
  }
  ```

## 5. Login with Valyd — callback  (`app/api/auth/callback/route.ts`)
- `GET` handler. The IdP redirects here with `?code=...`. Exchange it **server-side** and
  store the token + `pollus_id` in the session:
  ```ts
  import { valyd } from "@/lib/valyd";
  export async function GET(req: Request) {
    const code = new URL(req.url).searchParams.get("code")!;
    const { accessToken, user } = await valyd.auth.exchangeCode(code);
    // SAVE in the server session (HTTP-only):
    //   session.valydAccessToken = accessToken
    //   session.pollusId = user?.pollus_id
    //   session.idVerified = user?.id_verified
    return Response.redirect(`${process.env.APP_URL}/`);
  }
  ```

## 6. Start a verification — server route  (`app/api/verify/session/route.ts`)
- `POST` handler. Read the **Valyd access token + `pollus_id`** from the server session
  (NOT from the request body). If there's no token, return `401` so the UI prompts login.
  Create a verify session **carrying the token**:
  ```ts
  import { valyd } from "@/lib/valyd";
  export async function POST() {
    const accessToken = /* session.valydAccessToken */;
    const pollusId    = /* session.pollusId */;
    if (!accessToken) return new Response("login required", { status: 401 });
    try {
      const session = await valyd.verify.sessions.create({
        workflowId: process.env.VALYD_WORKFLOW_ID!,
        valydAccessToken: accessToken,            // ← server-to-server only
        vendorData: pollusId,
        redirectUrl: `${process.env.APP_URL}/verify/done`,
        // callback: "https://<tunnel>/api/webhook",  // optional, see step 10
      });
      return Response.json({ url: session.url, sessionId: session.sessionId });
    } catch (e: any) {
      // verify returns 401 valyd_login_required if the token is invalid/expired
      // and 402 insufficient_balance if the account can't cover the workflow
      return new Response(e?.code ?? "error", { status: e?.status ?? 500 });
    }
  }
  ```

## 7. Read the result — server route  (`app/api/verify/result/route.ts`)
- `GET` handler taking `?sessionId=`:
  ```ts
  import { valyd } from "@/lib/valyd";
  export async function GET(req: Request) {
    const sessionId = new URL(req.url).searchParams.get("sessionId")!;
    const decision = await valyd.verify.sessions.decision(sessionId);
    // decision = { status, checks[], pollus_id, identity }
    // identity = { full_name, dob, age_bands, licenses, verified_at } | null
    return Response.json(decision);
  }
  ```

## 8. On-demand reuse lookup — server route  (`app/api/verify/identity/route.ts`)
- `GET` handler. Reuse a returning user's verified identity WITHOUT a new session — look up by
  the `pollus_id` in the session (or by `vendor_data`):
  ```ts
  const res = await fetch(
    `https://verify.pollus.tech/api/v2/identity?pollus_id=${encodeURIComponent(pollusId)}`,
    { headers: { "X-API-Key": process.env.VALYD_API_KEY! } },
  );
  return Response.json(await res.json(), { status: res.status }); // 404 = not verified yet
  ```

## 9. Pages
**Home  (`app/page.tsx`)** — a server component that reads the session:
- If **not logged in**: show a **"Login with Valyd"** link to `/api/auth/login`.
- If **logged in**: show the `pollus_id` / `id_verified` and a **"Verify my license"** button
  (`"use client"`). On click: `POST /api/verify/session` → on `{ url }` do
  `window.location.href = url;` (full-page redirect). If the route returns `401`, prompt the
  user to log in with Valyd first; if `402`, show "top up balance".
- Also a **"Look up my verified identity"** button → `GET /api/verify/identity` → render the
  stored profile + licenses if found (404 = not verified yet). This shows reuse with no new
  verification.

**Return page  (`app/verify/done/page.tsx`)** — `"use client"`:
- Valyd redirects here as `…/verify/done?session_id=...&status=...`.
- Read `session_id`, then `GET /api/verify/result?sessionId=...` and show **status**,
  **checks**, and the verified **identity** (name, DOB, age bands, licenses). Link back home.

## 10. (Optional) Webhook receiver  (`app/api/webhook/route.ts`)
Needs a public URL (`ngrok http 3000`, add it as a Webhook destination in the verify console,
put its secret in `VALYD_WEBHOOK_SECRET`). Verify the signature over the **raw** body:
```ts
import { valyd } from "@/lib/valyd";
export async function POST(req: Request) {
  const raw = await req.text();
  try {
    const event = valyd.verify.webhooks.constructEvent(raw, {
      signature: req.headers.get("X-Valyd-Signature") ?? "",
      timestamp: req.headers.get("X-Valyd-Timestamp") ?? "",
    });
    console.log("valyd event", event); // type, status, session_id, vendor_data, decision
    return new Response("ok");
  } catch {
    return new Response("bad signature", { status: 400 });
  }
}
```

## 11. Run & test the flow
1. Fill `.env.local`, then `npm run dev` → open http://localhost:3000.
2. Click **Login with Valyd** → sign in at the IdP (silent if you already have a Valyd
   session) → you land back home, now logged in (your session holds the access token +
   `pollus_id`).
3. Click **Verify my license** → your server creates a verify session WITH the stored token →
   the page **redirects** to Valyd. **First time** → full workflow; **returning** (same
   `pollus_id`, already verified in this app) → **selfie only**, no ID re-scan → redirect back
   to `/verify/done` → result card shows APPROVED + identity. On approval verify also writes
   the result back to your Valyd identity.
4. Click **Look up my verified identity** → the stored profile + licenses appear with no new
   verification.

## 12. Acceptance criteria
- `client_secret`, `API key`, and the user's **Valyd access token** never appear in client
  bundles, the browser, or any redirect/popup URL.
- Login stores the access token + `pollus_id` in a server-side session.
- A verify session is created **with `valyd_access_token`** from the session; `401
  valyd_login_required` (re-login) and `402 insufficient_balance` (top up) are handled.
- First run = full KYC; second run for the same verified `pollus_id` = selfie-only reuse.
- Result page shows `status` + `identity` from the decision API.
- `/api/verify/identity` returns the stored profile for a verified user, 404 otherwise.

## Notes
- Managed endpoints used: IdP `GET /api/auth/tpsso/authorize` + `POST /api/auth/tpsso/token`
  (via `valyd.auth`), verify `POST /api/v2/session` (with `valyd_access_token`),
  `GET /api/v2/session/{id}/decision`, `GET /api/v2/identity?pollus_id=…` (X-API-Key).
- One-time setup on the Valyd side: register the OAuth client at **dev.pollus.tech** (with the
  `/api/auth/callback` redirect URI + the scopes above), and create a **Managed** workflow in
  the verify console for `VALYD_WORKFLOW_ID`.
- **Full-page redirect, not a popup/iframe** for the verify step — camera/geolocation are
  blocked in a cross-origin modal (a popup helper, `valyd-verify-js`, exists for simple flows).
