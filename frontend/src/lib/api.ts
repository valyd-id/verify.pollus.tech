// Same-origin calls to the Valyd Verify backend. In dev, Vite proxies /api to
// the Laravel server; in prod, nginx proxies /api to php-fpm. The session token
// (from the hosted URL) is the only credential needed for these endpoints.

export type ApiResult<T = unknown> = {
  success: boolean;
  data?: T;
  error?: { code: string; message: string };
};

async function asJson<T>(res: Response): Promise<ApiResult<T>> {
  try {
    return (await res.json()) as ApiResult<T>;
  } catch {
    return { success: false, error: { code: "bad_response", message: `HTTP ${res.status}` } };
  }
}

export type HostedState = {
  session_id: string;
  status: string;
  features: string[];
  steps: { feature: string; status: string }[];
  documents: string[];
  next_step: string | null;
  redirect_url: string | null;
  expires_at: string;
  reuse: boolean;
  pollus_id: string | null;
  reuse_eligible: boolean;
};

export type HostedResult = {
  session_id: string;
  status: string;
  decision: unknown;
  checks: { type: string; status: string; error: string | null }[];
  redirect_url: string | null;
};

export type CheckData = {
  fields?: Record<string, string>;
  [k: string]: unknown;
};

export type CheckRun = {
  check: { type: string; status: string; score: number | null; data?: CheckData; error: string | null };
  session_status: string;
};

export type CredentialState = {
  state_name: string;
  state_code: string;
};

export type CredentialProvider = {
  provider_code: string;
  provider_display_name: string;
  credential_code: string;
  credential_name: string;
  state_code: string;
  remote_code: string;
  required_fields: string[];
};

const J = { "Content-Type": "application/json", Accept: "application/json" };

export const getState = (token: string) =>
  fetch(`/api/hosted/${token}/state`, { headers: J }).then((r) => asJson<HostedState>(r));

export const getResult = (token: string) =>
  fetch(`/api/hosted/${token}/result`, { headers: J }).then((r) => asJson<HostedResult>(r));

export const uploadDocument = (token: string, type: string, image: string) =>
  fetch(`/api/hosted/${token}/documents`, {
    method: "POST",
    headers: J,
    body: JSON.stringify({ type, image }),
  }).then((r) => asJson(r));

export const runCheck = (token: string, check: string, payload: Record<string, unknown> = {}) =>
  fetch(`/api/hosted/${token}/run/${check}`, {
    method: "POST",
    headers: J,
    body: JSON.stringify(payload),
  }).then((r) => asJson<CheckRun>(r));

// Managed Identity by Valyd — returning user re-verifies with a selfie only,
// matched against the verify-side copy. The session is already bound to the Valyd
// identity at creation (the integrator passes the user's access token).
export const reuseFace = (token: string, selfie: string) =>
  fetch(`/api/hosted/${token}/reuse/face`, { method: "POST", headers: J, body: JSON.stringify({ selfie }) }).then((r) => asJson<{ match: boolean; score: number | null; session_status: string }>(r));

export const credentialStates = (token: string) =>
  fetch(`/api/hosted/${token}/credential/states`, { headers: J }).then((r) =>
    asJson<{ states: CredentialState[] }>(r)
  );

export const credentialProviders = (token: string, state: string) =>
  fetch(`/api/hosted/${token}/credential/states/${encodeURIComponent(state)}/providers`, {
    headers: J,
  }).then((r) => asJson<{ providers: CredentialProvider[] }>(r));
