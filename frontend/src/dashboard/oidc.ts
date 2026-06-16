// OIDC / "Login with Valyd" config — env-driven (like dev.pollus.tech).
//
// All values have safe defaults so PRODUCTION needs NO code change:
//  - The IdP base + client id default to the real values.
//  - The redirect URI defaults to the CURRENT origin + /login, so it is correct
//    automatically on localhost, staging, and verify.pollus.tech (each origin's
//    "/login" just needs to be in the client's allowed_redirect_uris at the IdP).
//
// Override any of them per-environment via Vite env vars (frontend/.env):
//   VITE_VALYD_IDP_URL, VITE_VALYD_CLIENT_ID, VITE_VALYD_SCOPES,
//   VITE_VALYD_AUTHORIZE_PATH, VITE_VALYD_REDIRECT_URI
const env = import.meta.env;

export const OIDC = {
  idpBase: (env.VITE_VALYD_IDP_URL as string) ?? "https://idp.pollus.tech",
  authorizePath: (env.VITE_VALYD_AUTHORIZE_PATH as string) ?? "api/auth/oidc/authorize",
  clientId: (env.VITE_VALYD_CLIENT_ID as string) ?? "verify-console",
  scopes: (env.VITE_VALYD_SCOPES as string) ?? "openid profile email",
  get redirectUri(): string {
    return (env.VITE_VALYD_REDIRECT_URI as string) ?? `${window.location.origin}/login`;
  },
  get authorizeUrl(): string {
    return `${this.idpBase.replace(/\/$/, "")}/${this.authorizePath.replace(/^\//, "")}`;
  },
};
