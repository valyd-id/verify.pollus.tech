import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api, tokenStore, type User } from "./api";
import { OIDC } from "./oidc";

// --- PKCE helpers (browser holds the verifier; backend exchanges with the secret) ---
function randomString(len = 64): string {
  const bytes = new Uint8Array(len);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => ("0" + (b & 0xff).toString(16)).slice(-2)).join("");
}
function b64url(buf: ArrayBuffer): string {
  return btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}
async function challenge(verifier: string): Promise<string> {
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(verifier));
  return b64url(digest);
}

const VERIFIER_KEY = "verify_pkce_verifier";
const REDIRECT_KEY = "verify_pkce_redirect";
const STATE_KEY = "verify_pkce_state";

/** Kick off Valyd SSO: build the authorize URL with PKCE and redirect to the IdP. */
export async function startValydLogin(): Promise<string | null> {
  if (!OIDC.clientId) return "not_configured";
  const verifier = randomString();
  const state = randomString(16);
  const nonce = randomString(16);
  const redirectUri = OIDC.redirectUri;
  sessionStorage.setItem(VERIFIER_KEY, verifier);
  sessionStorage.setItem(REDIRECT_KEY, redirectUri);
  sessionStorage.setItem(STATE_KEY, state);

  const u = new URL(OIDC.authorizeUrl);
  u.searchParams.set("client_id", OIDC.clientId);
  u.searchParams.set("redirect_uri", redirectUri);
  u.searchParams.set("response_type", "code");
  u.searchParams.set("scope", OIDC.scopes);
  u.searchParams.set("state", state);
  u.searchParams.set("nonce", nonce); // required by the Valyd IdP
  u.searchParams.set("code_challenge", await challenge(verifier));
  u.searchParams.set("code_challenge_method", "S256");
  window.location.href = u.toString(); // → https://idp.pollus.tech/...
  return null;
}

/**
 * Auto-login: after the IdP redirects back with ?code, exchange it for a console
 * session. The backend returns the user info directly, so the caller can sign in
 * without an extra /me round-trip (matches dev.pollus.tech's autologin).
 */
export async function completeValydLogin(
  code: string,
  returnedState?: string | null,
): Promise<{ ok: boolean; token?: string; user?: User; error?: string }> {
  const verifier = sessionStorage.getItem(VERIFIER_KEY) ?? undefined;
  const redirectUri = sessionStorage.getItem(REDIRECT_KEY) ?? OIDC.redirectUri;
  const savedState = sessionStorage.getItem(STATE_KEY);

  // CSRF protection: the state we sent must match the one returned.
  if (savedState && returnedState && savedState !== returnedState) {
    return { ok: false, error: "Security check failed (state mismatch). Please try again." };
  }

  const r = await api.autologin(code, verifier, redirectUri);
  sessionStorage.removeItem(VERIFIER_KEY);
  sessionStorage.removeItem(REDIRECT_KEY);
  sessionStorage.removeItem(STATE_KEY);

  if (r.success && r.data?.token && r.data?.user) {
    return { ok: true, token: r.data.token, user: r.data.user };
  }
  return { ok: false, error: r.error?.message ?? "Sign-in failed" };
}

type AuthState = {
  user: User | null;
  ready: boolean;
  login: (token: string, user: User) => void;
  logout: () => void;
  refresh: () => Promise<void>;
};
const Ctx = createContext<AuthState>({ user: null, ready: false, login: () => {}, logout: () => {}, refresh: async () => {} });

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  const refresh = async () => {
    if (!tokenStore.get()) { setUser(null); setReady(true); return; }
    const r = await api.me();
    setUser(r.success && r.data ? r.data.user : null);
    setReady(true);
  };

  useEffect(() => { refresh(); }, []);

  // Sign in directly with the token + user returned by the autologin call.
  const login = (token: string, u: User) => {
    tokenStore.set(token);
    setUser(u);
    setReady(true);
  };

  const logout = () => {
    api.logout();
    tokenStore.clear();
    setUser(null);
    window.location.href = "/login";
  };

  return <Ctx.Provider value={{ user, ready, login, logout, refresh }}>{children}</Ctx.Provider>;
}

export const useAuth = () => useContext(Ctx);
