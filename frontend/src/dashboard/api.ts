// Developer-console API client. Talks same-origin to the Laravel backend
// (/api/console/*) with a Bearer console token obtained via Valyd SSO.

const TOKEN_KEY = "verify_console_token";

export const tokenStore = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t: string) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

export type ApiResult<T = unknown> = { success: boolean; data?: T; error?: { code: string; message: string } };

async function req<T = unknown>(path: string, opts: RequestInit = {}): Promise<ApiResult<T>> {
  const token = tokenStore.get();
  const headers: Record<string, string> = { Accept: "application/json", ...(opts.headers as Record<string, string>) };
  if (opts.body) headers["Content-Type"] = "application/json";
  if (token) headers["Authorization"] = `Bearer ${token}`;
  try {
    const res = await fetch(`/api${path}`, { ...opts, headers });
    if (res.status === 401) tokenStore.clear();
    return (await res.json()) as ApiResult<T>;
  } catch {
    return { success: false, error: { code: "network", message: "Network error" } };
  }
}

export type AccountProfile = {
  org_name: string | null;
  legal_name: string | null;
  address1: string | null;
  address2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  company_phone: string | null;
  tax_id: string | null;
  website: string | null;
  tos_url: string | null;
  logo: string | null;
  require_2fa: boolean;
};
export type User = { id: number; email: string | null; name: string | null; profile?: AccountProfile };
export type App = {
  id: number;
  name: string;
  app_id: string;
  description: string | null;
  logo: string | null;
  api_key_prefix: string;
  webhook_url: string | null;
  is_active: boolean;
  is_default: boolean;
  created_at: string;
  workflows_count: number;
};
export type Service = { id: string; name: string; description: string; icon: string };
export type Workflow = { id: string; name: string; features: string[]; settings: unknown; is_active: boolean; created_at: string };
export type Webhook = { webhook_url: string | null; has_signing_secret: boolean; signing_secret_hint: string | null };
export type Session = { session_id: string; status: string; mode: string; vendor_data: string | null; features: string[]; created_at: string; decided_at: string | null };
export type Stats = { total: number; approved: number; declined: number; in_review: number };
export type Balance = { balance: number; currency: string };
export type BillingTxn = {
  id: number;
  type: "credit" | "debit" | "refund";
  amount: number;
  balance_after: number;
  reason: string;
  reference: string | null;
  created_at: string;
};

export const api = {
  // auth
  config: () => req<{ configured: boolean; client_id: string | null; authorize_url: string; redirect_uri: string; scopes: string }>("/auth/config"),
  me: () => req<{ user: User }>("/auth/me"),
  // Auto-login: exchange the IdP code (+ PKCE verifier) for a console session.
  autologin: (code: string, code_verifier?: string, redirect_uri?: string) =>
    req<{ token: string; user: User; needs_email?: boolean }>("/auth/autologin", { method: "POST", body: JSON.stringify({ code, code_verifier, redirect_uri }) }),
  setEmail: (email: string) => req<{ user: User }>("/auth/email", { method: "POST", body: JSON.stringify({ email }) }),
  updateAccount: (payload: Partial<AccountProfile> & { name?: string | null; email?: string | null }) =>
    req<{ user: User }>("/auth/account", { method: "PUT", body: JSON.stringify(payload) }),
  logout: () => req("/auth/logout", { method: "POST" }),

  // console
  services: () => req<{ services: Service[] }>("/console/services"),
  apps: () => req<{ apps: App[] }>("/console/apps"),
  createApp: (name: string, opts: { description?: string | null; logo?: string | null } = {}) =>
    req<{ app: App; api_key: string }>("/console/apps", { method: "POST", body: JSON.stringify({ name, ...opts }) }),
  updateApp: (app: number, payload: { name?: string; description?: string | null; logo?: string | null; is_default?: boolean }) =>
    req<{ app: App }>(`/console/apps/${app}`, { method: "PUT", body: JSON.stringify(payload) }),
  deleteApp: (app: number) => req<{ deleted: boolean }>(`/console/apps/${app}`, { method: "DELETE" }),
  rotateKey: (app: number) => req<{ app: App; api_key: string }>(`/console/apps/${app}/rotate-key`, { method: "POST" }),

  workflows: (app: number) => req<{ workflows: Workflow[] }>(`/console/apps/${app}/workflows`),
  createWorkflow: (app: number, name: string, features: string[]) =>
    req<{ workflow: Workflow }>(`/console/apps/${app}/workflows`, { method: "POST", body: JSON.stringify({ name, features }) }),
  deleteWorkflow: (app: number, id: string) => req(`/console/apps/${app}/workflows/${id}`, { method: "DELETE" }),

  webhook: (app: number) => req<Webhook>(`/console/apps/${app}/webhook`),
  setWebhook: (app: number, webhook_url: string) => req<Webhook>(`/console/apps/${app}/webhook`, { method: "PUT", body: JSON.stringify({ webhook_url }) }),
  rotateSecret: (app: number) => req<{ signing_secret: string }>(`/console/apps/${app}/webhook/rotate-secret`, { method: "POST" }),

  sessions: (app: number) => req<{ sessions: Session[]; stats: Stats }>(`/console/apps/${app}/sessions`),

  // billing (per-account prepaid balance)
  balance: () => req<Balance>("/console/billing/balance"),
  topUp: (amount: number) => req<Balance>("/console/billing/top-up", { method: "POST", body: JSON.stringify({ amount }) }),
  transactions: (limit = 20) => req<{ transactions: BillingTxn[] }>(`/console/billing/transactions?limit=${limit}`),
};
