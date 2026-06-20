import { useState } from "react";
import { Outlet, Link } from "react-router-dom";
import { Menu, X, LogOut, ExternalLink, Wallet, Plus } from "lucide-react";
import { Sidebar } from "./components/Sidebar";
import { EmailPrompt } from "./components/EmailPrompt";
import { useAuth } from "./auth";
import { AppsProvider } from "./apps";
import { BalanceProvider, useBalance, formatMoney } from "./balance";

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
          ? "border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100"
          : "border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
      }`}
    >
      <Wallet className={`h-4 w-4 ${low ? "text-amber-500" : "text-slate-400 group-hover:text-indigo-500"}`} />
      {ready ? formatMoney(balance, currency) : "—"}
      <span className={`ml-0.5 grid h-4 w-4 place-items-center rounded-full ${low ? "bg-amber-500 text-white" : "bg-slate-100 text-slate-500 group-hover:bg-indigo-100 group-hover:text-indigo-600"}`}>
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
      <div className="flex h-screen overflow-hidden bg-[#f7f8fa] text-slate-900">
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
            <button onClick={() => setDrawer(false)} className="absolute right-4 top-4 z-50 grid h-9 w-9 place-items-center rounded-lg bg-white text-slate-600 shadow" aria-label="Close menu">
              <X className="h-5 w-5" />
            </button>
          </div>
        )}

        <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
          <header className="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/80 px-4 py-3 backdrop-blur sm:px-6">
            <div className="flex items-center gap-2">
              <button onClick={() => setDrawer(true)} className="grid h-9 w-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Open menu">
                <Menu className="h-5 w-5" />
              </button>
              <a href="https://verify.pollus.tech/docs" className="hidden items-center gap-1 text-[13px] font-medium text-indigo-600 hover:text-indigo-700 sm:inline-flex">
                Documentation <ExternalLink className="h-3 w-3" />
              </a>
            </div>
            <div className="flex items-center gap-2.5">
              <BalancePill />
              <div className="hidden h-6 w-px bg-slate-200 sm:block" />
              <div className="hidden text-right sm:block">
                <div className="text-sm font-medium text-slate-800">{user.name ?? "Developer"}</div>
                <div className="text-xs text-slate-400">{user.email}</div>
              </div>
              <span className="grid h-9 w-9 place-items-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
                {(user.name ?? user.email ?? "DV").slice(0, 2).toUpperCase()}
              </span>
              <button onClick={logout} className="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" title="Sign out">
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
