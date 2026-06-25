import { useEffect, useState } from "react";
import {
  Plus, Trash2, Copy, Check, MoreHorizontal, User, Layers,
  IdCard, ScanFace, Smile, Cake, BadgeCheck, MapPin, Workflow as WorkflowIcon, type LucideIcon,
} from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { api, type Workflow } from "../api";
import { useApps } from "../apps";
import { Button, Card, PageHeader } from "../components/ui";
import { fadeUp, menu as menuVariants, listContainer, listItem } from "../components/motion";
import { useNavigate } from "react-router-dom";

// Per-feature display + indicative pricing (used to derive a workflow price range).
const FEATURE_META: Record<string, { icon: LucideIcon; min: number; max: number }> = {
  id_verification: { icon: IdCard, min: 0.1, max: 0.13 },
  liveness: { icon: ScanFace, min: 0.05, max: 0.08 },
  face_match: { icon: Smile, min: 0.05, max: 0.1 },
  age: { icon: Cake, min: 0.02, max: 0.05 },
  credential: { icon: BadgeCheck, min: 0.2, max: 0.32 },
  location: { icon: MapPin, min: 0.01, max: 0.02 },
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
  if (features.length === 1 && features.includes("location")) return "Location";
  return "Identity";
}

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
  const navigate = useNavigate();
  const [workflows, setWorkflows] = useState<Workflow[]>([]);
  const [menuFor, setMenuFor] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const load = async () => {
    if (!selected) return;
    const w = await api.workflows(selected.id);
    if (w.success && w.data) setWorkflows(w.data.workflows);
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [selected?.id]);

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

  const startNew = () => navigate("/dashboard/workflows/new");

  if (!selected) return <div className="grid h-[50vh] place-items-center text-sm text-muted-foreground">Create an app first to manage workflows.</div>;

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-6xl">
      <PageHeader
        title="Workflows"
        subtitle="Bundle services into a reusable workflow and reference its ID when creating a verification session."
        action={<Button onClick={startNew} className="rounded-full"><Plus className="h-4 w-4" /> New workflow</Button>}
      />

      <Card className="overflow-hidden">
        {workflows.length === 0 ? (
          <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary"><WorkflowIcon className="h-6 w-6" /></span>
            <h3 className="mt-4 text-base font-semibold text-foreground">No workflows yet</h3>
            <p className="mt-1 text-sm text-muted-foreground">Create a workflow to get a workflow ID for your sessions.</p>
            <Button className="mt-5" onClick={startNew}><Plus className="h-4 w-4" /> New workflow</Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[860px] text-sm">
              <thead>
                <tr className="border-b border-border text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
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
                  <motion.tr key={w.id} variants={listItem} className="border-b border-border/60 last:border-0 hover:bg-secondary/40">
                    <td className="px-5 py-3.5">
                      <div className="font-semibold text-foreground">{w.name}</div>
                      <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span className="font-mono">ID #{w.id.slice(0, 8)}…</span>
                        <button onClick={() => copyId(w.id)} className="text-muted-foreground hover:text-primary" title="Copy workflow ID">
                          {copiedId === w.id ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary">
                        <User className="h-3.5 w-3.5" /> {workflowType(w.features)}
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-muted-foreground">
                        <Layers className="h-3.5 w-3.5" /> Simple
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-1.5">
                        {w.features.map((f) => {
                          const Icon = FEATURE_META[f]?.icon ?? WorkflowIcon;
                          return (
                            <span key={f} title={f} className="grid h-7 w-7 place-items-center rounded-md border border-border bg-secondary text-muted-foreground">
                              <Icon className="h-4 w-4" />
                            </span>
                          );
                        })}
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-right tabular-nums text-foreground">{priceRange(w.features)}</td>
                    <td className="px-4 py-3.5 text-muted-foreground">{timeAgo(w.created_at)}</td>
                    <td className="relative px-4 py-3.5 text-right">
                      <button onClick={() => setMenuFor(menuFor === w.id ? null : w.id)} className="text-muted-foreground hover:text-foreground">
                        <MoreHorizontal className="h-4 w-4" />
                      </button>
                      <AnimatePresence>
                        {menuFor === w.id && (
                          <motion.div
                            variants={menuVariants} initial="hidden" animate="show" exit="exit"
                            style={{ transformOrigin: "top right" }}
                            className="absolute right-4 top-12 z-20 w-40 overflow-hidden rounded-lg border border-border bg-card py-1 text-left shadow-[var(--shadow-lift)]"
                          >
                            <button onClick={() => { copyId(w.id); setMenuFor(null); }} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-muted-foreground hover:bg-secondary">
                              <Copy className="h-3.5 w-3.5" /> Copy ID
                            </button>
                            <button onClick={() => remove(w.id)} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-red-400 hover:bg-red-500/10">
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
    </motion.div>
  );
}
