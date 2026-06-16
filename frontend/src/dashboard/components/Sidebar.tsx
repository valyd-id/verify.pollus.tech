import { NavLink } from "react-router-dom";
import { LayoutGrid, Users, Workflow, Webhook, Boxes, Settings, ShieldCheck, type LucideIcon } from "lucide-react";
import { AppSwitcher } from "./AppSwitcher";

type Item = { label: string; icon: LucideIcon; to: string };

const NAV: Item[] = [
  { label: "Overview", icon: LayoutGrid, to: "/dashboard" },
  { label: "Verifications", icon: Users, to: "/dashboard/verifications" },
  { label: "Workflows", icon: Workflow, to: "/dashboard/workflows" },
  { label: "Webhooks", icon: Webhook, to: "/dashboard/webhooks" },
  { label: "Apps & Keys", icon: Boxes, to: "/dashboard/apps" },
  { label: "Settings", icon: Settings, to: "/dashboard/settings" },
];

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <aside className="flex h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white">
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
                isActive ? "bg-indigo-50 font-medium text-indigo-700" : "text-slate-600 hover:bg-slate-50"
              }`
            }
          >
            {({ isActive }) => (
              <>
                <item.icon className={`h-[18px] w-[18px] ${isActive ? "text-indigo-600" : "text-slate-400"}`} strokeWidth={1.9} />
                {item.label}
              </>
            )}
          </NavLink>
        ))}
      </nav>

      <div className="flex items-center gap-2 border-t border-slate-100 px-4 py-3.5 text-sm font-medium text-slate-700">
        <ShieldCheck className="h-4 w-4 text-indigo-600" />
        Valyd Verify
      </div>
    </aside>
  );
}
