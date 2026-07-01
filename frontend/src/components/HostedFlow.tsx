import { useCallback, useEffect, useRef, useState } from "react";
import { motion } from "framer-motion";
import {
  ShieldCheck,
  Loader2,
  CheckCircle2,
  XCircle,
  Clock,
  Timer,
  CircleDashed,
  AlertTriangle,
} from "lucide-react";
import { X } from "lucide-react";
import { CameraCapture } from "./CameraCapture";
import { LocationCapture, type LocationBody } from "./LocationCapture";
import { CredentialStep } from "./CredentialStep";
import { getState, getResult, uploadDocument, runCheck, reuseFace, type HostedResult } from "../lib/api";
import { getEmbedContext, postToParent } from "../lib/embed";

type DocType = "id_front" | "id_back" | "selfie";
type Phase = "loading" | "intro" | "login_required" | "reuse" | "capture" | "location" | "credential" | "processing" | "result" | "redirecting" | "fatal";

const DOC_META: Record<DocType, { facing: "user" | "environment"; overlay: "card" | "oval"; title: string; hint: string; optional?: boolean }> = {
  id_front: { facing: "environment", overlay: "card", title: "Scan your ID — front", hint: "Place the front of your document inside the frame." },
  id_back: { facing: "environment", overlay: "card", title: "Scan your ID — back", hint: "Now flip it over and capture the back.", optional: true },
  selfie: { facing: "user", overlay: "oval", title: "Take a selfie", hint: "Center your face and look straight at the camera." },
};

const FEATURE_NAME: Record<string, string> = {
  id_verification: "Document check",
  liveness: "Liveness",
  face_match: "Face match",
  age: "Age check",
  credential: "License check",
  location: "Location",
};

const FEATURE_DOCS: Record<string, DocType[]> = {
  id_verification: ["id_front", "id_back"],
  liveness: ["selfie"],
  face_match: ["id_front", "selfie"],
};

const TERMINAL = ["APPROVED", "DECLINED", "EXPIRED", "ABANDONED", "IN_REVIEW"];

const STATUS_UI: Record<string, { icon: typeof CheckCircle2; color: string; bg: string; title: string; sub: string }> = {
  APPROVED: { icon: CheckCircle2, color: "text-emerald-600", bg: "bg-emerald-50", title: "Verification Approved", sub: "Your identity has been successfully verified." },
  DECLINED: { icon: XCircle, color: "text-red-600", bg: "bg-red-50", title: "Verification Declined", sub: "We could not verify your identity from the information provided." },
  IN_REVIEW: { icon: Clock, color: "text-amber-600", bg: "bg-amber-50", title: "Under Review", sub: "Your verification needs a short manual review." },
  EXPIRED: { icon: Timer, color: "text-slate-500", bg: "bg-slate-100", title: "Session Expired", sub: "This verification session timed out before it was completed." },
  ABANDONED: { icon: CircleDashed, color: "text-slate-500", bg: "bg-slate-100", title: "Verification Cancelled", sub: "This session was closed before completion." },
};

const badgeClass = (s: string) =>
  s === "passed" ? "bg-emerald-50 text-emerald-700"
  : s === "failed" ? "bg-red-50 text-red-700"
  : s === "review" ? "bg-amber-50 text-amber-700"
  : s === "running" ? "bg-sky-50 text-sky-700"
  : "bg-secondary text-muted-foreground";

function docsForFeatures(features: string[], account = false): DocType[] {
  const seen = new Set<DocType>();
  const out: DocType[] = [];
  for (const f of features) {
    // ACCOUNT session: face match uses the stored Valyd vector → selfie only, no ID.
    const docs = account && f === "face_match" ? (["selfie"] as DocType[]) : (FEATURE_DOCS[f] ?? []);
    for (const d of docs) if (!seen.has(d)) { seen.add(d); out.push(d); }
  }
  return out;
}

function siteLabel(url: string): string {
  try { return new URL(url).host; } catch { return "your application"; }
}

function Shell({ children }: { children: React.ReactNode }) {
  const embedded = getEmbedContext().embedded;
  return (
    <div className="min-h-screen flex items-center justify-center p-4" style={{ background: "var(--gradient-hero)" }}>
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
        className="relative w-full max-w-md rounded-3xl bg-card border border-border shadow-[var(--shadow-lift)] p-6 sm:p-8"
      >
        {embedded && (
          <button
            onClick={() => postToParent({ type: "close" })}
            aria-label="Close"
            className="absolute right-4 top-4 z-10 grid h-8 w-8 place-items-center rounded-full bg-secondary text-muted-foreground hover:text-foreground transition-colors"
          >
            <X className="h-4 w-4" />
          </button>
        )}
        {children}
      </motion.div>
    </div>
  );
}

export function HostedFlow({ token }: { token: string }) {
  const [phase, setPhase] = useState<Phase>("loading");
  const [features, setFeatures] = useState<string[]>([]);
  const [redirectUrl, setRedirectUrl] = useState<string | null>(null);
  const [docs, setDocs] = useState<DocType[]>([]);
  const [docIndex, setDocIndex] = useState(0);
  const [captures, setCaptures] = useState<Partial<Record<DocType, string>>>({});
  const [checks, setChecks] = useState<Record<string, string>>({});
  const [kycName, setKycName] = useState<string | null>(null);
  const [result, setResult] = useState<HostedResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fatal, setFatal] = useState<string>("");
  const started = useRef(false);

  const finish = useCallback((r: HostedResult) => {
    setResult(r);
    // Embedded (popup/modal): report the result to the opener/parent and let the
    // SDK tear down — never navigate the popup/iframe to the integrator's page.
    if (getEmbedContext().embedded) {
      postToParent({ type: "complete", sessionId: r.session_id, status: r.status });
      setPhase("result");
      return;
    }
    if (r.redirect_url) {
      setPhase("redirecting");
      const sep = r.redirect_url.includes("?") ? "&" : "?";
      const dest = `${r.redirect_url}${sep}session_id=${encodeURIComponent(r.session_id)}&status=${encodeURIComponent(r.status)}`;
      window.setTimeout(() => { window.location.href = dest; }, 1800);
    } else {
      setPhase("result");
    }
  }, []);

  // Initial load: resolve the session, decide where to begin.
  useEffect(() => {
    if (started.current) return;
    started.current = true;
    postToParent({ type: "ready" });
    const fail = (msg: string) => { setFatal(msg); setPhase("fatal"); postToParent({ type: "error", message: msg }); };
    if (!token) { fail("This verification link is missing its session. Please use the link provided to you."); return; }
    (async () => {
      const r = await getState(token);
      if (!r.success || !r.data) {
        // Session may already be terminal (state blocks those) — try the result endpoint.
        const rr = await getResult(token);
        if (rr.success && rr.data && TERMINAL.includes(rr.data.status)) { finish(rr.data); return; }
        fail(r.error?.message || "This verification link is invalid or has expired.");
        return;
      }
      // Skip steps already satisfied for this Valyd user (reused from the account) —
      // don't re-collect an ID or re-run a check that's already `passed`.
      const steps: Array<{ feature: string; status: string }> = (r.data as any).steps ?? [];
      const passedSet = new Set(steps.filter((s) => s.status === "passed").map((s) => s.feature));
      const pending = r.data.features.filter((f) => !passedSet.has(f));
      const active = pending.length ? pending : r.data.features;
      const isAccount = !!r.data.pollus_id;
      setFeatures(active);
      setRedirectUrl(r.data.redirect_url);
      setDocs(docsForFeatures(active, isAccount));
      if (passedSet.size) {
        const seed: Record<string, string> = {};
        steps.forEach((s) => { if (s.status === "passed") seed[s.feature] = "passed"; });
        setChecks(seed);
      }
      if (TERMINAL.includes(r.data.status)) {
        const rr = await getResult(token);
        if (rr.success && rr.data) { finish(rr.data); return; }
      }
      // Managed Identity by Valyd: the session is bound to the Valyd identity at
      // creation (the integrator passes the user's token), so pollus_id is set here.
      // Returning users with a stored record do selfie-only; first-timers fall
      // through to the full workflow. A Managed session with no identity means the
      // user wasn't logged in — ask them to sign in on the app first.
      if (r.data.reuse && !r.data.pollus_id) setPhase("login_required");
      else if (r.data.reuse && r.data.reuse_eligible) setPhase("reuse");
      else setPhase("intro");
    })();
  }, [token, finish]);

  const hasCredential = features.includes("credential");
  const hasLocation = features.includes("location");
  const kycFeatures = features.filter((f) => f !== "credential");

  const surfaceTerminal = useCallback(async (): Promise<boolean> => {
    const rr = await getResult(token);
    if (rr.success && rr.data && TERMINAL.includes(rr.data.status)) { finish(rr.data); return true; }
    return false;
  }, [token, finish]);

  // Phase 1: upload documents + run the KYC checks (everything except credential),
  // capturing the OCR'd name. Then branch to the license form, or finish.
  const runKyc = useCallback(async (caps: Partial<Record<DocType, string>>, location: LocationBody | null) => {
    setPhase("processing");
    setError(null);
    try {
      for (const [type, image] of Object.entries(caps)) {
        if (!image) continue;
        const u = await uploadDocument(token, type, image);
        if (!u.success) throw new Error(u.error?.message || `Upload of ${type} failed`);
      }
      let name: string | null = null;
      for (const f of kycFeatures) {
        setChecks((c) => ({ ...c, [f]: "running" }));
        // Location carries the captured coordinates (or a denial flag); every
        // other KYC check runs against the already-uploaded documents.
        const body: Record<string, unknown> = f === "location" ? (location ?? { denied: true }) : {};
        const res = await runCheck(token, f.replaceAll("_", "-"), body);
        if (!res.success || !res.data) { setChecks((c) => ({ ...c, [f]: "failed" })); throw new Error(res.error?.message || `${FEATURE_NAME[f] ?? f} failed`); }
        setChecks((c) => ({ ...c, [f]: res.data!.check.status }));
        if (f === "id_verification") name = (res.data.check.data?.fields?.full_name as string) ?? null;
        if (["APPROVED", "DECLINED", "EXPIRED", "ABANDONED"].includes(res.data.session_status)) break;
      }
      // A failed required KYC check already decided the session → show the result.
      if (await surfaceTerminal()) return;
      if (hasCredential) { setKycName(name); setPhase("credential"); return; }
      const rr = await getResult(token);
      if (!rr.success || !rr.data) throw new Error(rr.error?.message || "Could not load your result");
      finish(rr.data);
    } catch (e) {
      if (await surfaceTerminal()) return;
      setError((e as Error).message);
      setPhase("capture");
    }
  }, [token, kycFeatures, hasCredential, finish, surfaceTerminal]);

  // Phase 2: run the credential check with the license details (the name is
  // supplied server-side from the KYC result in KYC+License workflows).
  const runCredentialCheck = useCallback(async (payload: Record<string, string>) => {
    setPhase("processing");
    setError(null);
    try {
      setChecks((c) => ({ ...c, credential: "running" }));
      const res = await runCheck(token, "credential", payload);
      if (!res.success || !res.data) { setChecks((c) => ({ ...c, credential: "failed" })); throw new Error(res.error?.message || "License check failed"); }
      setChecks((c) => ({ ...c, credential: res.data!.check.status }));
      const rr = await getResult(token);
      if (!rr.success || !rr.data) throw new Error(rr.error?.message || "Could not load your result");
      finish(rr.data);
    } catch (e) {
      if (await surfaceTerminal()) return;
      setError((e as Error).message);
      setPhase("credential");
    }
  }, [token, finish, surfaceTerminal]);

  // After all documents are captured, run KYC (if any), else go straight to the
  // license form (credential-only workflows have no documents).
  const afterCaptures = useCallback((caps: Partial<Record<DocType, string>>) => {
    setCaptures(caps);
    // Location needs a user gesture + permission prompt, so capture it on its own
    // step before running the checks.
    if (hasLocation) { setPhase("location"); return; }
    if (kycFeatures.length) void runKyc(caps, null);
    else setPhase("credential");
  }, [hasLocation, kycFeatures.length, runKyc]);

  // After the location step: continue into the remaining KYC checks (which include
  // the location check itself), or jump to the license form.
  const afterLocation = useCallback((location: LocationBody) => {
    if (kycFeatures.length) void runKyc(captures, location);
    else setPhase("credential");
  }, [kycFeatures.length, runKyc, captures]);

  const startFlow = useCallback(() => {
    if (docs.length) setPhase("capture");
    else if (hasLocation) setPhase("location");
    else if (kycFeatures.length) void runKyc(captures, null);
    else setPhase("credential");
  }, [docs.length, hasLocation, kycFeatures.length, runKyc, captures]);

  const onReuseSelfie = useCallback(async (dataUrl: string) => {
    setPhase("processing");
    setError(null);
    const r = await reuseFace(token, dataUrl);
    if (!r.success || !r.data) { setError(r.error?.message || "Could not match your face."); setPhase("reuse"); return; }
    if (!r.data.match) { setError("That didn't match your Valyd identity. Please try again."); setPhase("reuse"); return; }
    const rr = await getResult(token);
    if (rr.success && rr.data) { finish(rr.data); return; }
    setError("Verified, but could not load your result."); setPhase("reuse");
  }, [token, finish]);

  const onCaptured = (dataUrl: string) => {
    const type = docs[docIndex];
    const next = { ...captures, [type]: dataUrl };
    setCaptures(next);
    if (docIndex + 1 < docs.length) setDocIndex(docIndex + 1);
    else afterCaptures(next);
  };
  const onSkip = () => {
    if (docIndex + 1 < docs.length) setDocIndex(docIndex + 1);
    else afterCaptures(captures);
  };

  // --- render ---
  if (phase === "loading") {
    return <Shell><div className="py-10 text-center"><Loader2 className="h-8 w-8 animate-spin text-primary mx-auto" /><p className="mt-4 text-sm text-muted-foreground">Loading your verification…</p></div></Shell>;
  }

  if (phase === "fatal") {
    return (
      <Shell>
        <div className="text-center">
          <div className="mx-auto h-14 w-14 rounded-2xl bg-red-50 flex items-center justify-center text-red-600 mb-4"><AlertTriangle className="h-7 w-7" /></div>
          <h2 className="font-display text-2xl text-foreground">Can't open this verification</h2>
          <p className="mt-2 text-sm text-muted-foreground">{fatal}</p>
        </div>
      </Shell>
    );
  }

  if (phase === "login_required") {
    return (
      <Shell>
        <div className="text-center">
          <div className="mx-auto h-14 w-14 rounded-2xl bg-primary-soft border border-border flex items-center justify-center text-primary mb-4"><ShieldCheck className="h-7 w-7" strokeWidth={1.75} /></div>
          <h2 className="font-display text-3xl text-foreground">Sign in with Valyd first</h2>
          <p className="mt-2 text-sm text-muted-foreground">This verification is linked to your Valyd identity. Please return to the app and sign in with Valyd, then start the verification again.</p>
          {error && <p className="mt-4 text-sm text-red-600">{error}</p>}
        </div>
      </Shell>
    );
  }

  if (phase === "reuse") {
    return (
      <Shell>
        <CameraCapture key="reuse-selfie" facingMode="user" overlay="oval" title="Take a selfie" hint="Match your face to your verified Valyd identity." onCapture={onReuseSelfie} />
        {error && <p className="mt-4 text-center text-sm text-red-600">{error}</p>}
      </Shell>
    );
  }

  if (phase === "intro") {
    return (
      <Shell>
        <div className="text-center">
          <div className="mx-auto h-14 w-14 rounded-2xl bg-primary-soft border border-border flex items-center justify-center text-primary mb-4"><ShieldCheck className="h-7 w-7" strokeWidth={1.75} /></div>
          <h2 className="font-display text-3xl text-foreground">Verify your identity</h2>
          <p className="mt-2 text-sm text-muted-foreground">A few quick steps. Your camera is used only for this verification.</p>
          <div className="mt-6 rounded-2xl border border-border bg-secondary/50 p-4 text-left">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground mb-3">You'll complete</p>
            <ul className="space-y-2">
              {features.map((f) => (
                <li key={f} className="flex items-center gap-2 text-sm text-foreground"><CheckCircle2 className="h-4 w-4 text-primary" /> {FEATURE_NAME[f] ?? f}</li>
              ))}
            </ul>
          </div>
          {error && <p className="mt-4 text-sm text-red-600">{error}</p>}
          <button onClick={startFlow} className="mt-6 w-full rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:opacity-90 transition-opacity">Begin</button>
        </div>
      </Shell>
    );
  }

  if (phase === "capture") {
    const doc = docs[docIndex];
    return (
      <Shell>
        <div className="flex items-center justify-center gap-1.5 mb-5">
          {docs.map((d, i) => (
            <span key={d} className={`h-1.5 rounded-full transition-all ${i === docIndex ? "w-6 bg-primary" : i < docIndex ? "w-6 bg-primary/40" : "w-3 bg-border"}`} />
          ))}
        </div>
        <CameraCapture
          key={doc}
          facingMode={DOC_META[doc].facing}
          overlay={DOC_META[doc].overlay}
          title={DOC_META[doc].title}
          hint={DOC_META[doc].hint}
          onCapture={onCaptured}
          onSkip={DOC_META[doc].optional ? onSkip : undefined}
        />
        {error && <p className="mt-4 text-center text-sm text-red-600">{error}</p>}
      </Shell>
    );
  }

  if (phase === "location") {
    return (
      <Shell>
        <LocationCapture onCapture={afterLocation} />
        {error && <p className="mt-4 text-center text-sm text-red-600">{error}</p>}
      </Shell>
    );
  }

  if (phase === "credential") {
    return (
      <Shell>
        <CredentialStep
          token={token}
          kycName={kycName}
          onSubmit={(payload) => {
            void runCredentialCheck(payload);
          }}
        />
        {error && <p className="mt-4 text-center text-sm text-red-600">{error}</p>}
      </Shell>
    );
  }

  if (phase === "processing") {
    return (
      <Shell>
        <div className="py-4 text-center">
          <Loader2 className="h-10 w-10 animate-spin text-primary mx-auto" />
          <p className="mt-4 text-sm text-muted-foreground">Verifying your identity…</p>
          <div className="mt-6 space-y-2 text-left">
            {features.map((f) => (
              <div key={f} className="flex items-center justify-between rounded-xl border border-border bg-card px-3 py-2.5">
                <span className="text-sm text-foreground">{FEATURE_NAME[f] ?? f}</span>
                <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${badgeClass(checks[f] ?? "pending")}`}>{checks[f] ?? "pending"}</span>
              </div>
            ))}
          </div>
        </div>
      </Shell>
    );
  }

  if (phase === "redirecting") {
    return (
      <Shell>
        <div className="py-8 text-center">
          <CheckCircle2 className="h-12 w-12 text-emerald-600 mx-auto" />
          <h2 className="mt-4 font-display text-2xl text-foreground">Verification complete</h2>
          <p className="mt-2 text-sm text-muted-foreground">Returning you to {siteLabel(redirectUrl ?? "")}…</p>
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground mx-auto mt-4" />
        </div>
      </Shell>
    );
  }

  // result (inline — only when there is no redirect_url)
  if (phase === "result" && result) {
    const ui = STATUS_UI[result.status] ?? { icon: CircleDashed, color: "text-slate-500", bg: "bg-slate-100", title: result.status, sub: "Your verification has been processed." };
    const Icon = ui.icon;
    return (
      <Shell>
        <div className="text-center">
          <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Verification Results</div>
          <div className={`mx-auto mt-4 h-16 w-16 rounded-full ${ui.bg} flex items-center justify-center`}><Icon className={`h-8 w-8 ${ui.color}`} /></div>
          <h2 className={`mt-3 font-display text-2xl ${ui.color}`}>{ui.title}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{ui.sub}</p>
          <div className="mt-5 space-y-2 text-left">
            {result.checks.map((c) => (
              <div key={c.type} className="rounded-xl border border-border bg-card px-3 py-2.5">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-foreground">{FEATURE_NAME[c.type] ?? c.type}</span>
                  <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${badgeClass(c.status)}`}>{c.status}</span>
                </div>
                {c.error && <p className="mt-1 text-xs text-red-600">{c.error}</p>}
              </div>
            ))}
          </div>
          <div className="mt-5 text-left">
            <div className="text-xs text-muted-foreground">Session ID</div>
            <code className="mt-1 block break-all rounded-lg bg-foreground px-3 py-2 text-xs text-background">{result.session_id}</code>
          </div>
          <p className="mt-4 text-xs text-muted-foreground bg-secondary/60 rounded-lg p-3">You can now close this window. The result has been sent to the requesting application.</p>
        </div>
      </Shell>
    );
  }

  return null;
}
