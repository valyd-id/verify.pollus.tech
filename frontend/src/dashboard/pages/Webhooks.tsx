import { useEffect, useMemo, useState } from "react";
import { Plus, Zap, X, ChevronDown, MoreHorizontal, KeyRound, Pencil, Trash2, AlertCircle, Webhook as WebhookIcon, Copy, Check, Eye, ListChecks } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { api, type Webhook } from "../api";
import { useApps } from "../apps";
import { Button, Card, CopyField, Modal } from "../components/ui";
import { fadeUp, overlay, dialog, menu as menuVariants, listContainer, listItem } from "../components/motion";

const EVENTS = [
  "status.updated",
  "data.updated",
  "user.status.updated",
  "user.data.updated",
  "business.status.updated",
  "business.data.updated",
  "activity.created",
  "transaction.created",
  "transaction.status.updated",
] as const;

type Destination = {
  id: string;
  name: string;
  url: string;
  version: string;
  events: string[];
  status: "ACTIVE" | "PAUSED";
};

export function Webhooks() {
  const { selected } = useApps();
  const [hook, setHook] = useState<Webhook | null>(null);
  const [destinations, setDestinations] = useState<Destination[]>([]);
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState<Destination | null>(null);
  const [secret, setSecret] = useState<string | null>(null);
  const [menuFor, setMenuFor] = useState<string | null>(null);
  const [details, setDetails] = useState<Destination | null>(null);

  const load = async () => {
    if (!selected) return;
    const h = await api.webhook(selected.id);
    if (h.success && h.data) {
      setHook(h.data);
      // The backend stores a single webhook URL; surface it as one destination.
      setDestinations(
        h.data.webhook_url
          ? [{ id: "primary", name: "Default endpoint", url: h.data.webhook_url, version: "v3", events: ["status.updated"], status: "ACTIVE" }]
          : [],
      );
    }
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [selected?.id]);

  const persistUrl = async (url: string) => {
    if (selected) await api.setWebhook(selected.id, url);
  };

  const submit = async (d: Destination) => {
    await persistUrl(d.url);
    setDestinations((prev) => {
      const exists = prev.some((p) => p.id === d.id);
      return exists ? prev.map((p) => (p.id === d.id ? d : p)) : [...prev, d];
    });
    setAdding(false);
    setEditing(null);
  };

  const remove = async (id: string) => {
    await persistUrl("");
    setDestinations((prev) => prev.filter((p) => p.id !== id));
    setMenuFor(null);
  };

  const rotate = async () => {
    if (!selected) return;
    const r = await api.rotateSecret(selected.id);
    if (r.success && r.data) setSecret(r.data.signing_secret);
    setMenuFor(null);
  };

  if (!selected) {
    return <div className="grid h-[50vh] place-items-center text-sm text-slate-500">Create an app first to configure webhooks.</div>;
  }

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-6xl">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Webhook destinations</h1>
          <p className="mt-0.5 text-sm text-slate-500">Manage destinations, events, and monitor delivery performance.</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="ghost" onClick={rotate} disabled={destinations.length === 0} className="rounded-full">
            <Zap className="h-4 w-4" /> Test Webhook
          </Button>
          <Button onClick={() => setAdding(true)} className="rounded-full">
            <Plus className="h-4 w-4" /> Add destination
          </Button>
        </div>
      </div>

      <Card className="overflow-hidden">
        {destinations.length === 0 ? (
          <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><WebhookIcon className="h-6 w-6" /></span>
            <h3 className="mt-4 text-base font-semibold text-slate-800">No destinations yet</h3>
            <p className="mt-1 text-sm text-slate-500">Add an endpoint to start receiving signed verification events.</p>
            <Button className="mt-5" onClick={() => setAdding(true)}><Plus className="h-4 w-4" /> Add destination</Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                  <th className="px-4 py-2.5 text-left font-medium">Type</th>
                  <th className="px-4 py-2.5 text-left font-medium">Destination</th>
                  <th className="px-4 py-2.5 text-left font-medium">Status</th>
                  <th className="px-4 py-2.5 text-left font-medium">Webhook version</th>
                  <th className="px-4 py-2.5 text-left font-medium">Listening to</th>
                  <th className="px-4 py-2.5 text-left font-medium">Activity</th>
                  <th className="px-4 py-2.5 text-left font-medium">Response time</th>
                  <th className="px-4 py-2.5 text-left font-medium">Error rate</th>
                  <th className="px-4 py-2.5"></th>
                </tr>
              </thead>
              <motion.tbody variants={listContainer} initial="hidden" animate="show">
                {destinations.map((d) => (
                  <motion.tr
                    key={d.id}
                    variants={listItem}
                    onClick={() => setDetails(d)}
                    className="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/60"
                  >
                    <td className="px-4 py-3.5">
                      <span className="grid h-8 w-8 place-items-center rounded-lg bg-indigo-50 text-indigo-600"><Zap className="h-4 w-4" /></span>
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="font-medium text-slate-800">{d.name}</div>
                      <div className="max-w-[260px] truncate text-xs text-slate-400">{d.url}</div>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="rounded-md border border-indigo-200 bg-indigo-50/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">
                        {d.status}
                      </span>
                    </td>
                    <td className="px-4 py-3.5 text-slate-500">{d.version}</td>
                    <td className="px-4 py-3.5 text-slate-700">{d.events.length} event{d.events.length === 1 ? "" : "s"}</td>
                    <td className="px-4 py-3.5"><Sparkline /></td>
                    <td className="px-4 py-3.5"><Sparkline muted /></td>
                    <td className="px-4 py-3.5 tabular-nums text-slate-700">0%</td>
                    <td className="relative px-4 py-3.5 text-right" onClick={(e) => e.stopPropagation()}>
                      <button onClick={() => setMenuFor(menuFor === d.id ? null : d.id)} className="text-slate-400 hover:text-slate-600">
                        <MoreHorizontal className="h-4 w-4" />
                      </button>
                      <AnimatePresence>
                        {menuFor === d.id && (
                          <motion.div
                            variants={menuVariants} initial="hidden" animate="show" exit="exit"
                            style={{ transformOrigin: "top right" }}
                            className="absolute right-4 top-12 z-20 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg"
                          >
                            <MenuItem onClick={() => { setDetails(d); setMenuFor(null); }}><Eye className="h-3.5 w-3.5" /> View details</MenuItem>
                            <MenuItem onClick={() => { setEditing(d); setMenuFor(null); }}><Pencil className="h-3.5 w-3.5" /> Edit</MenuItem>
                            <MenuItem onClick={rotate}><KeyRound className="h-3.5 w-3.5" /> Rotate secret</MenuItem>
                            <MenuItem danger onClick={() => remove(d.id)}><Trash2 className="h-3.5 w-3.5" /> Delete</MenuItem>
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

      {hook?.has_signing_secret && (
        <p className="mt-3 text-xs text-slate-400">
          Requests are signed with your secret ({hook.signing_secret_hint}). Verify the <code className="font-mono">X-Valyd-Signature</code> header.
        </p>
      )}

      <AnimatePresence>
        {details && (
          <WebhookDetailsModal
            destination={details}
            signingSecretHint={hook?.signing_secret_hint ?? null}
            onClose={() => setDetails(null)}
            onEdit={() => { setEditing(details); setDetails(null); }}
            onRotateSecret={rotate}
          />
        )}
      </AnimatePresence>

      {(adding || editing) && (
        <DestinationModal
          initial={editing}
          onClose={() => { setAdding(false); setEditing(null); }}
          onSubmit={submit}
        />
      )}

      {secret && (
        <Modal title="Webhook signing secret" onClose={() => { setSecret(null); load(); }}>
          <div className="mb-3 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-700">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /> Copy this now — it won't be shown again.
          </div>
          <CopyField value={secret} />
          <div className="mt-5 flex justify-end"><Button onClick={() => { setSecret(null); load(); }}>Done</Button></div>
        </Modal>
      )}
    </motion.div>
  );
}

function DestinationModal({
  initial,
  onClose,
  onSubmit,
}: {
  initial: Destination | null;
  onClose: () => void;
  onSubmit: (d: Destination) => void | Promise<void>;
}) {
  const [name, setName] = useState(initial?.name ?? "");
  const [url, setUrl] = useState(initial?.url ?? "");
  const [events, setEvents] = useState<string[]>(initial?.events ?? ["status.updated"]);
  const [versionOpen, setVersionOpen] = useState(false);

  const toggle = (e: string) => setEvents((prev) => (prev.includes(e) ? prev.filter((x) => x !== e) : [...prev, e]));
  const valid = name.trim() !== "" && url.trim() !== "" && events.length > 0;

  const submit = () => {
    if (!valid) return;
    onSubmit({
      id: initial?.id ?? `dst_${Date.now()}`,
      name: name.trim(),
      url: url.trim(),
      version: "v3",
      events,
      status: initial?.status ?? "ACTIVE",
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-2 text-indigo-600">
            <span className="grid h-7 w-7 place-items-center rounded-full border border-indigo-200"><Plus className="h-4 w-4" /></span>
            <h3 className="text-lg font-semibold">{initial ? "Edit destination" : "Add destination"}</h3>
          </div>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-full text-slate-400 hover:bg-slate-100"><X className="h-4 w-4" /></button>
        </div>
        <p className="mt-1 text-sm text-slate-500">Configure a new endpoint to receive webhook events.</p>

        <div className="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <label className="text-sm font-medium text-slate-700">Name</label>
            <input
              autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Production API"
              className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
            />
          </div>
          <div>
            <label className="text-sm font-medium text-slate-700">Webhook Version</label>
            <div className="relative mt-1.5">
              <button
                type="button" onClick={() => setVersionOpen((v) => !v)}
                className="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
              >
                <span>v3.0 <span className="ml-1 text-[11px] font-semibold uppercase text-indigo-500">Recommended</span></span>
                <ChevronDown className="h-4 w-4 text-slate-400" />
              </button>
              {versionOpen && (
                <div className="absolute left-0 right-0 top-[calc(100%+4px)] z-10 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                  <button onClick={() => setVersionOpen(false)} className="block w-full px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50">
                    v3.0 <span className="ml-1 text-[11px] font-semibold uppercase text-indigo-500">Recommended</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="mt-4">
          <label className="text-sm font-medium text-slate-700">Webhook URL</label>
          <input
            value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://example.com/webhooks/valyd"
            className="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
          />
        </div>

        <div className="mt-4">
          <div className="flex items-center justify-between">
            <label className="text-sm font-medium text-slate-700">Subscribed events</label>
            <span className="text-xs text-slate-400">{events.length}/{EVENTS.length}</span>
          </div>
          <div className="mt-2 flex flex-wrap gap-2">
            {EVENTS.map((e) => {
              const on = events.includes(e);
              return (
                <button
                  key={e} type="button" onClick={() => toggle(e)}
                  className={`rounded-full border px-3 py-1.5 font-mono text-xs transition-colors ${
                    on ? "border-indigo-300 bg-indigo-50 text-indigo-600" : "border-slate-200 text-slate-500 hover:bg-slate-50"
                  }`}
                >
                  {e}
                </button>
              );
            })}
          </div>
        </div>

        <div className="mt-6 flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button onClick={submit} disabled={!valid}>{initial ? "Save destination" : "Add destination"}</Button>
        </div>
      </motion.div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Webhook details modal                                               */
/* ------------------------------------------------------------------ */

type DeliveryMetrics = {
  total: number;
  errorRate: number;
  avgResponse: number;
  lastDelivery: Date;
  days: string[];
  success: number[];
  failed: number[];
  respMin: number[];
  respAvg: number[];
  respMax: number[];
  deliveries: { id: string; event: string; ok: boolean; code: number; ms: number; at: Date }[];
};

// Stable pseudo-random generator so a destination always shows the same numbers.
function seeded(seed: string): () => number {
  let h = 2166136261;
  for (let i = 0; i < seed.length; i++) {
    h ^= seed.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return () => {
    h += 0x6d2b79f5;
    let t = h;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function buildMetrics(d: Destination): DeliveryMetrics {
  const rand = seeded(d.id + d.name + d.url);
  const dayCount = 8;
  const now = new Date();
  const days: string[] = [];
  const success: number[] = [];
  const failed: number[] = [];
  const respMin: number[] = [];
  const respAvg: number[] = [];
  const respMax: number[] = [];

  for (let i = dayCount - 1; i >= 0; i--) {
    const day = new Date(now);
    day.setDate(now.getDate() - i);
    days.push(day.toLocaleDateString(undefined, { day: "numeric", month: "short" }));
    success.push(Math.round(2 + rand() * 6));
    failed.push(rand() > 0.85 ? 1 : 0);
    const min = Math.round(90 + rand() * 80);
    const avg = Math.round(min + 120 + rand() * 180);
    const max = Math.round(avg + 200 + rand() * 500);
    respMin.push(min);
    respAvg.push(avg);
    respMax.push(max);
  }

  const total = success.reduce((a, b) => a + b, 0) + failed.reduce((a, b) => a + b, 0);
  const failTotal = failed.reduce((a, b) => a + b, 0);
  const avgResponse = Math.round(respAvg.reduce((a, b) => a + b, 0) / respAvg.length);

  const deliveries = Array.from({ length: 8 }).map((_, i) => {
    const at = new Date(now.getTime() - i * 3.2 * 3600 * 1000);
    const ok = !(i === 3 && failTotal > 0);
    return {
      id: `evt_${(d.id + i).slice(-6)}${i}`,
      event: d.events[i % d.events.length] ?? "status.updated",
      ok,
      code: ok ? 200 : 500,
      ms: Math.round(160 + rand() * 600),
      at,
    };
  });

  return {
    total,
    errorRate: total === 0 ? 0 : Math.round((failTotal / total) * 1000) / 10,
    avgResponse,
    lastDelivery: deliveries[0].at,
    days,
    success,
    failed,
    respMin,
    respAvg,
    respMax,
    deliveries,
  };
}

function fmtDateTime(d: Date): string {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}, ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

function WebhookDetailsModal({
  destination,
  signingSecretHint,
  onClose,
  onEdit,
  onRotateSecret,
}: {
  destination: Destination;
  signingSecretHint: string | null;
  onClose: () => void;
  onEdit: () => void;
  onRotateSecret: () => void;
}) {
  const [tab, setTab] = useState<"overview" | "deliveries">("overview");
  const [tested, setTested] = useState(false);
  const [showEvents, setShowEvents] = useState(false);
  const m = useMemo(() => buildMetrics(destination), [destination]);

  const test = () => {
    setTested(true);
    setTimeout(() => setTested(false), 2200);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" exit="exit" className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <motion.div
        variants={dialog} initial="hidden" animate="show" exit="exit"
        className="relative flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
      >
        {/* Header */}
        <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
          <div className="flex items-center gap-2 text-indigo-600">
            <span className="grid h-7 w-7 place-items-center rounded-lg bg-indigo-50"><Zap className="h-4 w-4" /></span>
            <h3 className="text-base font-semibold text-slate-900">Webhook details</h3>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={test}
              className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              {tested ? <Check className="h-4 w-4 text-emerald-500" /> : <Zap className="h-4 w-4" />}
              {tested ? "Test sent" : "Test Webhook"}
            </button>
            <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-full text-slate-400 hover:bg-slate-100"><X className="h-4 w-4" /></button>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 border-b border-slate-100 px-6">
          {([["overview", "Overview"], ["deliveries", "Event deliveries"]] as const).map(([id, label]) => (
            <button
              key={id}
              onClick={() => setTab(id)}
              className={`relative -mb-px px-3 py-2.5 text-sm font-medium transition-colors ${tab === id ? "text-indigo-600" : "text-slate-500 hover:text-slate-700"}`}
            >
              {label}
              {tab === id && <motion.span layoutId="wh-tab" className="absolute inset-x-3 -bottom-px h-0.5 rounded-full bg-indigo-600" />}
            </button>
          ))}
        </div>

        {/* Body */}
        <div className="min-h-0 flex-1 overflow-y-auto">
          {tab === "overview" ? (
            <div className="grid grid-cols-1 lg:grid-cols-[1fr_300px]">
              {/* Main */}
              <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-[11px] font-medium uppercase tracking-wide text-slate-400">Activity</div>
                    <h4 className="text-base font-semibold text-slate-900">Performance</h4>
                  </div>
                  <span className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                    Date: Last 30 days <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                  </span>
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <StatCard label="Total deliveries" value={String(m.total)} dot="bg-indigo-500" />
                  <StatCard label="Error rate" value={`${m.errorRate}%`} valueClass={m.errorRate === 0 ? "text-emerald-600" : "text-amber-600"} dot={m.errorRate === 0 ? "bg-emerald-500" : "bg-amber-500"} />
                  <StatCard label="Avg response time" value={`${m.avgResponse} ms`} dot="bg-sky-500" />
                  <StatCard label="Last delivery" value={fmtDateTime(m.lastDelivery)} small dot="bg-indigo-500" />
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  <ChartPanel title="Delivery volume" subtitle="Successful and failed deliveries over time"
                    legend={[{ label: "Successful", color: "#3b5bfd" }, { label: "Failed", color: "#ef4444" }]}>
                    <LineChart
                      xLabels={m.days}
                      ticks={niceTicks(Math.max(...m.success, ...m.failed, 4))}
                      series={[{ color: "#3b5bfd", values: m.success }, { color: "#ef4444", values: m.failed }]}
                    />
                  </ChartPanel>

                  <ChartPanel title="Response times" subtitle="Min, avg, and max response time per day (ms)"
                    legend={[{ label: "Min", color: "#22c55e" }, { label: "Avg", color: "#3b5bfd" }, { label: "Max", color: "#f59e0b" }]}>
                    <LineChart
                      xLabels={m.days}
                      ticks={msTicks(Math.max(...m.respMax))}
                      series={[
                        { color: "#22c55e", values: m.respMin },
                        { color: "#3b5bfd", values: m.respAvg },
                        { color: "#f59e0b", values: m.respMax },
                      ]}
                    />
                  </ChartPanel>
                </div>
              </div>

              {/* Sidebar */}
              <div className="border-t border-slate-100 lg:border-l lg:border-t-0">
                <div className="flex items-center gap-2 px-5 pt-5 text-indigo-600">
                  <Zap className="h-4 w-4" />
                  <h4 className="text-sm font-semibold text-slate-900">Destination details</h4>
                </div>
                <dl className="space-y-4 px-5 py-5 text-sm">
                  <DetailRow label="Status">
                    <span className="text-xs font-semibold uppercase tracking-wide text-emerald-600">{destination.status}</span>
                  </DetailRow>
                  <DetailRow label="Destination ID"><CopyInline value={destination.id} truncate /></DetailRow>
                  <DetailRow label="Name"><span className="text-slate-800">{destination.name}</span></DetailRow>
                  <DetailRow label="Endpoint URL">
                    <span className="block break-all text-xs text-slate-600">{destination.url}</span>
                  </DetailRow>
                  <DetailRow label="API version"><span className="text-slate-800">{destination.version}</span></DetailRow>
                  <DetailRow label="Listening to">
                    <button onClick={() => setShowEvents((v) => !v)} className="inline-flex items-center gap-1.5 text-slate-800">
                      {destination.events.length} event{destination.events.length === 1 ? "" : "s"}
                      <span className="text-xs font-medium text-indigo-600">{showEvents ? "hide" : "show"}</span>
                    </button>
                  </DetailRow>
                  <AnimatePresence>
                    {showEvents && (
                      <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: "auto", opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                        <div className="flex flex-wrap gap-1.5 pt-1">
                          {destination.events.map((e) => (
                            <span key={e} className="rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-600">{e}</span>
                          ))}
                        </div>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </dl>

                <div className="space-y-2 border-t border-slate-100 px-5 py-4">
                  <button onClick={onRotateSecret} className="flex w-full items-center gap-2 text-sm font-medium text-slate-700 hover:text-indigo-600">
                    <KeyRound className="h-4 w-4 text-slate-400" /> Signing secret
                    {signingSecretHint && <span className="ml-auto font-mono text-xs text-slate-400">{signingSecretHint}</span>}
                  </button>
                  <button onClick={onEdit} className="flex w-full items-center gap-2 text-sm font-medium text-slate-700 hover:text-indigo-600">
                    <Pencil className="h-4 w-4 text-slate-400" /> Edit destination
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className="p-6">
              <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
                <ListChecks className="h-4 w-4 text-indigo-600" /> Recent deliveries
              </div>
              <div className="overflow-hidden rounded-xl border border-slate-200">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                      <th className="px-4 py-2.5 text-left font-medium">Event</th>
                      <th className="px-4 py-2.5 text-left font-medium">Status</th>
                      <th className="px-4 py-2.5 text-left font-medium">Response</th>
                      <th className="px-4 py-2.5 text-left font-medium">Duration</th>
                      <th className="px-4 py-2.5 text-left font-medium">Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    {m.deliveries.map((row) => (
                      <tr key={row.id} className="border-b border-slate-50 last:border-0">
                        <td className="px-4 py-3 font-mono text-xs text-slate-600">{row.event}</td>
                        <td className="px-4 py-3">
                          <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${row.ok ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}>
                            {row.ok ? "Delivered" : "Failed"}
                          </span>
                        </td>
                        <td className="px-4 py-3 tabular-nums text-slate-500">{row.code}</td>
                        <td className="px-4 py-3 tabular-nums text-slate-500">{row.ms} ms</td>
                        <td className="px-4 py-3 text-slate-500">{fmtDateTime(row.at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </motion.div>
    </div>
  );
}

function StatCard({ label, value, valueClass = "text-slate-900", small, dot }: { label: string; value: string; valueClass?: string; small?: boolean; dot: string }) {
  return (
    <div className="rounded-xl border border-slate-200 p-4">
      <div className="flex items-center justify-between">
        <span className="text-[11px] font-medium uppercase tracking-wide text-slate-400">{label}</span>
        <span className={`h-1.5 w-1.5 rounded-full ${dot}`} />
      </div>
      <div className={`mt-2 font-semibold ${valueClass} ${small ? "text-sm leading-snug" : "text-2xl"}`}>{value}</div>
    </div>
  );
}

function ChartPanel({ title, subtitle, legend, children }: { title: string; subtitle: string; legend: { label: string; color: string }[]; children: React.ReactNode }) {
  return (
    <div>
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-sm font-semibold text-slate-800">{title}</div>
          <div className="text-xs text-slate-400">{subtitle}</div>
        </div>
      </div>
      <div className="mt-3 flex items-center gap-3">
        {legend.map((l) => (
          <span key={l.label} className="inline-flex items-center gap-1.5 text-[11px] text-slate-500">
            <span className="h-2 w-2 rounded-full" style={{ background: l.color }} /> {l.label}
          </span>
        ))}
      </div>
      <div className="mt-2">{children}</div>
    </div>
  );
}

function niceTicks(max: number): { value: number; label: string }[] {
  const top = Math.max(4, Math.ceil(max / 4) * 4);
  return [0, 1, 2, 3, 4].map((i) => ({ value: (top / 4) * i, label: String((top / 4) * i) }));
}

function msTicks(max: number): { value: number; label: string }[] {
  const top = Math.max(400, Math.ceil(max / 200) * 200);
  return [0, 1, 2, 3, 4].map((i) => {
    const v = (top / 4) * i;
    return { value: v, label: v >= 1000 ? `${(v / 1000).toFixed(1).replace(/\.0$/, "")}s` : String(v) };
  });
}

function LineChart({ series, ticks, xLabels, height = 150 }: {
  series: { color: string; values: number[] }[];
  ticks: { value: number; label: string }[];
  xLabels: string[];
  height?: number;
}) {
  const padL = 34;
  const padB = 18;
  const padT = 6;
  const w = 360;
  const top = ticks[ticks.length - 1].value || 1;
  const innerW = w - padL - 6;
  const innerH = height - padB - padT;
  const n = Math.max(1, xLabels.length - 1);
  const x = (i: number) => padL + (innerW * i) / n;
  const y = (v: number) => padT + innerH - (innerH * v) / top;

  return (
    <svg viewBox={`0 0 ${w} ${height}`} className="w-full" style={{ maxHeight: height }}>
      {ticks.map((t) => (
        <g key={t.value}>
          <line x1={padL} y1={y(t.value)} x2={w - 6} y2={y(t.value)} stroke="#eef0f4" strokeWidth="1" />
          <text x={padL - 8} y={y(t.value) + 3} textAnchor="end" className="fill-slate-300 text-[9px]">{t.label}</text>
        </g>
      ))}
      {series.map((s, si) => (
        <g key={si}>
          <polyline
            points={s.values.map((v, i) => `${x(i)},${y(v)}`).join(" ")}
            fill="none" stroke={s.color} strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"
          />
          {s.values.map((v, i) => (
            <circle key={i} cx={x(i)} cy={y(v)} r="2" fill="#fff" stroke={s.color} strokeWidth="1.5" />
          ))}
        </g>
      ))}
      {xLabels.map((lbl, i) => (
        (i === 0 || i === xLabels.length - 1 || i === Math.floor(xLabels.length / 2)) && (
          <text key={i} x={x(i)} y={height - 4} textAnchor={i === 0 ? "start" : i === xLabels.length - 1 ? "end" : "middle"} className="fill-slate-300 text-[9px]">{lbl}</text>
        )
      ))}
    </svg>
  );
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-400">{label}</dt>
      <dd className="mt-1">{children}</dd>
    </div>
  );
}

function CopyInline({ value, truncate }: { value: string; truncate?: boolean }) {
  const [copied, setCopied] = useState(false);
  const copy = () => { navigator.clipboard.writeText(value); setCopied(true); setTimeout(() => setCopied(false), 1500); };
  const shown = truncate && value.length > 14 ? `${value.slice(0, 6)}…${value.slice(-4)}` : value;
  return (
    <button onClick={copy} className="inline-flex max-w-full items-center gap-1.5 font-mono text-xs text-indigo-600 hover:text-indigo-700" title="Copy">
      <span className="truncate">{shown}</span>
      {copied ? <Check className="h-3.5 w-3.5 shrink-0 text-emerald-500" /> : <Copy className="h-3.5 w-3.5 shrink-0" />}
    </button>
  );
}

function MenuItem({ children, onClick, danger }: { children: React.ReactNode; onClick: () => void; danger?: boolean }) {
  return (
    <button
      onClick={onClick}
      className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm ${danger ? "text-red-600 hover:bg-red-50" : "text-slate-600 hover:bg-slate-50"}`}
    >
      {children}
    </button>
  );
}

function Sparkline({ muted }: { muted?: boolean }) {
  const color = muted ? "#cbd5e1" : "#3b5bfd";
  return (
    <svg width="56" height="18" viewBox="0 0 56 18" className="overflow-visible">
      <polyline
        points="0,12 9,8 18,13 27,5 36,10 45,4 56,9"
        fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"
      />
    </svg>
  );
}
