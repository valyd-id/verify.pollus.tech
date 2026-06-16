import { useEffect, useMemo, useRef, useState } from "react";
import { motion } from "framer-motion";
import { ImageUp, Loader2, Check, LogOut } from "lucide-react";
import { api, type AccountProfile } from "../api";
import { useAuth } from "../auth";
import { useApps } from "../apps";
import { Card } from "../components/ui";
import { fadeUp } from "../components/motion";
import { fileToLogoDataUrl } from "../lib/image";

type TabId = "account" | "team" | "roles" | "sso" | "usage" | "billing" | "audit";
const TABS: { id: TabId; label: string }[] = [
  { id: "account", label: "Account" },
  { id: "team", label: "Team" },
  { id: "roles", label: "Roles" },
  { id: "sso", label: "SSO" },
  { id: "usage", label: "Usage" },
  { id: "billing", label: "Billing" },
  { id: "audit", label: "Audit Logs" },
];

const COUNTRIES = [
  "United States", "United Kingdom", "Canada", "Australia", "Germany", "France",
  "Spain", "Italy", "Netherlands", "India", "Pakistan", "United Arab Emirates",
  "Singapore", "Japan", "Brazil", "Mexico", "South Africa", "Nigeria",
];

type Form = AccountProfile & { name: string; email: string };

const EMPTY_FORM: Form = {
  name: "", email: "",
  org_name: "", legal_name: "", address1: "", address2: "", city: "", state: "",
  postal_code: "", country: "", company_phone: "", tax_id: "", website: "",
  tos_url: "", logo: null, require_2fa: false,
};

export function Settings() {
  const { user, logout, refresh } = useAuth();
  const { apps } = useApps();
  const [tab, setTab] = useState<TabId>("account");
  const [form, setForm] = useState<Form>(EMPTY_FORM);
  const [loaded, setLoaded] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    api.me().then((r) => {
      if (r.success && r.data) {
        const u = r.data.user;
        const p = u.profile;
        setForm({
          name: u.name ?? "", email: u.email ?? "",
          org_name: p?.org_name ?? "", legal_name: p?.legal_name ?? "",
          address1: p?.address1 ?? "", address2: p?.address2 ?? "",
          city: p?.city ?? "", state: p?.state ?? "", postal_code: p?.postal_code ?? "",
          country: p?.country ?? "", company_phone: p?.company_phone ?? "",
          tax_id: p?.tax_id ?? "", website: p?.website ?? "", tos_url: p?.tos_url ?? "",
          logo: p?.logo ?? null, require_2fa: p?.require_2fa ?? false,
        });
      }
      setLoaded(true);
    });
  }, []);

  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }));

  const onLogo = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try { set("logo", await fileToLogoDataUrl(file)); } catch { /* ignore */ }
  };

  const save = async () => {
    setSaving(true);
    const r = await api.updateAccount(form);
    setSaving(false);
    if (r.success) {
      await refresh();
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    }
  };

  const initials = useMemo(
    () => (form.org_name || user?.name || user?.email || "MY").slice(0, 2).toUpperCase(),
    [form.org_name, user],
  );

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-5xl pb-24">
      <div className="mb-5">
        <h1 className="text-xl font-semibold text-slate-900 sm:text-2xl">Organization account</h1>
        <p className="mt-1 text-sm text-slate-500">Update your organization profile, logo, and security settings.</p>
      </div>

      {/* Tabs */}
      <div className="mb-6 flex flex-wrap gap-1 border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`relative -mb-px rounded-t-lg px-3.5 py-2 text-sm font-medium transition-colors ${
              tab === t.id ? "text-indigo-600" : "text-slate-500 hover:text-slate-700"
            }`}
          >
            {t.label}
            {tab === t.id && <motion.span layoutId="settings-tab" className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-indigo-600" />}
          </button>
        ))}
      </div>

      {tab !== "account" ? (
        <Card className="grid place-items-center px-6 py-20 text-center">
          <div className="text-base font-semibold text-slate-800">{TABS.find((t) => t.id === tab)?.label}</div>
          <p className="mt-1 max-w-sm text-sm text-slate-500">This section is coming soon.</p>
        </Card>
      ) : !loaded ? (
        <div className="grid h-60 place-items-center"><Loader2 className="h-5 w-5 animate-spin text-indigo-600" /></div>
      ) : (
        <div className="space-y-6">
          {/* Account information */}
          <Card className="p-6">
            <h2 className="text-base font-semibold text-slate-900">Account information</h2>

            <div className="mt-5">
              <div className="text-sm font-medium text-slate-700">Organization logo</div>
              <div className="mt-2 flex items-center gap-4">
                <span className="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-400">
                  {form.logo ? <img src={form.logo} alt="" className="h-full w-full object-cover" /> : <ImageUp className="h-5 w-5" />}
                </span>
                <div>
                  <div className="flex items-center gap-2">
                    <button onClick={() => set("logo", null)} disabled={!form.logo} className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-300">Delete</button>
                    <button onClick={() => fileRef.current?.click()} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-50">Upload</button>
                    <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onLogo} />
                  </div>
                  <p className="mt-1.5 text-xs text-slate-400">Max file size: 2 MB</p>
                </div>
              </div>
            </div>

            <div className="mt-6 grid gap-5 sm:grid-cols-2">
              <TextField label="Organization name" value={form.org_name} onChange={(v) => set("org_name", v)} placeholder="My organization" clearable />
              <TextField label="Email address" value={form.email} onChange={(v) => set("email", v)} placeholder="you@example.com" clearable />
              <TextField label="Legal name" value={form.legal_name} onChange={(v) => set("legal_name", v)} placeholder="Ex. 9Labs Inc." />
              <TextField label="Address line 1" value={form.address1} onChange={(v) => set("address1", v)} placeholder="Ex. 123 Main St" />
              <TextField label="Address line 2" value={form.address2} onChange={(v) => set("address2", v)} placeholder="Ex. Suite 100" />
              <TextField label="City" value={form.city} onChange={(v) => set("city", v)} placeholder="Ex. San Francisco" />
              <TextField label="State / Province" value={form.state} onChange={(v) => set("state", v)} placeholder="Ex. California" />
              <TextField label="Postal code" value={form.postal_code} onChange={(v) => set("postal_code", v)} placeholder="Ex. 94102" />
              <SelectField label="Country" value={form.country} onChange={(v) => set("country", v)} options={COUNTRIES} placeholder="Select country" />
              <TextField label="Company phone" value={form.company_phone} onChange={(v) => set("company_phone", v)} placeholder="Ex. +1234567890" />
              <TextField label="Tax ID number" value={form.tax_id} onChange={(v) => set("tax_id", v)} placeholder="Ex. 1234567890" />
              <TextField label="Website" value={form.website} onChange={(v) => set("website", v)} placeholder="https://example.com" clearable />
              <TextField label="Terms of service URL" value={form.tos_url} onChange={(v) => set("tos_url", v)} placeholder="Ex. https://www.example.com/terms" />
            </div>
          </Card>

          {/* Security */}
          <Card className="p-6">
            <h2 className="text-base font-semibold text-slate-900">Security</h2>
            <div className="mt-4 flex items-start justify-between gap-6">
              <div>
                <div className="text-sm font-medium text-slate-800">
                  Require two-factor authentication for everyone in the {form.org_name || "organization"} team
                </div>
                <p className="mt-1 max-w-2xl text-xs text-slate-500">
                  Members who do not have two-factor authentication enabled will be unable to access resources owned by the organization until they update their settings.
                </p>
              </div>
              <Toggle on={form.require_2fa} onChange={() => set("require_2fa", !form.require_2fa)} />
            </div>
          </Card>

          {/* Organization apps */}
          <Card className="p-6">
            <h2 className="text-base font-semibold text-slate-900">Organization apps</h2>
            <p className="mt-1 text-sm text-slate-500">Apps owned by this organization.</p>
            <div className="mt-4 divide-y divide-slate-100">
              {apps.length === 0 ? (
                <p className="py-4 text-sm text-slate-400">No apps yet.</p>
              ) : apps.map((a) => (
                <div key={a.id} className="flex items-center gap-3 py-3">
                  <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                    {a.logo ? <img src={a.logo} alt="" className="h-full w-full object-cover" /> : a.name.slice(0, 2).toUpperCase()}
                  </span>
                  <span className="text-sm font-medium text-slate-800">{a.name}</span>
                  {a.is_default && <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-600">Default</span>}
                </div>
              ))}
            </div>
          </Card>

          <div className="flex justify-end">
            <button onClick={logout} className="inline-flex items-center gap-2 text-sm font-medium text-red-600 hover:text-red-700">
              <LogOut className="h-4 w-4" /> Sign out
            </button>
          </div>
        </div>
      )}

      {/* Sticky save bar */}
      {tab === "account" && loaded && (
        <div className="fixed bottom-0 left-0 right-0 z-20 border-t border-slate-200 bg-white/90 backdrop-blur md:left-64">
          <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <span className="text-xs text-slate-400">{form.org_name && initials}</span>
            <div className="flex items-center gap-3">
              {saved && <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><Check className="h-3.5 w-3.5" /> Saved</span>}
              <button
                onClick={save}
                disabled={saving}
                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              >
                {saving && <Loader2 className="h-4 w-4 animate-spin" />} Save changes
              </button>
            </div>
          </div>
        </div>
      )}
    </motion.div>
  );
}

function TextField({
  label, value, onChange, placeholder, clearable,
}: { label: string; value: string | null; onChange: (v: string) => void; placeholder?: string; clearable?: boolean }) {
  return (
    <div>
      <label className="text-sm font-medium text-slate-700">{label}</label>
      <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2.5 focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100">
        <input
          value={value ?? ""}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="min-w-0 flex-1 text-sm text-slate-800 outline-none placeholder:text-slate-300"
        />
        {clearable && value && (
          <button onClick={() => onChange("")} className="shrink-0 text-slate-300 hover:text-slate-500" title="Clear">×</button>
        )}
      </div>
    </div>
  );
}

function SelectField({
  label, value, onChange, options, placeholder,
}: { label: string; value: string | null; onChange: (v: string) => void; options: string[]; placeholder?: string }) {
  return (
    <div>
      <label className="text-sm font-medium text-slate-700">{label}</label>
      <select
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value)}
        className={`mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 ${value ? "text-slate-800" : "text-slate-300"}`}
      >
        <option value="" disabled>{placeholder ?? "Select"}</option>
        {options.map((o) => <option key={o} value={o} className="text-slate-800">{o}</option>)}
      </select>
    </div>
  );
}

function Toggle({ on, onChange }: { on: boolean; onChange: () => void }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      onClick={onChange}
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors ${on ? "bg-indigo-600" : "bg-slate-200"}`}
    >
      <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${on ? "translate-x-[22px]" : "translate-x-0.5"}`} />
    </button>
  );
}
