import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Check, ChevronsUpDown, Plus } from "lucide-react";
import { useApps } from "../apps";

const initials = (name: string) => name.slice(0, 2).toUpperCase();

export function AppSwitcher({ onNavigate }: { onNavigate?: () => void }) {
  const { apps, selected, select } = useApps();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const navigate = useNavigate();

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  if (!selected) {
    return (
      <button
        onClick={() => { navigate("/dashboard/apps"); onNavigate?.(); }}
        className="flex w-full items-center gap-2 rounded-lg border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-500 hover:bg-slate-50"
      >
        <Plus className="h-4 w-4" /> Create your first app
      </button>
    );
  }

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-left transition-colors hover:bg-slate-50"
      >
        <span className="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-indigo-600 text-[11px] font-semibold text-white">{initials(selected.name)}</span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium text-slate-800">{selected.name}</span>
          <span className="block truncate text-[11px] text-slate-400">{selected.app_id}</span>
        </span>
        <ChevronsUpDown className="h-4 w-4 shrink-0 text-slate-400" />
      </button>

      {open && (
        <div className="absolute left-0 right-0 top-[calc(100%+6px)] z-30 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
          <div className="px-3 py-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Your apps</div>
          {apps.map((app) => (
            <button
              key={app.id}
              onClick={() => { select(app.id); setOpen(false); }}
              className="flex w-full items-center gap-2.5 px-2.5 py-1.5 text-left hover:bg-slate-50"
            >
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-indigo-600 text-[10px] font-semibold text-white">{initials(app.name)}</span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm text-slate-800">{app.name}</span>
                <span className="block truncate text-[11px] text-slate-400">{app.api_key_prefix}…</span>
              </span>
              {app.id === selected.id && <Check className="h-4 w-4 text-indigo-600" />}
            </button>
          ))}
          <div className="my-1 h-px bg-slate-100" />
          <button
            onClick={() => { setOpen(false); navigate("/dashboard/apps"); onNavigate?.(); }}
            className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm font-medium text-indigo-600 hover:bg-slate-50"
          >
            <Plus className="h-4 w-4" /> Create new app
          </button>
        </div>
      )}
    </div>
  );
}
