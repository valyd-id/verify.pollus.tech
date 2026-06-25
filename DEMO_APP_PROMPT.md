# Prompt — build a Valyd Verify demo integrator app

Copy everything below the line into Cursor / your AI builder. It builds a **minimal but
fully working** demo (no design needed): a **Login with Valyd** button, then **three
buttons** — *Managed by Valyd*, *Verify fresh every time*, *Standalone APIs* — plus a
panel that shows the **data received from the webhook**. All secrets stay server-side.

---

You are building a **demo integrator app for Valyd Verify**. Stack: **Next.js (App
Router) + TypeScript**. No styling needed — plain HTML is fine; it just has to run and be
correct. Use the unified SDK package **`valyd-verify-sdk`** (the `Valyd` client:
`valyd.auth` = Login with Valyd / OAuth2, `valyd.verify` = checks). **Never expose any
secret or token to the browser** — every Valyd call happens in a server route.

## 0. Concepts (don't get this wrong)
- **Login with Valyd** is OAuth2 between THIS app's backend and the Valyd IdP. After login
  the backend holds the user's **Valyd access token** + **`pollus_id`**.
- **Managed by Valyd** = a hosted verification that REQUIRES that logged-in token: the
  backend creates a verify session passing **`valydAccessToken`**; the result is reusable.
- **Verify fresh every time** = a hosted verification with **no token** — full KYC each
  time, nothing kept.
- **Standalone APIs** = server-to-server checks (you send images), synchronous JSON result.

## 1. Scaffold
- `npx create-next-app@latest valyd-demo --ts --app --eslint --no-tailwind`
- `npm i valyd-verify-sdk` (if it isn't published yet, install from its local path, e.g.
  `npm i /var/www/pollus_main_servers/valyd-verify-sdk`).

## 2. Environment (`.env.local`) — I will fill these in
```
# Login with Valyd (from dev.pollus.tech — your OAuth client)
VALYD_CLIENT_ID=
VALYD_CLIENT_SECRET=
# Valyd Verify (from the verify.pollus.tech console — your App)
VALYD_API_KEY=                  # the App's API key (sent as X-API-Key); aka your "app id" key
VALYD_MANAGED_WORKFLOW_ID=      # a workflow created with product = Managed by Valyd
VALYD_FRESH_WORKFLOW_ID=        # a workflow created with product = Verify fresh every time
VALYD_WEBHOOK_SECRET=           # signing secret of a webhook destination
# URLs
VALYD_VERIFY_URL=https://verify.pollus.tech
VALYD_IDP_URL=https://idp.valyd.id
APP_URL=http://localhost:3000
```
> All of these are **server-only** — do NOT prefix with `NEXT_PUBLIC_`.
> **Managed and Fresh are different workflows** (Managed requires login; Fresh must not),
> so there are two workflow ids. If you only have one workflow, create the second in the
> verify console.

## 3. The Valyd client  (`lib/valyd.ts`)
```ts
import { Valyd } from "valyd-verify-sdk";

export const valyd = new Valyd({
  // Login with Valyd
  clientId: process.env.VALYD_CLIENT_ID,
  clientSecret: process.env.VALYD_CLIENT_SECRET,
  idpBaseUrl: process.env.VALYD_IDP_URL,
  redirectUri: `${process.env.APP_URL}/api/auth/callback`,
  // Verify
  apiKey: process.env.VALYD_API_KEY!,
  baseUrl: process.env.VALYD_VERIFY_URL,
  webhookSecret: process.env.VALYD_WEBHOOK_SECRET,
});
```

## 4. A tiny server session  (`lib/session.ts`)
Keep the logged-in user's Valyd token server-side. For a demo, an in-memory map keyed by an
httpOnly cookie is fine (resets on restart — that's OK).
```ts
import { cookies } from "next/headers";
type Session = { accessToken: string; refreshToken?: string; pollusId?: string; name?: string };
const store = new Map<string, Session>();
const COOKIE = "valyd_demo_sid";

export async function getSession(): Promise<Session | null> {
  const sid = (await cookies()).get(COOKIE)?.value;
  return sid ? store.get(sid) ?? null : null;
}
export async function setSession(s: Session) {
  const sid = crypto.randomUUID();
  store.set(sid, s);
  (await cookies()).set(COOKIE, sid, { httpOnly: true, sameSite: "lax", path: "/" });
}
export async function clearSession() {
  const c = await cookies(); const sid = c.get(COOKIE)?.value;
  if (sid) store.delete(sid); c.delete(COOKIE);
}
```

## 5. Login with Valyd  (two routes)
`app/api/auth/login/route.ts` — start login:
```ts
import { valyd } from "@/lib/valyd";
export async function GET() {
  const url = valyd.auth.getAuthorizationUrl({ scope: ["profile", "verifications"] });
  return Response.redirect(url);
}
```
`app/api/auth/callback/route.ts` — finish login:
```ts
import { valyd } from "@/lib/valyd";
import { setSession } from "@/lib/session";
export async function GET(req: Request) {
  const code = new URL(req.url).searchParams.get("code");
  if (!code) return new Response("missing code", { status: 400 });
  const { accessToken, refreshToken, user } = await valyd.auth.exchangeCode(code);
  await setSession({ accessToken, refreshToken, pollusId: user?.pollus_id, name: user?.name });
  return Response.redirect(`${process.env.APP_URL}/`);
}
```
`app/api/auth/logout/route.ts` → `clearSession()` then redirect to `/`.

## 6. Button 1 — Managed by Valyd  (`app/api/verify/managed/route.ts`)
Requires the logged-in token; verify validates it and binds the identity.
```ts
import { valyd } from "@/lib/valyd";
import { getSession } from "@/lib/session";
export async function POST() {
  const s = await getSession();
  if (!s?.accessToken) return Response.json({ error: "login_required" }, { status: 401 });
  try {
    const session = await valyd.verify.sessions.create({
      workflowId: process.env.VALYD_MANAGED_WORKFLOW_ID!,
      vendorData: s.pollusId,
      valydAccessToken: s.accessToken,                       // ← the Managed hand-off
      redirectUrl: `${process.env.APP_URL}/verify/done`,
      callback: `${process.env.APP_URL}/api/webhook`,        // optional (needs a public URL)
    });
    return Response.json({ url: session.url });
  } catch (e: any) {
    // verify returns 401 valyd_login_required if the token is bad/expired, 402 if no balance
    return Response.json({ error: e?.code ?? "error", message: e?.message }, { status: e?.status ?? 500 });
  }
}
```

## 7. Button 2 — Verify fresh every time  (`app/api/verify/fresh/route.ts`)
No token. Anyone can verify; nothing is kept.
```ts
import { valyd } from "@/lib/valyd";
export async function POST() {
  const session = await valyd.verify.sessions.create({
    workflowId: process.env.VALYD_FRESH_WORKFLOW_ID!,
    redirectUrl: `${process.env.APP_URL}/verify/done`,
    callback: `${process.env.APP_URL}/api/webhook`,
  });
  return Response.json({ url: session.url });
}
```

## 8. Button 3 — Standalone APIs  (`app/api/verify/standalone/route.ts`)
Server-to-server; synchronous result. Accept an `id_front` + `selfie` (and optional license
fields) as multipart form data, forward to the SDK, return the JSON.
```ts
import { valyd } from "@/lib/valyd";
export async function POST(req: Request) {
  const form = await req.formData();
  const buf = async (k: string) => Buffer.from(await (form.get(k) as File).arrayBuffer());
  const result = await valyd.verify.standalone.kycCredential({
    frontImage: await buf("id_front"),
    selfie: await buf("selfie"),
    licenseState: (form.get("license_state") as string) || undefined,
    licenseNumber: (form.get("license_number") as string) || undefined,
    providerCode: (form.get("provider_code") as string) || undefined,
  });
  return Response.json(result); // { status, checks[] }
}
```
> If you only want ID, call `valyd.verify.standalone.idVerification({ frontImage })` instead.
> Check the SDK types for the exact param names of each standalone method.

## 9. Return page  (`app/verify/done/page.tsx`, "use client")
Hosted flows redirect here as `…/verify/done?session_id=…&status=…`. Read the authoritative
result from your server, then show it:
`app/api/verify/result/route.ts`:
```ts
import { valyd } from "@/lib/valyd";
export async function GET(req: Request) {
  const id = new URL(req.url).searchParams.get("session_id")!;
  const decision = await valyd.verify.sessions.decision(id); // { status, checks, identity }
  return Response.json(decision);
}
```
The page reads `session_id` from the query, fetches `/api/verify/result?session_id=…`, and
renders `status`, `checks[]`, and `identity` (name / DOB / licenses).

## 10. Webhook receiver + live log  (show the data you receive)
`app/api/webhook/route.ts` — verify the signature over the **raw** body, store the event:
```ts
import { valyd } from "@/lib/valyd";
import { pushEvent } from "@/lib/events";
export async function POST(req: Request) {
  const raw = await req.text();
  const headers = Object.fromEntries(req.headers); // X-Valyd-Signature / Timestamp / Event-Id
  try {
    const event = valyd.verify.webhooks.constructEvent(raw, headers); // throws on bad signature
    pushEvent(event);
    return new Response("ok");
  } catch {
    return new Response("bad signature", { status: 400 });
  }
}
```
`lib/events.ts` — keep the last ~20 events in memory:
```ts
type Ev = any; const buf: Ev[] = [];
export function pushEvent(e: Ev) { buf.unshift({ at: new Date().toISOString(), ...e }); buf.length = Math.min(buf.length, 20); }
export function recentEvents() { return buf; }
```
`app/api/webhook/events/route.ts` → `Response.json(recentEvents())`.

## 11. Home page  (`app/page.tsx`, "use client")
- Call a small `/api/me` route (returns `{ loggedIn, name, pollusId }` from the session).
- **Not logged in** → show one button: **“Login with Valyd”** → `window.location.href = "/api/auth/login"`.
- **Logged in** → show `name / pollus_id`, a **Logout** link, and **three buttons**:
  1. **Managed by Valyd** → `POST /api/verify/managed` → `window.location.href = res.url`
     (if `401 login_required`, send them back to `/api/auth/login`; if `402`, show “top up balance”).
  2. **Verify fresh every time** → `POST /api/verify/fresh` → redirect to `res.url`.
  3. **Standalone APIs** → a tiny form (file inputs `id_front`, `selfie`, optional
     `license_state` / `license_number`) → `POST /api/verify/standalone` (multipart) →
     render the returned JSON.
- A **“Webhook events”** panel that polls `GET /api/webhook/events` every few seconds and
  dumps each received event (pretty-printed JSON: type, status, session_id, vendor_data,
  decision). This is the “data received from the webhook”.

## 12. Run & test
1. Fill `.env.local`, then `npm run dev` → open http://localhost:3000.
2. Click **Login with Valyd** → sign in → you're back, logged in, with 3 buttons.
3. **Managed by Valyd** → redirected to the Valyd hosted page → first time = full KYC, a
   returning user = selfie only → back on `/verify/done` with status + identity.
4. **Verify fresh** → full KYC every time, nothing kept.
5. **Standalone** → upload an ID + selfie → see the synchronous JSON.
6. **Webhooks**: they need a public URL. Run `ngrok http 3000`, set `APP_URL` to the ngrok
   URL (and register that `…/api/webhook` as a destination in the verify console with the
   same `VALYD_WEBHOOK_SECRET`). Events then appear live in the Webhook events panel. (The
   decision API on `/verify/done` already works locally without a tunnel.)

## 13. Acceptance criteria
- No secret/token ever reaches the browser (only `session.url` and final results do).
- Logged-out = only the Login button; logged-in = the 3 buttons.
- Managed sends `valydAccessToken`; Fresh does not; both redirect to the hosted `url`.
- Standalone returns a synchronous result.
- `/verify/done` shows `status` + `identity` from the decision API.
- The Webhook panel shows received, signature-verified events.
- A `401 valyd_login_required` from the Managed route routes the user back to login; a `402`
  shows a clear “top up balance” message.
