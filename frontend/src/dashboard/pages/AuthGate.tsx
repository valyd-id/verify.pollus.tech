import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Loader2, AlertTriangle } from "lucide-react";
import { motion } from "framer-motion";
import { useAuth, startValydLogin, completeValydLogin } from "../auth";
import { dialog } from "../components/motion";

/**
 * Pageless auth gate mounted at /login (also the OIDC redirect_uri).
 *  - No ?code  → bounce straight to the Valyd IdP (no intermediate page).
 *  - ?code     → exchange it for a console session, then go to /dashboard.
 */
export function AuthGate() {
  const { user, ready, login } = useAuth();
  const navigate = useNavigate();
  const ran = useRef(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready || ran.current) return;
    ran.current = true;
    (async () => {
      const params = new URLSearchParams(window.location.search);
      const code = params.get("code");
      const returnedState = params.get("state");
      const err = params.get("error");

      if (err) { setError(params.get("error_description") || err); return; }

      // Back from the IdP with a code → run the autologin exchange, which returns
      // the user info; sign in with it and head to the console.
      if (code) {
        const r = await completeValydLogin(code, returnedState);
        if (r.ok && r.token && r.user) {
          login(r.token, r.user);
          navigate("/dashboard", { replace: true });
        } else {
          setError(r.error ?? "Sign-in failed");
        }
        return;
      }

      // Already signed in? go to the console.
      if (user) { navigate("/dashboard", { replace: true }); return; }

      // Not logged in and no code → redirect to Valyd to authenticate.
      const res = await startValydLogin();
      if (res === "not_configured") setError("Valyd SSO isn't configured on this server yet. Set VALYD_OIDC_CLIENT_ID / SECRET in the backend .env.");
      else if (res) setError(res);
    })();
  }, [ready, user, navigate, login]);

  return (
    <div className="grid min-h-screen place-items-center p-4" style={{ background: "var(--gradient-hero)" }}>
      <motion.div variants={dialog} initial="hidden" animate="show" className="w-full max-w-sm rounded-2xl border border-border bg-card p-8 text-center shadow-lg">
        {error ? (
          <>
            <AlertTriangle className="mx-auto h-8 w-8 text-amber-500" />
            <h1 className="mt-3 text-base font-semibold text-foreground">Can't sign in</h1>
            <p className="mt-1 text-sm text-muted-foreground">{error}</p>
          </>
        ) : (
          <>
            <Loader2 className="mx-auto h-8 w-8 animate-spin text-primary" />
            <p className="mt-3 text-sm text-muted-foreground">Redirecting to Valyd…</p>
          </>
        )}
      </motion.div>
    </div>
  );
}
