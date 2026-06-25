import { useRef, useState } from "react";
import { Plus, Boxes, Copy, Check, Eye, EyeOff, RefreshCw, X, ImageUp, AlertCircle, Trash2, Loader2 } from "lucide-react";
import { motion } from "framer-motion";
import { api, type App } from "../api";
import { useApps } from "../apps";
import { Button, Card, PageHeader } from "../components/ui";
import { fadeUp, overlay, dialog } from "../components/motion";
import { fileToLogoDataUrl } from "../lib/image";

export function Apps() {
  const { selected, reload, select } = useApps();
  const [creating, setCreating] = useState(false);

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-3xl">
      <PageHeader
        title="Apps & API keys"
        subtitle="Each app has a unique App ID and a secret API key used to authenticate Verify API calls."
        action={<Button onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> New app</Button>}
      />

      {selected ? (
        <AppGeneral key={selected.id} app={selected} />
      ) : (
        <Card className="flex flex-col items-center justify-center px-6 py-16 text-center">
          <span className="grid h-12 w-12 place-items-center rounded-xl bg-primary-soft text-primary"><Boxes className="h-6 w-6" /></span>
          <h3 className="mt-4 text-base font-semibold text-foreground">No apps yet</h3>
          <p className="mt-1 text-sm text-muted-foreground">Create your first app to get an App ID and API key.</p>
          <Button className="mt-5" onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> Create app</Button>
        </Card>
      )}

      {creating && (
        <CreateApplicationModal
          onClose={() => setCreating(false)}
          onCreated={async (id) => {
            setCreating(false);
            await reload();
            select(id);
          }}
        />
      )}
    </motion.div>
  );
}

function CreateApplicationModal({ onClose, onCreated }: { onClose: () => void; onCreated: (id: number) => void }) {
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [logo, setLogo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      setLogo(await fileToLogoDataUrl(file));
    } catch {
      setError("Could not read that image.");
    }
  };

  const create = async () => {
    if (!name.trim()) return;
    setBusy(true);
    setError(null);
    const r = await api.createApp(name.trim(), { description: description.trim() || null, logo });
    setBusy(false);
    if (r.success && r.data) onCreated(r.data.app.id);
    else setError(r.error?.message ?? "Could not create the application.");
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-border bg-card shadow-xl">
        <button onClick={onClose} className="absolute right-4 top-4 z-10 grid h-8 w-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary">
          <X className="h-4 w-4" />
        </button>

        <div className="flex flex-col sm:flex-row">
          {/* Left: title + primary action */}
          <div className="flex flex-col p-6 sm:w-60 sm:shrink-0">
            <h3 className="text-lg font-semibold text-foreground">Create application</h3>
            <p className="mt-1 text-sm text-muted-foreground">Enter a name for your application and configure the necessary settings.</p>
            <div className="mt-6 sm:mt-auto">
              <Button onClick={create} disabled={busy || !name.trim()}>
                {busy && <Loader2 className="h-4 w-4 animate-spin" />} Create application
              </Button>
            </div>
          </div>

          <div className="hidden w-px bg-secondary sm:block" />

          {/* Right: avatar + fields */}
          <div className="flex-1 space-y-5 p-6">
            <LogoEditor logo={logo} onUpload={() => fileRef.current?.click()} onDelete={() => setLogo(null)} hint="Max file size: 0.1 MB" />
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onFile} />

            <div>
              <label className="text-sm font-medium text-foreground">Application name</label>
              <input
                autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder="Enter your app name"
                onKeyDown={(e) => e.key === "Enter" && create()}
                className="mt-1.5 w-full rounded-lg border border-border px-3 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/30"
              />
            </div>

            <div>
              <label className="text-sm font-medium text-foreground">Application description</label>
              <textarea
                value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Enter your app description"
                className="mt-1.5 w-full resize-y rounded-lg border border-border px-3 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/30"
              />
            </div>

            {error && <p className="text-xs text-red-400">{error}</p>}
          </div>
        </div>
      </motion.div>
    </div>
  );
}

function AppGeneral({ app }: { app: App }) {
  const { reload, apps } = useApps();
  const [name, setName] = useState(app.name);
  const [description, setDescription] = useState(app.description ?? "");
  const [logo, setLogo] = useState<string | null>(app.logo);
  const [rawKey, setRawKey] = useState<string | null>(null);
  const [rotating, setRotating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [savedAt, setSavedAt] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const dirty = name !== app.name || description !== (app.description ?? "") || logo !== app.logo;
  const canDelete = !app.is_default && apps.length > 1;

  const rotate = async () => {
    setRotating(true);
    const r = await api.rotateKey(app.id);
    setRotating(false);
    if (r.success && r.data) setRawKey(r.data.api_key);
  };

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setError(null);
    try {
      setLogo(await fileToLogoDataUrl(file));
    } catch {
      setError("Could not read that image.");
    }
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    const r = await api.updateApp(app.id, { name: name.trim() || app.name, description: description.trim() || null, logo });
    setSaving(false);
    if (r.success) {
      await reload();
      setSavedAt(true);
      setTimeout(() => setSavedAt(false), 2000);
    } else {
      setError(r.error?.message ?? "Could not save changes.");
    }
  };

  const makeDefault = async () => {
    const r = await api.updateApp(app.id, { is_default: true });
    if (r.success) await reload();
  };

  return (
    <Card className="p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold text-foreground">General</h2>
        {app.is_default && (
          <span className="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary">Default app</span>
        )}
      </div>

      {/* Logo + default toggle */}
      <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
        <LogoEditor logo={logo} onUpload={() => fileRef.current?.click()} onDelete={() => setLogo(null)} size="lg" hint="Max file size: 2 MB" />
        <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onFile} />

        <label className="flex items-center gap-2.5 text-sm font-medium text-foreground">
          Set app as default
          <Toggle on={app.is_default} onChange={makeDefault} disabled={app.is_default} />
        </label>
      </div>

      {/* Application name */}
      <div className="mt-6">
        <label className="text-sm font-medium text-foreground">Application name</label>
        <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-border px-3 py-2 focus-within:border-primary">
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="min-w-0 flex-1 text-sm text-foreground outline-none"
          />
          {name && (
            <button onClick={() => setName("")} className="shrink-0 text-muted-foreground hover:text-foreground" title="Clear">
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      {/* Description */}
      <div className="mt-5">
        <label className="text-sm font-medium text-foreground">Description</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={3}
          placeholder="This is a description of the application"
          className="mt-1.5 w-full resize-y rounded-lg border border-border px-3 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-primary"
        />
      </div>

      {/* App ID + API Key */}
      <div className="mt-6 grid gap-5 sm:grid-cols-2">
        <Field label="App ID">
          <ReadonlyField value={app.app_id} />
        </Field>
        <Field label="API Key">
          <ApiKeyField prefix={app.api_key_prefix} fullKey={app.api_key} rawKey={rawKey} />
        </Field>
      </div>

      {rawKey && (
        <div className="mt-3 flex items-start gap-2 rounded-lg bg-amber-500/15 p-3 text-xs text-amber-300">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          This is your new API key. Copy it now — it is shown only once and cannot be retrieved later.
        </div>
      )}

      <div className="mt-4 flex justify-end">
        <button
          onClick={rotate}
          disabled={rotating}
          className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary disabled:opacity-50"
        >
          <RefreshCw className={`h-4 w-4 ${rotating ? "animate-spin" : ""}`} /> Rotate API Key
        </button>
      </div>

      {error && <p className="mt-3 text-sm text-red-400">{error}</p>}

      {/* Footer actions */}
      <div className="mt-6 flex items-center justify-between border-t border-border pt-5">
        <button
          onClick={() => setConfirmDelete(true)}
          disabled={!canDelete}
          title={app.is_default ? "The default app cannot be deleted" : undefined}
          className="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 hover:text-red-300 disabled:cursor-not-allowed disabled:text-muted-foreground"
        >
          <Trash2 className="h-4 w-4" /> Delete app
        </button>

        <div className="flex items-center gap-3">
          {savedAt && <span className="text-xs font-medium text-emerald-400">Saved</span>}
          <Button onClick={save} disabled={!dirty || saving}>
            {saving && <Loader2 className="h-4 w-4 animate-spin" />} Save changes
          </Button>
        </div>
      </div>

      {confirmDelete && (
        <DeleteAppModal app={app} onClose={() => setConfirmDelete(false)} onDeleted={reload} />
      )}
    </Card>
  );
}

function DeleteAppModal({ app, onClose, onDeleted }: { app: App; onClose: () => void; onDeleted: () => Promise<App[]> }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const remove = async () => {
    setBusy(true);
    setError(null);
    const r = await api.deleteApp(app.id);
    setBusy(false);
    if (r.success) {
      await onDeleted();
      onClose();
    } else {
      setError(r.error?.message ?? "Could not delete the app.");
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-xl">
        <div className="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-red-500/15">
          <Trash2 className="h-5 w-5 text-red-400" />
        </div>
        <h3 className="mt-4 text-center text-base font-semibold text-foreground">Delete “{app.name}”?</h3>
        <p className="mt-1 text-center text-sm text-muted-foreground">
          This permanently deletes the app, its API key, workflows and verification history. This cannot be undone.
        </p>
        {error && <p className="mt-3 text-center text-sm text-red-400">{error}</p>}
        <div className="mt-5 flex items-center gap-2">
          <Button variant="ghost" className="flex-1" onClick={onClose} disabled={busy}>Cancel</Button>
          <Button variant="danger" className="flex-1" onClick={remove} disabled={busy}>
            {busy && <Loader2 className="h-4 w-4 animate-spin" />} Delete app
          </Button>
        </div>
      </motion.div>
    </div>
  );
}

function LogoEditor({
  logo, onUpload, onDelete, size = "md", hint,
}: { logo: string | null; onUpload: () => void; onDelete: () => void; size?: "md" | "lg"; hint?: string }) {
  const box = size === "lg" ? "h-16 w-16" : "h-14 w-14";
  const icon = size === "lg" ? "h-6 w-6" : "h-5 w-5";
  return (
    <div className="flex items-center gap-4">
      <span className={`grid ${box} shrink-0 place-items-center overflow-hidden rounded-full border border-border bg-secondary text-muted-foreground`}>
        {logo ? <img src={logo} alt="" className="h-full w-full object-cover" /> : <ImageUp className={icon} />}
      </span>
      <div>
        <div className="flex items-center gap-2">
          <button onClick={onDelete} disabled={!logo} className="rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-red-400 hover:bg-red-500/15 disabled:cursor-not-allowed disabled:border-border disabled:text-muted-foreground">Delete</button>
          <button onClick={onUpload} className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground hover:bg-secondary">Upload</button>
        </div>
        {hint && <p className="mt-1.5 text-xs text-muted-foreground">{hint}</p>}
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="mb-1.5 text-sm font-medium text-foreground">{label}</div>
      {children}
    </div>
  );
}

function ReadonlyField({ value }: { value: string }) {
  const [copied, setCopied] = useState(false);
  const copy = () => {
    navigator.clipboard.writeText(value);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };
  return (
    <div className="flex items-center gap-2 rounded-lg bg-secondary px-3 py-2.5">
      <code className="min-w-0 flex-1 truncate font-mono text-[13px] text-muted-foreground">{value}</code>
      <button onClick={copy} className="shrink-0 text-muted-foreground hover:text-primary" title="Copy App ID">
        {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
      </button>
    </div>
  );
}

function ApiKeyField({ prefix, fullKey, rawKey }: { prefix: string; fullKey: string | null; rawKey: string | null }) {
  const [show, setShow] = useState(false);
  const [copied, setCopied] = useState(false);

  // The full key is stored (encrypted) and revealable anytime. `rawKey` is a
  // just-rotated key; `fullKey` is the persisted one. Legacy keys created before
  // storage have neither — only the prefix is known until the next rotation.
  const secret = rawKey ?? fullKey;
  const display = show ? (secret ?? `${prefix}${"•".repeat(24)}`) : "•".repeat(40);

  const copy = () => {
    navigator.clipboard.writeText(secret ?? prefix);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div>
      <div className="flex items-center gap-2 rounded-lg bg-secondary px-3 py-2.5">
        <code className="min-w-0 flex-1 truncate font-mono text-[13px] tracking-tight text-muted-foreground">{display}</code>
        <button onClick={() => setShow((v) => !v)} className="shrink-0 text-muted-foreground hover:text-primary" title={show ? "Hide" : "Reveal"}>
          {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
        <button onClick={copy} className="shrink-0 text-muted-foreground hover:text-primary" title="Copy API key">
          {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
        </button>
      </div>
      {show && !secret && (
        <p className="mt-1 text-[11px] text-muted-foreground">This key predates key storage — rotate to generate one you can reveal.</p>
      )}
    </div>
  );
}

function Toggle({ on, onChange, disabled }: { on: boolean; onChange: () => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      disabled={disabled}
      onClick={onChange}
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-60 ${on ? "bg-primary" : "bg-secondary"}`}
    >
      <span className={`inline-block h-5 w-5 transform rounded-full bg-card shadow transition-transform ${on ? "translate-x-[22px]" : "translate-x-0.5"}`} />
    </button>
  );
}
