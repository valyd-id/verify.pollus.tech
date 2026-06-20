import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { Loader2 } from "lucide-react";
import { HostedFlow } from "./components/HostedFlow";
import { AuthProvider, useAuth } from "./dashboard/auth";
import { DashboardLayout } from "./dashboard/DashboardLayout";
import { AuthGate } from "./dashboard/pages/AuthGate";
import { Overview } from "./dashboard/pages/Overview";
import { Apps } from "./dashboard/pages/Apps";
import { Workflows } from "./dashboard/pages/Workflows";
import { Webhooks } from "./dashboard/pages/Webhooks";
import { Verifications } from "./dashboard/pages/Verifications";
import { Settings } from "./dashboard/pages/Settings";
import { Billing } from "./dashboard/pages/Billing";

/**
 * Surfaces sharing this SPA:
 *  - /verify?session=<token>  → end-user hosted verification flow
 *  - /login                   → "Login with Valyd" (OIDC redirect target / autologin)
 *  - /dashboard/*             → developer console (gated by RequireAuth → /login)
 */
function VerifyRoute() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get("session") ?? params.get("token") ?? "";
  return <HostedFlow token={token} />;
}

/** Gate console routes: wait for the session check, then bounce to /login (which
 *  redirects to the IdP) when there's no authenticated user. */
function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user, ready } = useAuth();
  if (!ready) {
    return (
      <div className="grid h-screen place-items-center bg-[#f7f8fa]">
        <Loader2 className="h-5 w-5 animate-spin text-indigo-600" />
      </div>
    );
  }
  if (!user) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

export function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/verify" element={<VerifyRoute />} />
          <Route path="/login" element={<AuthGate />} />

          <Route
            path="/dashboard"
            element={
              <RequireAuth>
                <DashboardLayout />
              </RequireAuth>
            }
          >
            <Route index element={<Overview />} />
            <Route path="verifications" element={<Verifications />} />
            <Route path="workflows" element={<Workflows />} />
            <Route path="webhooks" element={<Webhooks />} />
            <Route path="apps" element={<Apps />} />
            <Route path="billing" element={<Billing />} />
            <Route path="settings" element={<Settings />} />
          </Route>

          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
