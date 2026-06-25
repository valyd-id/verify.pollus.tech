import { useEffect, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Plus, Loader2, MoreHorizontal, KeyRound, Trash2, Check, Webhook as WebhookIcon } from "lucide-react";
import { api, type WebhookEndpoint } from "../api";
import { useApps } from "../apps";
import { Button, Card, CopyField, Modal, PageHeader } from "../components/ui";
import { fadeUp, menu as menuVariants, listContainer, listItem } from "../components/motion";

// Event types the backend actually emits.
const EVENTS = [
  "verification.approved",
  "verification.declined",
  "verification.in_review",
  "verification.expired",
  "verification.abandoned",
  "verification.credential_changed",
] as const;

export function Webhooks() {
  const { selected } = useApps();
  const [endpoints, setEndpoints] = useState<WebhookEndpoint[]>([]);
  const [loaded, setLoaded] = useState(false);
  const [editing, setEditing] = useState<WebhookEndpoint | "new" | null>(null);
  const [menuFor, setMenuFor] = useState<number | null>(null);

  const load = async () => {
    if (!selected) return;
    setLoaded(false);
    const r = await api.webhooks(selected.id);
    if (r.success && r.data) setEndpoints(r.data.endpoints);
    setLoaded(true);
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [selected?.id]);

  const onSaved = () => {
    setEditing(null);
    load();
  };

  const toggleActive = async (ep: WebhookEndpoint) => {
    if (!selected) return;
    await api.updateWebhook(selected.id, ep.id, { is_active: !ep.is_active });
    load();
  };

  const rotate = async (ep: WebhookEndpoint) => {
    if (!selected) return;
    setMenuFor(null);
    await api.rotateWebhookSecret(selected.id, ep.id);
    load();
  };

  const remove = async (ep: WebhookEndpoint) => {
    if (!selected) return;
    setMenuFor(null);
    await api.deleteWebhook(selected.id, ep.id);
    load();
  };

  if (!selected) return <div className="grid h-[50vh] place-items-center text-sm text-muted-foreground">Create an app first to configure webhooks.</div>;

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-4xl">
      <PageHeader
        title="Webhooks"
        subtitle="Add one or more destinations. Every event fans out to each active endpoint, signed with its own secret."
        action={<Button onClick={() => setEditing("new")} className="rounded-full"><Plus className="h-4 w-4" /> Add destination</Button>}
      />

      <Card className="overflow-hidden">
        {!loaded ? (
          <div className="grid place-items-center py-16"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>
        ) : endpoints.length === 0 ? (
          <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary"><WebhookIcon className="h-6 w-6" /></span>
            <h3 className="mt-4 text-base font-semibold text-foreground">No destinations yet</h3>
            <p className="mt-1 text-sm text-muted-foreground">Add a destination to start receiving signed verification events.</p>
            <Button className="mt-5" onClick={() => setEditing("new")}><Plus className="h-4 w-4" /> Add destination</Button>
          </div>
        ) : (
          <motion.ul variants={listContainer} initial="hidden" animate="show" className="divide-y divide-border">
            {endpoints.map((ep) => (
              <motion.li key={ep.id} variants={listItem} className="px-5 py-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-foreground">{ep.name}</span>
                      <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${ep.is_active ? "bg-emerald-500/15 text-emerald-300" : "bg-secondary text-muted-foreground"}`}>{ep.is_active ? "active" : "paused"}</span>
                    </div>
                    <div className="mt-0.5 truncate font-mono text-xs text-muted-foreground">{ep.url}</div>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {(ep.events ?? ["all events"]).map((e) => (
                        <span key={e} className="rounded bg-secondary px-2 py-0.5 font-mono text-[11px] text-muted-foreground">{e}</span>
                      ))}
                    </div>
                  </div>
                  <div className="relative shrink-0">
                    <button onClick={() => setMenuFor(menuFor === ep.id ? null : ep.id)} className="text-muted-foreground hover:text-foreground"><MoreHorizontal className="h-4 w-4" /></button>
                    <AnimatePresence>
                      {menuFor === ep.id && (
                        <motion.div variants={menuVariants} initial="hidden" animate="show" exit="exit" style={{ transformOrigin: "top right" }}
                          className="absolute right-0 top-7 z-20 w-44 overflow-hidden rounded-lg border border-border bg-card py-1 text-left shadow-[var(--shadow-lift)]">
                          <button onClick={() => { setEditing(ep); setMenuFor(null); }} className="block w-full px-3 py-1.5 text-left text-sm text-muted-foreground hover:bg-secondary">Edit</button>
                          <button onClick={() => toggleActive(ep)} className="block w-full px-3 py-1.5 text-left text-sm text-muted-foreground hover:bg-secondary">{ep.is_active ? "Pause" : "Activate"}</button>
                          <button onClick={() => rotate(ep)} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-muted-foreground hover:bg-secondary"><KeyRound className="h-3.5 w-3.5" /> Rotate secret</button>
                          <button onClick={() => remove(ep)} className="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-red-400 hover:bg-red-500/10"><Trash2 className="h-3.5 w-3.5" /> Delete</button>
                        </motion.div>
                      )}
                    </AnimatePresence>
                  </div>
                </div>
                {ep.signing_secret && (
                  <div className="mt-3">
                    <div className="mb-1 text-[11px] text-muted-foreground">Signing secret</div>
                    <CopyField value={ep.signing_secret} />
                  </div>
                )}
              </motion.li>
            ))}
          </motion.ul>
        )}
      </Card>

      <p className="mt-3 text-xs text-muted-foreground">
        Verify each request: <code className="rounded bg-card px-1.5 py-0.5 font-mono text-foreground">HMAC-SHA256(timestamp + "." + body, secret)</code> equals the <code className="rounded bg-card px-1.5 py-0.5 font-mono text-foreground">X-Valyd-Signature</code> header.
      </p>

      {editing && selected && (
        <EndpointModal
          app={selected.id}
          endpoint={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={onSaved}
        />
      )}
    </motion.div>
  );
}

function EndpointModal({ app, endpoint, onClose, onSaved }: {
  app: number; endpoint: WebhookEndpoint | null; onClose: () => void; onSaved: (e: WebhookEndpoint, isNew: boolean) => void;
}) {
  const isNew = !endpoint;
  const [name, setName] = useState(endpoint?.name ?? "");
  const [url, setUrl] = useState(endpoint?.url ?? "");
  const [allEvents, setAllEvents] = useState(endpoint ? endpoint.events === null : true);
  const [picked, setPicked] = useState<string[]>(endpoint?.events ?? []);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const toggle = (e: string) => setPicked((p) => (p.includes(e) ? p.filter((x) => x !== e) : [...p, e]));

  const save = async () => {
    if (!name.trim() || !url.trim()) { setError("Name and URL are required."); return; }
    setBusy(true); setError(null);
    const events = allEvents ? null : picked;
    const r = isNew
      ? await api.createWebhook(app, { name: name.trim(), url: url.trim(), events })
      : await api.updateWebhook(app, endpoint!.id, { name: name.trim(), url: url.trim(), events });
    setBusy(false);
    if (r.success && r.data) onSaved(r.data.endpoint, isNew);
    else setError(r.error?.message ?? "Could not save the destination.");
  };

  return (
    <Modal title={isNew ? "Add destination" : "Edit destination"} onClose={onClose}>
      <label className="text-sm font-medium text-foreground">Name</label>
      <input autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder="Production server"
        className="mt-1 mb-3 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-primary" />
      <label className="text-sm font-medium text-foreground">Endpoint URL</label>
      <input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://api.example.com/webhooks/valyd"
        className="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-primary" />

      <div className="mt-4 text-sm font-medium text-foreground">Events</div>
      <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm text-muted-foreground">
        <input type="checkbox" checked={allEvents} onChange={(e) => setAllEvents(e.target.checked)} className="h-4 w-4 accent-[#2BD4C0]" />
        All events
      </label>
      {!allEvents && (
        <div className="mt-2 space-y-1.5">
          {EVENTS.map((e) => (
            <label key={e} className="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-2.5 py-2 text-xs hover:bg-secondary">
              <input type="checkbox" checked={picked.includes(e)} onChange={() => toggle(e)} className="h-4 w-4 accent-[#2BD4C0]" />
              <span className="font-mono text-foreground">{e}</span>
            </label>
          ))}
        </div>
      )}

      {error && <p className="mt-3 text-sm text-red-400">{error}</p>}
      <div className="mt-5 flex justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>Cancel</Button>
        <Button onClick={save} disabled={busy}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />} {isNew ? "Add" : "Save"}</Button>
      </div>
    </Modal>
  );
}
