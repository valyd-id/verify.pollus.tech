import { useState, type ReactNode } from "react";
import { Check, Copy, X } from "lucide-react";
import { motion } from "framer-motion";
import { overlay, dialog } from "./motion";

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="text-xl font-semibold text-slate-900 sm:text-2xl">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

export function Card({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <div className={`rounded-xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)] ${className}`}>{children}</div>;
}

export function Button({ children, onClick, variant = "primary", disabled, type = "button", className = "" }: {
  children: ReactNode; onClick?: () => void; variant?: "primary" | "ghost" | "danger"; disabled?: boolean; type?: "button" | "submit"; className?: string;
}) {
  const styles = {
    primary: "bg-indigo-600 text-white hover:bg-indigo-700",
    ghost: "border border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
    danger: "border border-red-200 bg-white text-red-600 hover:bg-red-50",
  }[variant];
  return (
    <button type={type} onClick={onClick} disabled={disabled} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors disabled:opacity-50 ${styles} ${className}`}>
      {children}
    </button>
  );
}

export function CopyField({ label, value, mono = true }: { label?: string; value: string; mono?: boolean }) {
  const [copied, setCopied] = useState(false);
  const copy = () => { navigator.clipboard.writeText(value); setCopied(true); setTimeout(() => setCopied(false), 1500); };
  return (
    <div>
      {label && <div className="mb-1 text-xs font-medium text-slate-500">{label}</div>}
      <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
        <code className={`min-w-0 flex-1 truncate text-[13px] text-slate-700 ${mono ? "font-mono" : ""}`}>{value}</code>
        <button onClick={copy} className="shrink-0 text-slate-400 hover:text-indigo-600" title="Copy">
          {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"><X className="h-4 w-4" /></button>
        </div>
        {children}
      </motion.div>
    </div>
  );
}

export function StatusPill({ status }: { status: string }) {
  const map: Record<string, string> = {
    APPROVED: "bg-emerald-50 text-emerald-700", DECLINED: "bg-red-50 text-red-700",
    IN_REVIEW: "bg-amber-50 text-amber-700", EXPIRED: "bg-slate-100 text-slate-500",
    ABANDONED: "bg-slate-100 text-slate-500", IN_PROGRESS: "bg-sky-50 text-sky-700", NOT_STARTED: "bg-slate-100 text-slate-500",
  };
  return <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${map[status] ?? "bg-slate-100 text-slate-600"}`}>{status.replace(/_/g, " ").toLowerCase()}</span>;
}
