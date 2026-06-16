import { useEffect, useState } from "react";
import {
  Plus, Trash2, Copy, Check, MoreHorizontal, User, Layers,
  IdCard, ScanFace, Smile, Cake, BadgeCheck, Workflow as WorkflowIcon, type LucideIcon,
} from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { api, type Service, type Workflow } from "../api";
import { useApps } from "../apps";
import { Button, Card, Modal, PageHeader } from "../components/ui";
import { fadeUp, menu as menuVariants, listContainer, listItem } from "../components/motion";

// Per-feature display + indicative pricing (used to derive a workflow price range).
const FEATURE_META: Record<string, { icon: LucideIcon; min: number; max: number }> = {
  id_verification: { icon: IdCard, min: 0.1, max: 0.13 },
  liveness: { icon: ScanFace, min: 0.05, max: 0.08 },
  face_match: { icon: Smile, min: 0.05, max: 0.1 },
  age: { icon: Cake, min: 0.02, max: 0.05 },
  credential: { icon: BadgeCheck, min: 0.2, max: 0.32 },
};

function priceRange(features: string[]): string {
  const min = features.reduce((s, f) => s + (FEATURE_META[f]?.min ?? 0), 0);
  const max = features.reduce((s, f) => s + (FEATURE_META[f]?.max ?? 0), 0);
  return min === max ? `$${min.toFixed(2)}` : `$${min.toFixed(2)} - $${max.toFixed(2)}`;
}

function workflowType(features: string[]): string {
  const hasId = features.includes("id_verification");
  const hasCredential = features.includes("credential");
  if (hasId && hasCredential) return "KYC + License";
  if (hasCredential) return "License";
  if (hasId) return "KYC";
  if (features.includes("age")) return "Age";
  return "Identity";
}

// One-click templates for the two credential products.
const PRESETS: { label: string; name: string; features: string[] }[] = [
  { label: "License Verification", name: "License Verification", features: ["credential"] },
  { label: "KYC + License", name: "KYC + License", features: ["id_verification", "liveness", "face_match", "credential"] },
];

function timeAgo(iso: string): string {
  const secs = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  const mins = secs / 60, hours = mins / 60, days = hours / 24;
  if (secs < 60) return "just now";
  if (mins < 60) return `${Math.floor(mins)} min ago`;
  if (hours < 24) return `${Math.floor(hours)} hour${Math.floor(hours) === 1 ? "" : "s"} ago`;
  if (days < 30) return `${Math.floor(days)} day${Math.floor(days) === 1 ? "" : "s"} ago`;
  return new Date(iso).toLocaleDateString();
}

export function Workflows() {
  const { selected } = useApps();
  const [services, setServices] = useState<Service[]>([]);
  const [workflows, setWorkflows] = useState<Workflow[]>([]);
  const [creating, setCreating] = useState(false);
  const [name, setName] = useState("");
  const [picked, setPicked] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [menuFor, setMenuFor] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const load = async () => {
    if (!selected) return;
    const [s, w] = await Promise.all([api.services(), api.workflows(selected.id)]);
    if (s.success && s.data) setServices(s.data.services);
    if (w.success && w.data) setWorkflows(w.data.workflows);
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [selected?.id]);

  const toggle = (id: string) => setPicked((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));

  const create = async () => {
    if (!selected || !name.trim() || picked.length === 0) return;
    setBusy(true);
    const r = await api.createWorkflow(selected.id, name.trim(), picked);
    setBusy(false);
    if (r.success) { setCreating(false); setName(""); setPicked([]); load(); }
  };

  const remove = async (id: string) => {
    if (!selected) return;
    setMenuFor(null);
    await api.deleteWorkflow(selected.id, id);
    load();
  };

  const copyId = (id: string) => {
    navigator.clipboard.writeText(id);
    setCopiedId(id);
    setTimeout(() => setCopiedId((c) => (c === id ? null : c)), 1500);
  };

  if (!selected) return <div className="grid h-[50vh] place-items-center text-sm text-slate-500">Create an app first to manage workflows.</div>;

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-6xl">
      <PageHeader
        title="Workflows"
        subtitle="Bundle services into a reusable workflow and reference its ID when creating a verification session."
        action={<Button onClick={() => setCreating(true)} className="rounded-full"><Plus className="h-4 w-4" /> New workflow</Button>}
      />

      <Card className="overflow-hidden">
        {workflows.length === 0 ? (
          <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><WorkflowIcon className="h-6 w-6" /></span>
            <h3 className="mt-4 text-base font-semibold text-slate-800">No workflows yet</h3>
            <p className="mt-1 text-sm text-slate-500">Create a workflow to get a workflow ID for your sessions.</p>
            <Button className="mt-5" onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> New workflow</Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[860px] text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                  <th className="px-5 py-2.5 text-left font-medium">Workflow</th>
                  <th className="px-4 py-2.5 text-left font-medium">Type</th>
                  <th className="px-4 py-2.5 text-left font-medium">Structure</th>
                  <th className="px-4 py-2.5 text-left font-medium">Features</th>
                  <th className="px-4 py-2.5 text-right font-medium">Price</th>
                  <th className="px-4 py-2.5 text-left font-medium">Last updated</th>
                  <th className="px-4 py-2.5"></th>
                </tr>
              </thead>
              <motion.tbody variants={listContainer} initial="hidden" animate="show">
                {workflows.map((w) => (
                  <motion.tr key={w.id} variants={listItem} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/60">
                    <td className="px-5 py-3.5">
                      <div className="font-semibold text-slate-800">{w.name}</div>
                      <div className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                        <span className="font-mono">ID #{w.id.slice(0, 8)}…</span>
                        <button onClick={() => copyId(w.id)} className="text-slate-300 hover:text-indigo-600" title="Copy workflow ID">
                          {copiedId === w.id ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-lime-50 px-2.5 py-1 text-xs font-medium text-lime-700">
                        <User className="h-3.5 w-3.5" /> {workflowType(w.features)}
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                        <Layers className="h-3.5 w-3.5" /> Simple
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-1.5">
                        {w.features.map((f) => {
                          const Icon = FEATURE_META[f]?.icon ?? WorkflowIcon;
                          return (
                            <span key={f} title={f} className="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-500">
                              <Icon className="h-4 w-4" />
                            </span>
                          );
                        })}
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-right tabular-nums text-slate-700">{priceRange(w.features)}</td>
                    <td className="px-4 py-3.5 text-slate-400">{timeAgo(w.created_at)}</td>
                    <td className="relative px-4 py-3.5 text-right">
                      <button onClick={() => setMenuFor(menuFor === w.id ? null : w.id)} className="text-slate-400 hover:text-slate-600">
                        <MoreHorizontal className="h-4 w-4" />
                      </button>
                      <AnimatePresence>
                        {menuFor === w.id && (
                          <motion.div
                            variants={menuVariants} initial="hidden" animate="show" exit="exit"
                            style={{ transformOrigin: "top right" }}
                            className="absolute right-4 top-12 z-20 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg"
                          >
                            <button onClick={() => { copyId(w.id); setMenuFor(null); }} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                              <Copy className="h-3.5 w-3.5" /> Copy ID
                            </button>
                            <button onClick={() => remove(w.id)} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">
                              <Trash2 className="h-3.5 w-3.5" /> Delete
                            </button>
                          </motion.div>
                        )}
                      </AnimatePresence>
                    </td>
                  </motion.tr>
                ))}
              </motion.tbody>
            </table>
          </div>
        )}
      </Card>

      {creating && (
        <Modal title="New workflow" onClose={() => setCreating(false)}>
          <div className="mb-4">
            <div className="text-sm font-medium text-slate-700">Start from a template</div>
            <div className="mt-2 flex flex-wrap gap-2">
              {PRESETS.map((p) => {
                const active = name === p.name && p.features.every((f) => picked.includes(f)) && picked.length === p.features.length;
                return (
                  <button
                    key={p.name}
                    type="button"
                    onClick={() => { setName(p.name); setPicked(p.features); }}
                    className={`rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${active ? "border-indigo-400 bg-indigo-50 text-indigo-700" : "border-slate-200 text-slate-600 hover:border-indigo-400 hover:text-indigo-600"}`}
                  >
                    {p.label}
                  </button>
                );
              })}
            </div>
          </div>
          <label className="text-sm font-medium text-slate-700">Name</label>
          <input autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. KYC + AML"
            className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400" />
          <div className="mt-4 text-sm font-medium text-slate-700">Services</div>
          <div className="mt-2 space-y-2">
            {services.map((s) => (
              <label key={s.id} className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-2.5 hover:bg-slate-50">
                <input type="checkbox" checked={picked.includes(s.id)} onChange={() => toggle(s.id)} className="h-4 w-4 accent-indigo-600" />
                <span className="text-sm text-slate-700">{s.name}</span>
              </label>
            ))}
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setCreating(false)}>Cancel</Button>
            <Button onClick={create} disabled={busy || !name.trim() || picked.length === 0}>Create</Button>
          </div>
        </Modal>
      )}
    </motion.div>
  );
}
