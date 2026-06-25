import { NavLink } from "react-router-dom";
import { LayoutGrid, Users, Workflow, Webhook, Boxes, Wallet, Settings, ShieldCheck, type LucideIcon } from "lucide-react";
import { AppSwitcher } from "./AppSwitcher";

type Item = { label: string; icon: LucideIcon; to: string };

const NAV: Item[] = [
  { label: "Overview", icon: LayoutGrid, to: "/dashboard" },
  { label: "Verifications", icon: Users, to: "/dashboard/verifications" },
  { label: "Workflows", icon: Workflow, to: "/dashboard/workflows" },
  { label: "Webhooks", icon: Webhook, to: "/dashboard/webhooks" },
  { label: "Apps & Keys", icon: Boxes, to: "/dashboard/apps" },
  { label: "Billing", icon: Wallet, to: "/dashboard/billing" },
  { label: "Settings", icon: Settings, to: "/dashboard/settings" },
];

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <aside className="flex h-screen w-64 shrink-0 flex-col border-r border-border bg-card">
      <div className="p-3">
        <AppSwitcher onNavigate={onNavigate} />
      </div>

      <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
        {NAV.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === "/dashboard"}
            onClick={onNavigate}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm transition-colors ${
                isActive ? "bg-primary-soft font-medium text-primary" : "text-muted-foreground hover:bg-secondary hover:text-foreground"
              }`
            }
          >
            {({ isActive }) => (
              <>
                <item.icon className={`h-[18px] w-[18px] ${isActive ? "text-primary" : "text-muted-foreground"}`} strokeWidth={1.9} />
                {item.label}
              </>
            )}
          </NavLink>
        ))}
      </nav>

      <div className="flex items-center gap-2 border-t border-border px-4 py-3.5 text-sm font-medium text-foreground">
        <ShieldCheck className="h-4 w-4 text-primary" />
        Valyd Verify
      </div>
    </aside>
  );
}
