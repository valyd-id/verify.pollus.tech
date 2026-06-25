import { useEffect, useMemo, useRef, useState } from "react";
import { ShieldCheck, Clock, Server, Zap, Lock, LifeBuoy, Mail, CalendarDays, Check, ArrowRight } from "lucide-react";
import { api, type App, type Workflow } from "../api";

/**
 * Full "Set up your Valyd integration" wizard — ported from the canonical
 * new-flow design (valyd-setup-wizard.html): campaign switcher, intro, the
 * Connect → Configure → Integrate stepper, contact view, and the recipe.
 * Adapted to our architecture (Managed Identity by Valyd vs Verify Fresh) and
 * wired to api.createWorkflow on the final step.
 */

type Product = "sso" | "verify";
type Mode = "hosted" | "standalone";
type Recheck = "per_action" | "scheduled" | "expiry";
type Storage = "store" | "recapture";
type View = "intro" | "contact" | "flow";

const SERVICE_LABEL: Record<string, string> = {
  id_verification: "Government ID + OCR",
  liveness: "Liveness (anti-spoof)",
  face_match: "Face match to ID",
  age: "Age band check",
  credential: "Professional license",
  location: "Location of service (geofence)",
  vin: "VIN lookup (vehicle match)",
};
const CHECK_DESC: Record<string, string> = {
  id_verification: "OCR and authenticity from a government ID. Returns the holder's name, DOB and portrait.",
  liveness: "Passive check that the selfie is a live person, not a photo or screen.",
  face_match: "1:1 match of the selfie against the ID portrait.",
  age: "Confirms an age band (18+, 21+) from date of birth.",
  credential: "Looks up a professional license in the state registry and returns its live status.",
  location: "Confirms the person is inside a geofence at the moment of the action.",
};
const PREVIEW_DESC: Record<string, string> = {
  vin: "Matches the driver to the assigned vehicle by VIN at pickup / handoff. In active development — wire for it now, results arrive once it ships.",
};
// Checks that map to a real workflow feature (selectable). `vin` is preview-only.
const REAL_CHECKS = ["id_verification", "liveness", "face_match", "age", "credential", "location"];
const ORDER = REAL_CHECKS;

type Vertical = {
  label: string;
  subject: { singular: string; plural: string };
  defaults: string[];
  previewAvailable: string[];
  recheckDefault: Recheck | null;
  scope: string;
  recheckReason: string;
};

const VERTICALS: Record<string, Vertical> = {
  home_health: {
    label: "Home Health",
    subject: { singular: "caregiver", plural: "caregivers" },
    defaults: ["id_verification", "liveness", "face_match", "credential"],
    previewAvailable: ["location"],
    recheckDefault: "scheduled",
    scope:
      "Valyd confirms the caregiver's identity and that the license is currently active, and feeds that into your EVV system (Sandata, HHAeXchange, etc.) — Valyd is the identity + presence layer, not the EVV record itself. The live-license recheck matters most: a lapsed license invalidates the EVV record and the visit becomes unbillable.",
    recheckReason: "Keep the license status fresh so a lapse never invalidates a billed visit.",
  },
  pharmacy: {
    label: "Pharmacy / EPCS",
    subject: { singular: "prescriber", plural: "prescribers" },
    defaults: ["id_verification", "liveness", "face_match", "credential"],
    previewAvailable: ["location"],
    recheckDefault: "per_action",
    scope:
      "Valyd provides identity proofing, the biometric “something you are” factor, and live license status for an EPCS signing workflow. EPCS certification, credential issuance, and the audit-trail format stay with your EPCS platform. Confirm Valyd's face match clears the DEA false-match-rate bar (≤ 0.001).",
    recheckReason: "Confirm the license is active at the moment of each controlled-substance signing.",
  },
  trucker: {
    label: "Trucking / Carrier",
    subject: { singular: "driver", plural: "drivers" },
    defaults: ["id_verification", "liveness", "face_match"],
    previewAvailable: ["location", "vin"],
    recheckDefault: "per_action",
    scope:
      "Valyd confirms the driver is a real, live, ID-matched person at the moment of pickup. Plate and equipment matching are out of scope; VIN lookup is in active development to match the driver to the assigned tractor at handoff. Confirm whether the credential registry covers CDL / FMCSA before enabling the license check.",
    recheckReason: "Re-verify the driver at each high-value pickup, not just at onboarding.",
  },
  money: {
    label: "Money Exchange",
    subject: { singular: "customer", plural: "customers" },
    defaults: ["id_verification", "liveness", "face_match", "age"],
    previewAvailable: ["location"],
    recheckDefault: null,
    scope:
      "Valyd does the CIP identity layer — a real, live, ID-verified, age-checked human, with an auditable verification record. Sanctions / PEP screening and SAR/CTR filing are not part of these APIs — your AML stack owns those.",
    recheckReason: "",
  },
};
const VERTICAL_KEYS = Object.keys(VERTICALS);

export function WorkflowWizard({ app, onClose, onCreated }: { app: App; onClose: () => void; onCreated: (w: Workflow) => void }) {
  const [V, setV] = useState<string>("home_health");
  const [view, setView] = useState<View>("intro");
  const [product, setProduct] = useState<Product>("sso");
  const [mode, setMode] = useState<Mode | null>(null);
  const [services, setServices] = useState<string[]>(VERTICALS.home_health.defaults.slice());
  const [recheck, setRecheck] = useState<Recheck | null>(VERTICALS.home_health.recheckDefault);
  const [recheckInterval, setRecheckInterval] = useState<"daily" | "weekly">("daily");
  const [storage, setStorage] = useState<Storage>("store");
  const [name, setName] = useState("");
  const [stepIdx, setStepIdx] = useState(0);
  const [activeTab, setActiveTab] = useState<"node" | "curl">("node");
  const [creating, setCreating] = useState(false);
  const [created, setCreated] = useState<Workflow | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const startedRef = useRef(false);

  const vert = VERTICALS[V];
  const plural = vert.subject.plural;
  const hasCredential = services.includes("credential");
  const previewItems = vert.previewAvailable.filter((s) => !REAL_CHECKS.includes(s)); // display-only (e.g. vin)

  const resetForVertical = (v: string) => {
    const d = VERTICALS[v];
    setProduct("sso");
    setMode(null);
    setServices(d.defaults.slice());
    setRecheck(d.recheckDefault);
    setRecheckInterval("daily");
    setStorage("store");
    setName("");
    setStepIdx(0);
    setCreated(null);
    setError(null);
    startedRef.current = false;
    setView("intro");
  };

  // Conditional step flow.
  const flow = useMemo(() => {
    const steps: string[] = ["product"];
    if (product === "verify") steps.push("mode");
    steps.push("checks");
    if (hasCredential) steps.push("recheck");
    if (product === "verify" && mode === "standalone") steps.push("storage");
    steps.push("recipe");
    return steps;
  }, [product, mode, hasCredential]);

  const idx = Math.min(stepIdx, flow.length - 1);
  const step = flow[idx];
  const beforeRecipe = idx === flow.length - 2;

  const ready = (() => {
    if (step === "product") return !!product;
    if (step === "mode") return !!mode;
    if (step === "checks") return services.length > 0;
    if (step === "recheck") return !!recheck;
    if (step === "storage") return !!storage;
    return true;
  })();

  const settings = (): Record<string, unknown> => ({
    product,
    mode: product === "verify" ? mode : null,
    reuse: product === "sso",
    recheck: hasCredential ? recheck : null,
    recheck_interval: recheck === "scheduled" ? recheckInterval : null,
    storage: product === "verify" && mode === "standalone" ? storage : null,
    auto_approve: true,
  });

  const createNow = () => {
    if (creating) return;
    setCreating(true);
    setError(null);
    const wfName = name.trim() || `${vert.label} ${product === "sso" ? "identity" : "verification"}`;
    api.createWorkflow(app.id, wfName, services, settings()).then((r) => {
      setCreating(false);
      if (r.success && r.data) {
        setCreated(r.data.workflow);
        onCreated(r.data.workflow);
      } else {
        setError(r.error?.message ?? "Could not create the workflow.");
      }
    });
  };

  // Reaching the recipe (Integrate) step creates the workflow exactly once.
  useEffect(() => {
    if (view === "flow" && step === "recipe" && !startedRef.current) {
      startedRef.current = true;
      createNow();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [view, step]);

  const toggleService = (s: string) => {
    setServices((prev) => {
      const nextArr = prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s];
      return [...nextArr].sort((a, b) => ORDER.indexOf(a) - ORDER.indexOf(b));
    });
  };

  const next = () => {
    if (!ready || creating) return;
    setStepIdx((i) => Math.min(flow.length - 1, i + 1));
  };
  const back = () => setStepIdx((i) => Math.max(0, i - 1));
  const restart = () => {
    setStepIdx(0);
    setCreated(null);
    setError(null);
    startedRef.current = false;
    setView("flow");
  };

  const workflowId = created?.id ?? "wf_…";
  const stepperActive = step === "product" || step === "mode" ? 1 : step === "checks" || step === "recheck" ? 2 : 3;

  const copy = (txt: string) => {
    navigator.clipboard?.writeText(txt).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1400);
    });
  };

  return (
    <div className="vw-page">
      <style>{WIZARD_CSS}</style>
      <div className="vw">
        <header className="vw-top">
          <span className="vw-mark">VAL<b>YD</b></span>
          <span className="vw-crumb">integration setup · {app.name}</span>
          <div className="vw-campaign">
            <label>Campaign</label>
            <select value={V} onChange={(e) => { setV(e.target.value); resetForVertical(e.target.value); }}>
              {VERTICAL_KEYS.map((k) => <option key={k} value={k}>{VERTICALS[k].label}</option>)}
            </select>
          </div>
          <button className="vw-x" onClick={onClose} aria-label="Close" title="Back to workflows">✕</button>
        </header>

        <div className="vw-grid">
          <section className="vw-stage">
            {view !== "flow" ? (
              <div className="vw-eyebrow"><span>{vert.label}</span><span className="vw-tick">{view === "intro" ? "welcome" : "contact"}</span></div>
            ) : (
              <>
                <div className="vw-eyebrow"><span>{vert.label}</span><span className="vw-tick">{step === "recipe" ? "ready" : `step ${stepperActive} / 3`}</span></div>
                <Stepper active={stepperActive} />
              </>
            )}

            {/* ---------- INTRO ---------- */}
            {view === "intro" && (
              <>
                <div className="vw-intro-head">
                  <div>
                    <h1 className="vw-head">Set up your Valyd integration</h1>
                    <p className="vw-sub">This is your integration workspace. We'll walk through three short steps and generate the code you need to drop Valyd into your product.</p>
                  </div>
                  <div className="vw-hero" aria-hidden="true"><HeroArt /></div>
                </div>

                <div className="vw-callout">
                  <div className="vw-callout-ic"><ShieldCheck size={48} strokeWidth={1.3} /></div>
                  <div>
                    <div className="vw-cal-eyebrow">Get started with confidence</div>
                    <h2 className="vw-cal-h">500 free verifications, on us</h2>
                    <p className="vw-cal-p">Your team gets 500 free verifications to test the full flow in production before committing — plenty of room to wire it up, demo it, and stress-test it end-to-end.</p>
                    <div className="vw-checks">
                      <span className="vw-c"><Check size={14} /> No credit card required</span>
                      <span className="vw-c"><Check size={14} /> Full product access</span>
                      <span className="vw-c"><Check size={14} /> Upgrade anytime</span>
                    </div>
                  </div>
                </div>

                <div className="vw-whatsnext">
                  <h3>What's next?</h3>
                  <div className="vw-feat-grid">
                    <Feat icon={<Zap size={22} strokeWidth={1.7} />} cls="teal" t="Quick to integrate" p="Most teams are up and running in under an hour using our SDK and APIs." />
                    <Feat icon={<Lock size={22} strokeWidth={1.7} />} cls="purple" t="Built for security" p="Enterprise-grade security and privacy by design — your users' data stays protected." />
                    <Feat icon={<LifeBuoy size={22} strokeWidth={1.7} />} cls="mint" t="We're here to help" p="Need guidance? Our team is ready to help you ship with confidence." />
                  </div>
                </div>

                <div className="vw-intro-cta">
                  <button className="vw-btn ghost" onClick={() => setView("contact")}>Talk to us first</button>
                  <button className="vw-btn primary" onClick={() => { setStepIdx(0); setView("flow"); }}>Get started <ArrowRight size={15} /></button>
                </div>
              </>
            )}

            {/* ---------- CONTACT ---------- */}
            {view === "contact" && (
              <>
                <h1 className="vw-head">Let's talk</h1>
                <p className="vw-sub">Valyd supports hybrid architectures, custom retention policies, customer-managed keys, private cloud deployments, and more. Reach out and we'll tailor an integration to your stack.</p>
                <div className="vw-contact-grid">
                  <a className="vw-contact" href="mailto:hello@valyd.id">
                    <span className="vw-contact-ic teal"><Mail size={26} strokeWidth={1.5} /></span>
                    <span><span className="vw-contact-k">Email</span><span className="vw-contact-v">hello@valyd.id</span></span>
                  </a>
                  <a className="vw-contact" href="https://calendar.app.google/RAsWewDxZqXjdbsV6" target="_blank" rel="noopener noreferrer">
                    <span className="vw-contact-ic purple"><CalendarDays size={26} strokeWidth={1.5} /></span>
                    <span><span className="vw-contact-k">Live walkthrough</span><span className="vw-contact-v">Book a demo →</span></span>
                  </a>
                </div>
                <div className="vw-nav"><button className="vw-btn ghost" onClick={() => setView("intro")}>← Back to setup</button><span className="vw-spacer" /></div>
              </>
            )}

            {/* ---------- PRODUCT ---------- */}
            {view === "flow" && step === "product" && (
              <>
                <h1 className="vw-head">How should {plural} verify?</h1>
                <p className="vw-sub">The one choice that changes everything — whether a verified identity sticks around to be reused, or every action is checked fresh.</p>
                <div className="vw-opts">
                  <Opt active={product === "sso"} recommended icon={<ShieldCheck size={30} strokeWidth={1.5} />} iconCls="teal"
                    title="Managed Identity by Valyd" pill="Login with Valyd"
                    desc={`${cap(plural)} sign in with Valyd. When they need a check, they verify once here — the result joins their Valyd identity and is reusable everywhere. Returning users re-verify with a selfie only.`}
                    best="Best for most teams — simplest for users, reusable across apps, strong privacy."
                    onClick={() => { setProduct("sso"); setMode(null); }} />
                  <Opt active={product === "verify"} icon={<Clock size={30} strokeWidth={1.5} />} iconCls="purple"
                    title="Verify Fresh Every Time" pill="Verify"
                    desc="No account, nothing retained. Identity and credential checks run in the moment and are discarded after verification."
                    best="Best for high-privacy workflows and one-time interactions."
                    onClick={() => setProduct("verify")} />
                  <Opt active={false} disabled icon={<Server size={30} strokeWidth={1.5} />} iconCls="mint"
                    title="Self-Managed Infrastructure" pill="Coming soon"
                    desc="You host and manage biometric services and credential storage within your own infrastructure. Full control with operational responsibility."
                    best="Best for orgs with existing identity infrastructure or specific regulatory requirements."
                    onClick={() => {}} />
                </div>
                {product === "sso" && (
                  <div className="vw-note teal">
                    Managed logs the user in with Valyd. Register your login app at <a href="https://dev.pollus.tech" target="_blank" rel="noopener noreferrer">dev.pollus.tech</a> to get a <b>client_id</b> / <b>client_secret</b>, then pass the user's Valyd access token when you create a verify session.
                  </div>
                )}
              </>
            )}

            {/* ---------- MODE ---------- */}
            {view === "flow" && step === "mode" && (
              <>
                <h1 className="vw-head">Whose screen captures the face?</h1>
                <p className="vw-sub">Hand the camera step to Valyd, or keep your own UI and call us behind the scenes.</p>
                <div className="vw-opts">
                  <Opt active={mode === "hosted"} title="Use Valyd's capture page" pill="Hosted"
                    desc="Redirect the person to a Valyd page that handles ID scan, selfie and the rest. You get a signed result back. No camera UI to build, no images touch your servers."
                    onClick={() => setMode("hosted")} />
                  <Opt active={mode === "standalone"} title="Keep your own UI" pill="Standalone"
                    desc="Capture images in your app and call our REST endpoints server-to-server. Synchronous result on the same request. Full control of the experience."
                    onClick={() => setMode("standalone")} />
                </div>
              </>
            )}

            {/* ---------- CHECKS ---------- */}
            {view === "flow" && step === "checks" && (
              <>
                <h1 className="vw-head">What does each verification check?</h1>
                <p className="vw-sub">Pre-filled for {vert.label}. Toggle anything you don't need. {product === "sso" ? "These run on verify and attach to the Valyd identity." : product === "verify" && mode === "hosted" ? "These become a Workflow you reference by id." : "Each maps to one endpoint, or one combined call."}</p>
                <div className="vw-opts">
                  {REAL_CHECKS.map((s) => (
                    <Opt key={s} check active={services.includes(s)} title={SERVICE_LABEL[s]} desc={CHECK_DESC[s]} onClick={() => toggleService(s)} />
                  ))}
                </div>
                {hasCredential && (
                  <div className="vw-note">⚠ The license is matched to the <b>name read off the verified ID</b> — never a name your code supplies — so someone can't pass off another person's license.</div>
                )}
                {previewItems.length > 0 && (
                  <div className="vw-preview-group">
                    <div className="vw-pg-h">In preview · in active development</div>
                    <div className="vw-opts">
                      {previewItems.map((s) => (
                        <button key={s} className="vw-opt preview" disabled type="button">
                          <span className="vw-dot sq" />
                          <span className="vw-body"><span className="vw-ot">{SERVICE_LABEL[s]}</span><span className="vw-od">{PREVIEW_DESC[s]}</span></span>
                          <span className="vw-pill preview">preview</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </>
            )}

            {/* ---------- RECHECK ---------- */}
            {view === "flow" && step === "recheck" && (
              <>
                <h1 className="vw-head">How often re-check the license?</h1>
                <p className="vw-sub">{vert.recheckReason} A registry lookup returns the live status and expiry, so you can re-run it on whatever rhythm fits the risk.</p>
                <div className="vw-opts">
                  <Opt active={recheck === "per_action"} title="At every action" pill="strictest" desc="Confirm the license is active each time it's used. Strongest — you never trust a cached 'valid.'" onClick={() => setRecheck("per_action")} />
                  <Opt active={recheck === "scheduled"} title="On a schedule" desc="Re-run daily or weekly to catch a mid-cycle suspension between renewals. Balances cost and coverage." onClick={() => setRecheck("scheduled")} />
                  <Opt active={recheck === "expiry"} title="At expiry only" desc="Re-check when the stored expiry date approaches. Lightest — misses suspensions before the printed expiry." onClick={() => setRecheck("expiry")} />
                </div>
                {recheck === "scheduled" && (
                  <div className="vw-seg">
                    <button className={recheckInterval === "daily" ? "on" : ""} onClick={() => setRecheckInterval("daily")}>Daily</button>
                    <button className={recheckInterval === "weekly" ? "on" : ""} onClick={() => setRecheckInterval("weekly")}>Weekly</button>
                  </div>
                )}
              </>
            )}

            {/* ---------- STORAGE ---------- */}
            {view === "flow" && step === "storage" && (
              <>
                <h1 className="vw-head">Store the verified portrait?</h1>
                <p className="vw-sub">In Standalone you hold the data, so you decide whether to keep the portrait Valyd returns. Keeping it lets returning {plural} re-verify with just a face.</p>
                <div className="vw-opts">
                  <Opt active={storage === "store"} title="Keep the portrait — face-only re-checks" pill="face only" desc="Store the portrait from the first ID scan. Returning people verify with a selfie alone. Lighter friction; you own the stored image." onClick={() => setStorage("store")} />
                  <Opt active={storage === "recapture"} title="Re-scan the ID each time" desc="Don't retain images. Every verification starts from a fresh ID scan. Nothing stored, nothing to breach." onClick={() => setStorage("recapture")} />
                </div>
              </>
            )}

            {/* ---------- RECIPE ---------- */}
            {view === "flow" && step === "recipe" && (
              <div className="vw-recipe">
                <h1 className="vw-head">Your integration recipe</h1>
                <p className="vw-sub">{creating ? "Creating your workflow…" : created ? "Your workflow is live. Drop the snippet into your backend to make the first call." : "Everything below is scoped to what the API does today."}</p>

                <p className="vw-rk">Product</p>
                <p className="vw-rv"><span className="vw-accent">{product === "sso" ? "Managed Identity by Valyd" : `Verify Fresh · ${mode === "hosted" ? "Hosted" : "Standalone"}`}</span> &nbsp;·&nbsp; <span className="vw-mono">{product === "sso" ? "idp.valyd.id + verify.pollus.tech" : "verify.pollus.tech"}</span></p>

                <p className="vw-rk">Workflow ID</p>
                <p className="vw-rv"><span className="vw-mono vw-accent">{creating ? "creating…" : workflowId}</span></p>

                <p className="vw-rk">Checks</p>
                <p className="vw-rv">{services.map((s) => SERVICE_LABEL[s]).join(", ")}</p>

                {hasCredential && recheck && (
                  <>
                    <p className="vw-rk">License recheck</p>
                    <p className="vw-rv">{{ per_action: "At every action", scheduled: `On a schedule (${recheckInterval})`, expiry: "At expiry only" }[recheck]}</p>
                  </>
                )}
                {product === "verify" && mode === "standalone" && (
                  <>
                    <p className="vw-rk">Biometric storage</p>
                    <p className="vw-rv">{storage === "store" ? "Portrait kept — face-only re-checks" : "Not retained — ID re-scan each time"}</p>
                  </>
                )}

                <div className="vw-tabs">
                  <button className={activeTab === "node" ? "on" : ""} onClick={() => setActiveTab("node")}>Node SDK</button>
                  <button className={activeTab === "curl" ? "on" : ""} onClick={() => setActiveTab("curl")}>cURL</button>
                </div>
                <div className="vw-codewrap">
                  <button className={`vw-copy${copied ? " ok" : ""}`} onClick={() => copy(snippet(product, mode, activeTab, services, workflowId, vert))}>{copied ? "copied" : "copy"}</button>
                  <pre>{snippet(product, mode, activeTab, services, workflowId, vert)}</pre>
                </div>

                <div className="vw-scope">{vert.scope}</div>

                <ol className="vw-steps-next">
                  {nextSteps(product, mode, workflowId).map((li, i) => <li key={i} dangerouslySetInnerHTML={{ __html: li }} />)}
                </ol>

                {error && (
                  <div className="vw-note" style={{ marginTop: 14 }}>
                    {error} <button className="vw-link" onClick={createNow}>Try again</button>
                  </div>
                )}

                <div className="vw-nav">
                  <button className="vw-restart" onClick={restart}>↺ start over</button>
                  <span className="vw-spacer" />
                  <button className="vw-btn primary" disabled={creating || !created} onClick={onClose}>{creating ? "Creating…" : "Done — back to workflows"}</button>
                </div>
              </div>
            )}

            {/* nav for the configuring steps (not intro/contact/recipe) */}
            {view === "flow" && step !== "recipe" && (
              <div className="vw-nav">
                {idx > 0 && <button className="vw-btn ghost" onClick={back}>← Back</button>}
                <span className="vw-spacer" />
                {!ready && <span className="vw-hint">pick an option to continue</span>}
                {beforeRecipe && <input className="vw-name" placeholder="Workflow name (optional)" value={name} onChange={(e) => setName(e.target.value)} />}
                <button className="vw-btn primary" onClick={next} disabled={!ready}>{beforeRecipe ? "Get my code" : "Continue →"}</button>
              </div>
            )}
          </section>

          <aside className={`vw-panel${view === "flow" && step === "recipe" && created ? " sealed" : ""}`}>
            <div className="vw-panel-h"><span>Your setup</span><span className="vw-state"><span className="vw-led" />{view === "flow" && step === "recipe" && created ? "sealed" : "building"}</span></div>
            <Row k="Product" v={product === "sso" ? "Managed (Login with Valyd)" : "Verify Fresh"} set />
            <Row k="Mode" v={product === "verify" ? (mode ? (mode === "hosted" ? "Hosted" : "Standalone") : "—") : "—"} set={product === "verify" && !!mode} />
            <div className="vw-chips-h">Checks</div>
            <div className="vw-chips">
              {services.length ? services.map((s) => <span key={s} className="vw-chip">{s}</span>) : <span className="vw-faint">Nothing selected</span>}
              {previewItems.map((s) => <span key={s} className="vw-chip prev">{s}</span>)}
            </div>
            <Row k="Recheck" v={hasCredential && recheck ? { per_action: "every action", scheduled: recheckInterval, expiry: "at expiry" }[recheck] : "—"} set={hasCredential && !!recheck} />
            <Row k="Portrait" v={product === "verify" && mode === "standalone" ? (storage === "store" ? "stored" : "not kept") : "—"} set={product === "verify" && mode === "standalone"} />
          </aside>
        </div>
      </div>
    </div>
  );
}

const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

function Stepper({ active }: { active: number }) {
  const items = [
    { n: 1, t: "Connect", s: "Choose your environment" },
    { n: 2, t: "Configure", s: "Set your options" },
    { n: 3, t: "Integrate", s: "Add the code" },
  ];
  return (
    <div className="vw-stepper">
      {items.map((it, i) => (
        <div key={it.n} className={`vw-step-chip${it.n < active ? " done" : it.n === active ? " active" : ""}`} style={{ display: "contents" }}>
          <div className="vw-step-chip-inner">
            <div className="vw-step-num">{it.n}</div>
            <div className="vw-step-text"><span className="vw-step-t">{it.t}</span><span className="vw-step-s">{it.s}</span></div>
          </div>
          {i < items.length - 1 && <div className={`vw-step-line${it.n < active ? " lit" : ""}`} />}
        </div>
      ))}
    </div>
  );
}

function Opt({ active, title, desc, pill, best, check, recommended, disabled, icon, iconCls, onClick }: {
  active: boolean; title: string; desc: string; pill?: string; best?: string; check?: boolean; recommended?: boolean; disabled?: boolean; icon?: React.ReactNode; iconCls?: string; onClick: () => void;
}) {
  return (
    <button className={`vw-opt${active ? " on" : ""}${disabled ? " disabled" : ""}`} aria-pressed={active} disabled={disabled} onClick={onClick}>
      <span className={`vw-dot${check ? " sq" : ""}`} />
      {icon && <span className={`vw-icon ${iconCls ?? "teal"}`}>{icon}</span>}
      <span className="vw-body">
        <span className="vw-title-row"><span className="vw-ot">{title}</span>{recommended && <span className="vw-rec">Recommended</span>}</span>
        <span className="vw-od">{desc}</span>
        {best && <span className="vw-best"><Check size={13} /> {best}</span>}
      </span>
      {pill && <span className={`vw-pill${disabled ? " muted" : ""}`}>{pill}</span>}
    </button>
  );
}

function Feat({ icon, cls, t, p }: { icon: React.ReactNode; cls: string; t: string; p: string }) {
  return (
    <div className="vw-feat">
      <div className={`vw-feat-ic ${cls}`}>{icon}</div>
      <div className="vw-feat-t">{t}</div>
      <p className="vw-feat-p">{p}</p>
    </div>
  );
}

function Row({ k, v, set }: { k: string; v: string; set?: boolean }) {
  return <div className="vw-row"><span className="vw-k">{k}</span><span className={`vw-v${set && v !== "—" ? " set" : ""}`}>{v}</span></div>;
}

function HeroArt() {
  return (
    <svg width="220" height="160" viewBox="0 0 240 180" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="ig1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#2bd4c0" stopOpacity=".22" /><stop offset="1" stopColor="#2bd4c0" stopOpacity=".02" /></linearGradient>
        <linearGradient id="ig2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#5aa8ff" stopOpacity=".35" /><stop offset="1" stopColor="#2bd4c0" stopOpacity=".15" /></linearGradient>
      </defs>
      <rect x="22" y="28" width="160" height="108" rx="14" fill="url(#ig1)" stroke="#2bd4c0" strokeOpacity=".35" strokeWidth="1.2" />
      <rect x="92" y="62" width="118" height="92" rx="14" fill="url(#ig2)" stroke="#5aa8ff" strokeOpacity=".55" strokeWidth="1.4" />
      <g stroke="#9ad4ff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" fill="none" transform="translate(135 95)">
        <path d="M-14 -10 L-22 0 L-14 10" /><path d="M14 -10 L22 0 L14 10" /><path d="M-4 12 L4 -12" />
      </g>
    </svg>
  );
}

// ---------- recipe snippet + next-steps generators ----------
function snippet(product: Product, mode: Mode | null, tab: "node" | "curl", services: string[], wf: string, vert: Vertical): string {
  if (product === "sso") return tab === "curl" ? ssoCurl(wf) : ssoNode(wf);
  if (mode === "hosted") return tab === "curl" ? hostedCurl(wf) : hostedNode(wf);
  return tab === "curl" ? standaloneCurl(services, vert) : standaloneNode(services, vert);
}

function ssoNode(wf: string): string {
  return `import { Valyd } from "valyd-verify-sdk";

const valyd = new Valyd({
  clientId: process.env.VALYD_CLIENT_ID,        // from dev.pollus.tech
  clientSecret: process.env.VALYD_CLIENT_SECRET, // server-side only
  apiKey: process.env.VALYD_API_KEY,             // your verify API key
});

// 1) Send the user to Valyd to sign in
const url = valyd.auth.getAuthorizationUrl({
  scope: ["profile", "verifications"],
  redirectUri: "https://app.example.com/auth/valyd/callback",
});
// res.redirect(url)

// 2) On callback, exchange the code for the user's Valyd token
const { accessToken, user } = await valyd.auth.exchangeCode(code);
// user.pollus_id identifies the signed-in Valyd user

// 3) Run a verification on verify — hand over the user's Valyd token
const session = await valyd.verify.sessions.create({
  workflowId: "${wf}",
  vendorData: user.pollus_id,
  valydAccessToken: accessToken,
  redirectUrl: "https://app.example.com/verify/done",
});
// res.redirect(session.url)

// 4) Read the authoritative decision (status + checks + identity)
const decision = await valyd.verify.sessions.decision(session.sessionId);`;
}
function ssoCurl(wf: string): string {
  return `# 1) Redirect the user to Valyd to sign in (browser)
#    https://idp.valyd.id/api/auth/tpsso/authorize?client_id=...&redirect_uri=...&response_type=code&scope=profile%20verifications

# 2) Exchange the code for the user's Valyd token (server-side)
curl -X POST https://idp.valyd.id/api/auth/tpsso/token \\
  -H "Content-Type: application/json" \\
  -d '{ "grant_type":"authorization_code","code":"...","client_id":"...","client_secret":"..." }'
# -> { access_token, user: { pollus_id } }

# 3) Create a verify session bound to that Valyd user
curl -X POST https://verify.pollus.tech/api/v2/session \\
  -H "X-API-Key: $VALYD_API_KEY" -H "Content-Type: application/json" \\
  -d '{ "workflow_id":"${wf}", "valyd_access_token":"<access_token>",
        "vendor_data":"<pollus_id>", "redirect_url":"https://app.example.com/verify/done" }'
# -> { url } : redirect the user to it

# 4) Read the decision
curl https://verify.pollus.tech/api/v2/session/<session_id>/decision \\
  -H "X-API-Key: $VALYD_API_KEY"`;
}
function hostedNode(wf: string): string {
  return `import { Valyd } from "valyd-verify-sdk";

const valyd = new Valyd({
  apiKey: process.env.VALYD_API_KEY,             // server-side only
  webhookSecret: process.env.VALYD_WEBHOOK_SECRET,
});

// 1) Create a session and send the person to the hosted page
const session = await valyd.verify.sessions.create({
  workflowId: "${wf}",
  redirectUrl: "https://app.example.com/verify/done",
  callback:    "https://api.example.com/webhooks/valyd",
  vendorData:  "customer_123",
});
// res.redirect(session.url)

// 2) In your webhook, pull the authoritative decision
const event = valyd.verify.webhooks.constructEvent(rawBody, headers);
const decision = await valyd.verify.sessions.decision(event.session_id);
// decision.status -> APPROVED | DECLINED ; decision.checks[]`;
}
function hostedCurl(wf: string): string {
  return `# 1) Create a session against your workflow
curl -X POST https://verify.pollus.tech/api/v2/session \\
  -H "X-API-Key: $VALYD_API_KEY" -H "Content-Type: application/json" \\
  -d '{ "workflow_id":"${wf}",
        "redirect_url":"https://app.example.com/verify/done",
        "callback":"https://api.example.com/webhooks/valyd",
        "vendor_data":"customer_123" }'
# -> { url } : redirect the person's browser to it

# 2) After the webhook fires, read the full result
curl https://verify.pollus.tech/api/v2/session/<session_id>/decision \\
  -H "X-API-Key: $VALYD_API_KEY"`;
}
function standaloneNode(services: string[], vert: Vertical): string {
  if (services.includes("credential")) {
    return `import { Valyd, readImage } from "valyd-verify-sdk";
const valyd = new Valyd({ apiKey: process.env.VALYD_API_KEY });

// One combined call: ID + liveness + face match + license.
// License is matched to the OCR'd name, never a client-supplied one.
const result = await valyd.verify.standalone.kycCredential({
  frontImage: readImage("./id_front.jpg"),
  selfie:     readImage("./selfie.jpg"),
  licenseState:  "CA",
  licenseNumber: "A12345",
});
// result.status -> passed | failed | review
// result.checks[] -> id_verification, liveness, face_match, credential`;
  }
  const lines = [
    `import { Valyd, readImage } from "valyd-verify-sdk";`,
    `const valyd = new Valyd({ apiKey: process.env.VALYD_API_KEY });`,
    ``,
    `// Run the checks you need, server-to-server`,
  ];
  if (services.includes("id_verification")) lines.push(`const id   = await valyd.verify.standalone.idVerification({ frontImage: readImage("./id_front.jpg") });`);
  if (services.includes("liveness")) lines.push(`const live = await valyd.verify.standalone.liveness({ image: readImage("./selfie.jpg") });`);
  if (services.includes("face_match")) lines.push(`const fm   = await valyd.verify.standalone.faceMatch({ idImage: id.check.data.portrait, selfie: readImage("./selfie.jpg") });`);
  if (services.includes("age")) lines.push(`const age  = await valyd.verify.standalone.ageVerification({ dob: id.check.data.dob, bands: ["is_18_plus"] });`);
  if (services.includes("location")) lines.push(`const loc  = await valyd.verify.standalone.location({ latitude, longitude });`);
  lines.push(``, `// each returns { status, check: { score, data } }`);
  void vert;
  return lines.join("\n");
}
function standaloneCurl(services: string[], vert: Vertical): string {
  void vert;
  if (services.includes("credential")) {
    return `# Combined ID + liveness + face match + license, one request
curl -X POST https://verify.pollus.tech/api/v2/kyc-credential \\
  -H "X-API-Key: $VALYD_API_KEY" \\
  -F "front_image=@./id_front.jpg" \\
  -F "selfie=@./selfie.jpg" \\
  -F "license_state=CA" \\
  -F "license_number=A12345"`;
  }
  return `# ID verification
curl -X POST https://verify.pollus.tech/api/v2/id-verification \\
  -H "X-API-Key: $VALYD_API_KEY" -F "front_image=@./id_front.jpg"

# Liveness
curl -X POST https://verify.pollus.tech/api/v2/liveness \\
  -H "X-API-Key: $VALYD_API_KEY" -F "image=@./selfie.jpg"`;
}

function nextSteps(product: Product, mode: Mode | null, wf: string): string[] {
  if (product === "sso") {
    return [
      `Register your app at <code>dev.pollus.tech</code> to get a <b>client_id</b> and <b>client_secret</b>. <a href="https://dev.pollus.tech" target="_blank" rel="noopener noreferrer">Register at dev.pollus.tech →</a>`,
      `Add a <b>Login with Valyd</b> button that redirects to the authorize URL with your scopes.`,
      `Exchange the returned code for an <b>access token</b> (server-side).`,
      `Create a verify session against <code>${wf}</code> with the user's <b>valyd_access_token</b>, then read the decision.`,
    ];
  }
  return [
    `Copy your <b>App API key</b> (shown in this console) and keep it server-side only.`,
    mode === "hosted"
      ? `Create a session against <code>${wf}</code> and redirect the person to <code>session.url</code>.`
      : `Call the endpoint above from your backend with the captured images.`,
    `Read the result from the webhook or <code>GET /session/{id}/decision</code>.`,
  ];
}

const WIZARD_CSS = `
.vw-page{display:flex;justify-content:center;padding:4px 0 56px;
  --ink:#0E1318;--ink2:#141B22;--ink3:#1B232C;--ink4:#222C36;--line:#28323D;--line2:#37444F;--txt:#E8EEF3;--dim:#9AA8B4;--faint:#5E6B77;--sig:#2BD4C0;--wash:rgba(43,212,192,.12);--review:#E0A33C;--purple:#B89CFF;--mint:#6EE0B4;--r:14px;--rs:9px;--fm:"IBM Plex Mono",ui-monospace,monospace;}
.vw{width:100%;max-width:1180px;color:var(--txt)}
.vw-top{display:flex;align-items:center;gap:14px;margin-bottom:16px;flex-wrap:wrap}
.vw-mark{font-weight:800;letter-spacing:.16em;font-size:18px}.vw-mark b{color:var(--sig)}
.vw-crumb{font-family:var(--fm);font-size:12px;color:var(--dim)}
.vw-campaign{margin-left:auto;display:flex;align-items:center;gap:9px}
.vw-campaign label{font-family:var(--fm);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--faint)}
.vw-campaign select{appearance:none;background:var(--ink2);color:var(--txt);border:1px solid var(--line2);border-radius:var(--rs);padding:7px 28px 7px 11px;font:inherit;font-size:13px;font-weight:500;cursor:pointer}
.vw-x{background:var(--ink2);border:1px solid var(--line2);color:var(--dim);border-radius:50%;width:30px;height:30px;cursor:pointer}
.vw-grid{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}
@media(max-width:820px){.vw-grid{grid-template-columns:1fr}}
.vw-stage{background:linear-gradient(180deg,var(--ink2),#11171D);border:1px solid var(--line);border-radius:var(--r);padding:28px;min-height:420px;display:flex;flex-direction:column}
.vw-eyebrow{font-family:var(--fm);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--sig);display:flex;justify-content:space-between;margin-bottom:14px}
.vw-tick{color:var(--faint)}
.vw-head{font-weight:700;font-size:25px;margin:0 0 7px;letter-spacing:-.012em}
.vw-sub{color:var(--dim);font-size:14.5px;margin:0 0 24px;line-height:1.55;max-width:60ch}
/* stepper */
.vw-stepper{display:flex;align-items:flex-start;gap:14px;margin:2px 0 24px}
.vw-step-chip-inner{display:flex;align-items:flex-start;gap:11px;flex:0 0 auto}
.vw-step-num{flex:none;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-family:var(--fm);font-size:12.5px;font-weight:600;border:1.5px solid var(--line2);color:var(--faint)}
.vw-step-chip.active .vw-step-num{background:var(--sig);color:#06231F;border-color:var(--sig);box-shadow:0 0 0 4px rgba(43,212,192,.15)}
.vw-step-chip.done .vw-step-num{background:rgba(43,212,192,.18);color:var(--sig);border-color:rgba(43,212,192,.45)}
.vw-step-text{display:flex;flex-direction:column;gap:1px}
.vw-step-t{font-size:14px;font-weight:600;color:var(--faint);line-height:1.25}
.vw-step-s{font-size:12px;color:var(--faint);line-height:1.3}
.vw-step-chip.active .vw-step-t{color:var(--sig)}.vw-step-chip.active .vw-step-s{color:var(--sig);opacity:.85}
.vw-step-chip.done .vw-step-t{color:var(--txt)}
.vw-step-line{flex:1 1 auto;height:1.5px;background:linear-gradient(90deg,var(--line2),var(--line));margin-top:14px;border-radius:2px;min-width:20px}
.vw-step-line.lit{background:linear-gradient(90deg,var(--sig),rgba(43,212,192,.25))}
/* opts */
.vw-opts{display:flex;flex-direction:column;gap:12px}
.vw-opt{position:relative;display:flex;align-items:flex-start;gap:14px;text-align:left;background:var(--ink3);border:1px solid var(--line);border-radius:var(--rs);padding:16px 18px;cursor:pointer;color:var(--txt);font:inherit;width:100%;transition:border-color .15s,background .15s}
.vw-opt:hover{border-color:var(--line2);background:var(--ink4)}
.vw-opt.on{border-color:var(--sig);background:var(--wash)}
.vw-opt.disabled{opacity:.55;cursor:not-allowed}.vw-opt.disabled:hover{border-color:var(--line);background:var(--ink3)}
.vw-opt.preview.disabled{opacity:.8;cursor:default}
.vw-dot{flex:none;width:18px;height:18px;border-radius:50%;border:1.5px solid var(--line2);margin-top:3px;display:grid;place-items:center}
.vw-dot.sq{border-radius:5px}
.vw-opt.on .vw-dot{border-color:var(--sig)}
.vw-opt.on .vw-dot::after{content:"";width:9px;height:9px;border-radius:50%;background:var(--sig)}
.vw-opt.on .vw-dot.sq::after{border-radius:2px}
.vw-icon{flex:none;width:60px;height:60px;border-radius:14px;display:grid;place-items:center;margin-top:1px}
.vw-icon.teal{background:rgba(43,212,192,.08);border:1px solid rgba(43,212,192,.25);color:var(--sig)}
.vw-icon.purple{background:rgba(168,120,255,.08);border:1px solid rgba(168,120,255,.28);color:var(--purple)}
.vw-icon.mint{background:rgba(80,220,170,.07);border:1px solid rgba(80,220,170,.25);color:var(--mint)}
.vw-body{display:flex;flex-direction:column;gap:5px;flex:1;min-width:0}
.vw-title-row{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.vw-ot{font-weight:600;font-size:15px}
.vw-od{color:var(--dim);font-size:13px;line-height:1.5}
.vw-best{display:flex;align-items:flex-start;gap:7px;color:var(--dim);font-size:12.5px;margin-top:7px;line-height:1.45}
.vw-best svg{flex:none;margin-top:2px;color:var(--sig);opacity:.8}
.vw-pill{align-self:center;font-family:var(--fm);font-size:10.5px;color:var(--sig);background:var(--wash);border:1px solid rgba(43,212,192,.4);border-radius:20px;padding:3px 9px;white-space:nowrap}
.vw-pill.muted{color:var(--dim);background:rgba(255,255,255,.04);border-color:var(--line)}
.vw-pill.preview{color:var(--review);background:rgba(224,163,60,.1);border-color:rgba(224,163,60,.32)}
.vw-rec{font-family:var(--fm);font-size:9px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--sig);background:var(--wash);border:1px solid rgba(43,212,192,.4);border-radius:20px;padding:3px 7px}
.vw-note{margin-top:16px;font-size:12.5px;color:#E9C98C;background:rgba(224,163,60,.07);border:1px solid rgba(224,163,60,.25);border-radius:var(--rs);padding:12px 14px;line-height:1.5}
.vw-note.teal{color:#BCEDE6;background:var(--wash);border-color:rgba(43,212,192,.3)}
.vw-note b{color:var(--txt)}
.vw-note a,.vw-link{color:var(--sig);text-decoration:none;font-weight:600;background:none;border:none;cursor:pointer;font:inherit;padding:0}
.vw-note a:hover,.vw-link:hover{text-decoration:underline}
.vw-preview-group{margin-top:18px;padding-top:15px;border-top:1px dashed var(--line)}
.vw-pg-h{font-family:var(--fm);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--review);margin-bottom:11px}
.vw-opt.preview.on,.vw-opt.preview{border-color:var(--line)}
.vw-seg{display:inline-flex;margin-top:14px;border:1px solid var(--line2);border-radius:var(--rs);overflow:hidden}
.vw-seg button{background:var(--ink3);color:var(--dim);border:0;padding:8px 16px;cursor:pointer;font:inherit;font-size:13px}
.vw-seg button.on{background:var(--sig);color:#06231f;font-weight:600}
/* intro */
.vw-intro-head{display:grid;grid-template-columns:1fr 240px;gap:24px;align-items:start;margin-bottom:22px}
.vw-hero{display:flex;justify-content:flex-end}
@media(max-width:760px){.vw-intro-head{grid-template-columns:1fr}.vw-hero{display:none}}
.vw-callout{display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:center;padding:22px 24px;background:linear-gradient(180deg,rgba(43,212,192,.06),rgba(43,212,192,.015));border:1px solid rgba(43,212,192,.28);border-radius:var(--r);margin-bottom:26px}
.vw-callout-ic{width:96px;height:96px;display:grid;place-items:center;color:var(--sig);opacity:.85}
@media(max-width:560px){.vw-callout{grid-template-columns:1fr}.vw-callout-ic{width:64px;height:64px}}
.vw-cal-eyebrow{font-family:var(--fm);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--sig);margin-bottom:6px}
.vw-cal-h{font-size:23px;font-weight:700;letter-spacing:-.012em;margin:0 0 8px}
.vw-cal-p{margin:0 0 14px;color:var(--dim);font-size:14px;line-height:1.55}
.vw-checks{display:flex;flex-wrap:wrap;gap:16px 22px}
.vw-c{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--sig)}
.vw-whatsnext h3{font-size:17px;font-weight:600;margin:0 0 16px}
.vw-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding-bottom:20px;border-bottom:1px solid var(--line)}
@media(max-width:680px){.vw-feat-grid{grid-template-columns:1fr}}
.vw-feat{display:flex;flex-direction:column;gap:10px}
.vw-feat-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center}
.vw-feat-ic.teal{background:rgba(43,212,192,.08);border:1px solid rgba(43,212,192,.25);color:var(--sig)}
.vw-feat-ic.purple{background:rgba(168,120,255,.08);border:1px solid rgba(168,120,255,.28);color:var(--purple)}
.vw-feat-ic.mint{background:rgba(80,220,170,.07);border:1px solid rgba(80,220,170,.25);color:var(--mint)}
.vw-feat-t{font-size:15px;font-weight:600}
.vw-feat-p{font-size:13px;color:var(--dim);line-height:1.55;margin:0}
.vw-intro-cta{display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:22px}
/* contact */
.vw-contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:560px){.vw-contact-grid{grid-template-columns:1fr}}
.vw-contact{display:flex;align-items:center;gap:16px;padding:18px 20px;background:var(--ink3);border:1px solid var(--line);border-radius:var(--rs);text-decoration:none;color:var(--txt)}
.vw-contact:hover{border-color:var(--sig);background:var(--ink4)}
.vw-contact-ic{flex:none;width:48px;height:48px;border-radius:12px;display:grid;place-items:center}
.vw-contact-ic.teal{background:rgba(43,212,192,.08);border:1px solid rgba(43,212,192,.25);color:var(--sig)}
.vw-contact-ic.purple{background:rgba(168,120,255,.08);border:1px solid rgba(168,120,255,.28);color:var(--purple)}
.vw-contact-k{display:block;font-family:var(--fm);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--faint)}
.vw-contact-v{display:block;font-size:16px;font-weight:600;margin-top:3px}
/* nav */
.vw-nav{display:flex;align-items:center;gap:12px;margin-top:auto;padding-top:24px;flex-wrap:wrap}
.vw-spacer{flex:1}
.vw-hint{font-family:var(--fm);font-size:11.5px;color:var(--faint)}
.vw-name{background:var(--ink);border:1px solid var(--line2);border-radius:var(--rs);color:var(--txt);padding:9px 12px;font:inherit;font-size:13px;width:210px}
.vw-btn{display:inline-flex;align-items:center;gap:7px;border-radius:var(--rs);padding:11px 18px;cursor:pointer;font:inherit;font-size:14px;font-weight:600;border:1px solid transparent}
.vw-btn.ghost{background:transparent;border-color:var(--line2);color:var(--dim)}.vw-btn.ghost:hover{color:var(--txt);border-color:var(--dim)}
.vw-btn.primary{background:var(--sig);color:#06231f}.vw-btn.primary:hover{background:#3ee0cc}
.vw-btn:disabled{opacity:.45;cursor:not-allowed}
.vw-restart{background:none;border:none;color:var(--faint);font-family:var(--fm);font-size:12px;cursor:pointer;text-decoration:underline;text-underline-offset:3px}
.vw-restart:hover{color:var(--dim)}
/* panel */
.vw-panel{background:var(--ink2);border:1px solid var(--line);border-radius:var(--r);padding:18px;position:sticky;top:16px;transition:border-color .3s}
.vw-panel.sealed{border-color:rgba(43,212,192,.45)}
.vw-panel-h{display:flex;justify-content:space-between;align-items:center;font-family:var(--fm);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:14px}
.vw-state{display:flex;align-items:center;gap:7px}
.vw-led{width:8px;height:8px;border-radius:50%;background:var(--line2)}
.vw-panel.sealed .vw-led{background:var(--sig);box-shadow:0 0 8px var(--sig)}
.vw-panel.sealed .vw-state{color:var(--sig)}
.vw-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13px}
.vw-k{font-family:var(--fm);font-size:11.5px;letter-spacing:.04em;text-transform:uppercase;color:var(--faint)}
.vw-v{color:var(--faint);text-align:right}.vw-v.set{color:var(--txt);font-weight:500}
.vw-chips-h{font-family:var(--fm);font-size:11px;color:var(--faint);text-transform:uppercase;letter-spacing:.08em;margin:14px 0 9px}
.vw-chips{display:flex;flex-wrap:wrap;gap:7px}
.vw-chip{font-family:var(--fm);font-size:11px;color:var(--txt);background:var(--ink3);border:1px solid var(--line);border-radius:7px;padding:5px 9px}
.vw-chip.prev{border-style:dashed;color:var(--review)}
.vw-faint{color:var(--faint);font-size:12.5px;font-style:italic}
/* recipe */
.vw-recipe .vw-rk{font-family:var(--fm);font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--faint);margin:0 0 5px}
.vw-rv{font-size:15px;font-weight:500;margin:0 0 16px}
.vw-rv .vw-accent{color:var(--sig);font-weight:600}
.vw-mono{font-family:var(--fm);font-size:13px;color:var(--dim)}
.vw-tabs{display:flex;gap:5px;margin:6px 0 0}
.vw-tabs button{font-family:var(--fm);font-size:12px;padding:7px 14px;border-radius:7px 7px 0 0;cursor:pointer;background:transparent;border:1px solid transparent;border-bottom:none;color:var(--faint)}
.vw-tabs button.on{color:var(--txt);background:#0B0F14;border-color:var(--line);border-bottom-color:#0B0F14}
.vw-codewrap{position:relative;background:#0B0F14;border:1px solid var(--line);border-radius:0 var(--rs) var(--rs) var(--rs);overflow:hidden}
.vw-codewrap pre{margin:0;padding:16px;overflow-x:auto;font-family:var(--fm);font-size:12.5px;line-height:1.6;color:#C6D2DC}
.vw-copy{position:absolute;top:9px;right:9px;font-family:var(--fm);font-size:11px;background:var(--ink3);border:1px solid var(--line2);color:var(--dim);padding:5px 10px;border-radius:6px;cursor:pointer}
.vw-copy:hover,.vw-copy.ok{color:var(--sig);border-color:var(--sig)}
.vw-scope{margin:18px 0 0;background:var(--wash);border:1px solid rgba(43,212,192,.28);border-radius:var(--rs);padding:14px 15px;font-size:13.5px;color:#BCEDE6;line-height:1.55}
.vw-steps-next{margin:18px 0 0;padding:0;list-style:none;counter-reset:s}
.vw-steps-next li{counter-increment:s;position:relative;padding:9px 0 9px 34px;font-size:13.5px;color:var(--dim);border-bottom:1px dashed var(--line)}
.vw-steps-next li:last-child{border-bottom:0}
.vw-steps-next li::before{content:counter(s);position:absolute;left:0;top:8px;width:22px;height:22px;border-radius:50%;background:var(--ink3);border:1px solid var(--line2);color:var(--sig);font-family:var(--fm);font-size:11px;display:grid;place-items:center}
.vw-steps-next li b{color:var(--txt)}
.vw-steps-next code{font-family:var(--fm);font-size:12.5px;color:var(--sig);background:var(--ink);padding:1px 6px;border-radius:5px}
.vw-steps-next a{color:var(--sig);text-decoration:none;font-weight:600}.vw-steps-next a:hover{text-decoration:underline}
`;
