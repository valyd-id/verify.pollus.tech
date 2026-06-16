import { useEffect, useMemo, useState } from "react";
import { BadgeCheck, Loader2 } from "lucide-react";
import {
  credentialProviders,
  credentialStates,
  type CredentialProvider,
  type CredentialState,
} from "../lib/api";

// vc `required_fields` use a few aliases — normalize them to the param names
// Verify's run/credential expects.
const CANONICAL: Record<string, string> = {
  license_no: "license_number",
  license_number: "license_number",
  first_name: "first_name",
  last_name: "last_name",
  full_name: "full_name",
  npi: "npi",
};
const canonical = (k: string) => CANONICAL[k] ?? k;

const LABELS: Record<string, string> = {
  first_name: "First name",
  last_name: "Last name",
  full_name: "Full name",
  license_number: "License number",
  npi: "NPI number",
};
const label = (param: string) =>
  LABELS[param] ?? param.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

// vc's verification always needs the holder's name; license number is needed for
// the lookup. NPI / extras are optional.
const REQUIRED_PARAMS = new Set(["first_name", "last_name", "full_name", "license_number"]);

const inputClass =
  "w-full rounded-xl border border-border bg-card px-3 py-2.5 text-sm text-foreground outline-none focus:ring-2 focus:ring-primary/30";

/**
 * License-only verification step: pick a state, pick a license type/provider,
 * then fill the required fields. Submits the assembled payload to the caller
 * (HostedFlow), which runs the `credential` check.
 */
// Name params are never collected from the user in KYC mode — the backend uses
// the OCR'd name from the ID document.
const NAME_PARAMS = ["first_name", "last_name", "full_name"];

export function CredentialStep({
  token,
  onSubmit,
  submitting,
  prefill = {},
  kycName = null,
}: {
  token: string;
  onSubmit: (payload: Record<string, string>) => void;
  submitting?: boolean;
  prefill?: Record<string, string>;
  /** When set, the license is verified against this KYC-derived name (locked). */
  kycName?: string | null;
}) {
  const [states, setStates] = useState<CredentialState[]>([]);
  const [providers, setProviders] = useState<CredentialProvider[]>([]);
  const [state, setState] = useState("");
  const [providerCode, setProviderCode] = useState("");
  const [values, setValues] = useState<Record<string, string>>(prefill);
  const [loadingStates, setLoadingStates] = useState(true);
  const [loadingProviders, setLoadingProviders] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let alive = true;
    (async () => {
      setLoadingStates(true);
      const r = await credentialStates(token);
      if (!alive) return;
      setStates(r.data?.states ?? []);
      if (!r.success || !r.data?.states?.length) {
        setErr(r.error?.message ?? "Could not load the list of states. Please try again.");
      }
      setLoadingStates(false);
    })();
    return () => {
      alive = false;
    };
  }, [token]);

  const onStateChange = async (s: string) => {
    setState(s);
    setProviderCode("");
    setProviders([]);
    setErr(null);
    if (!s) return;
    setLoadingProviders(true);
    const r = await credentialProviders(token, s);
    setProviders(r.data?.providers ?? []);
    if (!r.success) setErr(r.error?.message ?? "Could not load license types for this state.");
    setLoadingProviders(false);
  };

  const provider = useMemo(
    () => providers.find((p) => p.provider_code === providerCode) ?? null,
    [providers, providerCode]
  );

  const kyc = !!kycName;

  // Fields to render. In KYC mode the name comes from the ID document, so we drop
  // all name fields and only collect the license number / NPI. Otherwise we add
  // the holder's name (vc always needs it, unless the provider takes full_name).
  const fields = useMemo(() => {
    if (!provider) return [];
    const req = (provider.required_fields ?? []).map(canonical);
    if (kyc) return req.filter((f) => !NAME_PARAMS.includes(f));
    const base = req.includes("full_name") ? [] : ["first_name", "last_name"];
    return Array.from(new Set([...base, ...req]));
  }, [provider, kyc]);

  const submit = () => {
    if (!state) return setErr("Select a state.");
    if (!provider) return setErr("Select a license type.");
    const missing = fields.filter((f) => REQUIRED_PARAMS.has(f) && !(values[f] ?? "").trim());
    if (missing.length) {
      return setErr(`Please fill: ${missing.map(label).join(", ")}.`);
    }
    const payload: Record<string, string> = {
      provider_code: provider.provider_code,
      license_state: provider.state_code || state,
      license_type: provider.credential_name,
    };
    for (const f of fields) {
      const v = (values[f] ?? "").trim();
      if (v) payload[f] = v;
    }
    setErr(null);
    onSubmit(payload);
  };

  return (
    <div>
      <div className="text-center">
        <div className="mx-auto h-14 w-14 rounded-2xl bg-primary-soft border border-border flex items-center justify-center text-primary mb-4">
          <BadgeCheck className="h-7 w-7" strokeWidth={1.75} />
        </div>
        <h2 className="font-display text-2xl text-foreground">Verify your license</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          {kyc
            ? "Select your license, then enter its number. We'll check it against your verified ID."
            : "Select where your license was issued, then enter its details."}
        </p>
      </div>

      {kyc && (
        <div className="mt-5 rounded-xl border border-border bg-secondary/50 px-3 py-2.5 text-left">
          <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Verified identity
          </div>
          <div className="mt-0.5 text-sm font-medium text-foreground">{kycName}</div>
        </div>
      )}

      <div className="mt-6 space-y-4 text-left">
        {/* State */}
        <div>
          <label className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted-foreground">
            State / jurisdiction
          </label>
          <select
            className={inputClass}
            value={state}
            disabled={loadingStates || submitting}
            onChange={(e) => void onStateChange(e.target.value)}
          >
            <option value="">{loadingStates ? "Loading states…" : "Select a state"}</option>
            {states.map((s) => (
              <option key={s.state_code} value={s.state_code}>
                {s.state_name}
              </option>
            ))}
          </select>
        </div>

        {/* License type / provider */}
        {state && (
          <div>
            <label className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted-foreground">
              License type
            </label>
            <select
              className={inputClass}
              value={providerCode}
              disabled={loadingProviders || submitting}
              onChange={(e) => {
                setProviderCode(e.target.value);
                setErr(null);
              }}
            >
              <option value="">
                {loadingProviders ? "Loading license types…" : "Select a license type"}
              </option>
              {providers.map((p) => (
                <option key={p.provider_code} value={p.provider_code}>
                  {p.credential_name} — {p.provider_display_name}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Dynamic fields */}
        {fields.map((f) => (
          <div key={f}>
            <label className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {label(f)}
              {f === "npi" && <span className="ml-1 normal-case text-muted-foreground/70">(optional)</span>}
            </label>
            <input
              className={inputClass}
              type="text"
              value={values[f] ?? ""}
              placeholder={f === "npi" ? "10 digits" : undefined}
              disabled={submitting}
              onChange={(e) => setValues((v) => ({ ...v, [f]: e.target.value }))}
            />
          </div>
        ))}
      </div>

      {err && <p className="mt-4 text-sm text-red-600">{err}</p>}

      <button
        onClick={submit}
        disabled={submitting || loadingStates}
        className="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-60"
      >
        {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
        {submitting ? "Verifying…" : "Verify license"}
      </button>
    </div>
  );
}
