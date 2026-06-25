import { useState } from "react";
import { Outlet, Link } from "react-router-dom";
import { Menu, X, LogOut, ExternalLink, Wallet, Plus } from "lucide-react";
import { Sidebar } from "./components/Sidebar";
import { EmailPrompt } from "./components/EmailPrompt";
import { useAuth } from "./auth";
import { AppsProvider } from "./apps";
import { BalanceProvider, useBalance, formatMoney } from "./balance";

// Documentation site base (override with VITE_DOCS_URL); the navbar links to the
// Verify section at {base}/verify.
const DOCS_URL = (import.meta.env.VITE_DOCS_URL as string) ?? "https://docs.pollus.tech";

// Compact balance chip shown in the top bar; links to the Billing page and turns
// amber when the account is running low.
function BalancePill() {
  const { balance, currency, ready } = useBalance();
  const low = ready && balance < 1;
  return (
    <Link
      to="/dashboard/billing"
      title="Account balance"
      className={`group inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium tabular-nums transition-colors ${
        low
          ? "border-amber-500/30 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20"
          : "border-border-2 bg-card text-foreground hover:border-primary hover:text-primary"
      }`}
    >
      <Wallet className={`h-4 w-4 ${low ? "text-amber-400" : "text-muted-foreground group-hover:text-primary"}`} />
      {ready ? formatMoney(balance, currency) : "—"}
      <span className={`ml-0.5 grid h-4 w-4 place-items-center rounded-full ${low ? "bg-amber-500 text-[#06231f]" : "bg-secondary text-muted-foreground group-hover:bg-primary-soft group-hover:text-primary"}`}>
        <Plus className="h-3 w-3" strokeWidth={2.5} />
      </span>
    </Link>
  );
}

// Auth is enforced by <RequireAuth> in App.tsx, so a user is guaranteed here.
export function DashboardLayout() {
  const { user, logout, refresh } = useAuth();
  const [drawer, setDrawer] = useState(false);
  const [emailSkipped, setEmailSkipped] = useState(false);

  if (!user) return null;

  return (
    <AppsProvider>
      <BalanceProvider>
      {!user.email && !emailSkipped && (
        <EmailPrompt
          onDone={async () => {
            setEmailSkipped(true);
            await refresh();
          }}
        />
      )}
      <div className="flex h-screen overflow-hidden bg-background text-foreground" style={{ backgroundImage: "var(--gradient-hero)" }}>
        {/* Desktop sidebar */}
        <div className="hidden md:flex">
          <Sidebar />
        </div>

        {/* Mobile drawer */}
        {drawer && (
          <div className="fixed inset-0 z-40 md:hidden">
            <div className="absolute inset-0 bg-slate-900/40" onClick={() => setDrawer(false)} />
            <div className="absolute left-0 top-0 h-full">
              <Sidebar onNavigate={() => setDrawer(false)} />
            </div>
            <button onClick={() => setDrawer(false)} className="absolute right-4 top-4 z-50 grid h-9 w-9 place-items-center rounded-lg bg-card text-muted-foreground shadow" aria-label="Close menu">
              <X className="h-5 w-5" />
            </button>
          </div>
        )}

        <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
          <header className="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-border bg-card/80 px-4 py-3 backdrop-blur sm:px-6">
            <div className="flex items-center gap-2">
              <button onClick={() => setDrawer(true)} className="grid h-9 w-9 place-items-center rounded-lg text-muted-foreground hover:bg-secondary md:hidden" aria-label="Open menu">
                <Menu className="h-5 w-5" />
              </button>
              <a href={`${DOCS_URL}/verify`} target="_blank" rel="noopener noreferrer" className="hidden items-center gap-1 text-[13px] font-medium text-primary hover:opacity-80 sm:inline-flex">
                Documentation <ExternalLink className="h-3 w-3" />
              </a>
            </div>
            <div className="flex items-center gap-2.5">
              <BalancePill />
              <div className="hidden h-6 w-px bg-border sm:block" />
              <div className="hidden text-right sm:block">
                <div className="text-sm font-medium text-foreground">{user.name ?? "Developer"}</div>
                <div className="text-xs text-muted-foreground">{user.email}</div>
              </div>
              <span className="grid h-9 w-9 place-items-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                {(user.name ?? user.email ?? "DV").slice(0, 2).toUpperCase()}
              </span>
              <button onClick={logout} className="grid h-9 w-9 place-items-center rounded-lg text-muted-foreground hover:bg-secondary" title="Sign out">
                <LogOut className="h-[18px] w-[18px]" />
              </button>
            </div>
          </header>

          <main className="flex-1 px-4 pb-12 pt-5 sm:px-6 lg:px-8">
            <Outlet />
          </main>
        </div>
      </div>
      </BalanceProvider>
    </AppsProvider>
  );
}
