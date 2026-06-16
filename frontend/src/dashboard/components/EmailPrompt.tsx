import { useState } from "react";
import { Mail, Loader2 } from "lucide-react";
import { motion } from "framer-motion";
import { api } from "../api";
import { overlay, dialog } from "./motion";

/**
 * Shown after SSO when Valyd didn't return an email. Entirely optional — the
 * developer can type anything (no validation) or skip it and continue.
 */
export function EmailPrompt({ onDone }: { onDone: () => void }) {
  const [email, setEmail] = useState("");
  const [saving, setSaving] = useState(false);

  const save = async () => {
    setSaving(true);
    await api.setEmail(email);
    setSaving(false);
    onDone();
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-slate-900/40" />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div className="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-indigo-50">
          <Mail className="h-5 w-5 text-indigo-600" />
        </div>
        <h2 className="mt-4 text-center text-base font-semibold text-slate-900">Add your email</h2>
        <p className="mt-1 text-center text-sm text-slate-500">
          We didn't get an email from Valyd. Add one for receipts and notifications, or skip for now.
        </p>

        <input
          type="text"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !saving) void save();
          }}
          placeholder="you@example.com"
          autoFocus
          className="mt-4 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        />

        <div className="mt-4 flex items-center gap-2">
          <button
            onClick={onDone}
            disabled={saving}
            className="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
          >
            Skip
          </button>
          <button
            onClick={save}
            disabled={saving || email.trim() === ""}
            className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {saving && <Loader2 className="h-4 w-4 animate-spin" />}
            Save
          </button>
        </div>
      </motion.div>
    </div>
  );
}
