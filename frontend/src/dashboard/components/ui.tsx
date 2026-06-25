import { useState, type ReactNode } from "react";
import { Check, Copy, X } from "lucide-react";
import { motion } from "framer-motion";
import { overlay, dialog } from "./motion";

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="font-display text-xl font-semibold text-foreground sm:text-2xl">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

export function Card({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <div className={`rounded-xl border border-border bg-card shadow-[var(--shadow-soft)] ${className}`}>{children}</div>;
}

export function Button({ children, onClick, variant = "primary", disabled, type = "button", className = "" }: {
  children: ReactNode; onClick?: () => void; variant?: "primary" | "ghost" | "danger"; disabled?: boolean; type?: "button" | "submit"; className?: string;
}) {
  const styles = {
    primary: "bg-primary text-primary-foreground hover:opacity-90",
    ghost: "border border-border-2 bg-card text-foreground hover:bg-secondary",
    danger: "border border-red-500/30 bg-card text-red-400 hover:bg-red-500/10",
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
      {label && <div className="mb-1 text-xs font-medium text-muted-foreground">{label}</div>}
      <div className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2">
        <code className={`min-w-0 flex-1 truncate text-[13px] text-foreground ${mono ? "font-mono" : ""}`}>{value}</code>
        <button onClick={copy} className="shrink-0 text-muted-foreground hover:text-primary" title="Copy">
          {copied ? <Check className="h-4 w-4 text-primary" /> : <Copy className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <motion.div variants={overlay} initial="hidden" animate="show" className="absolute inset-0 bg-black/60" onClick={onClose} />
      <motion.div variants={dialog} initial="hidden" animate="show" className="relative w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-[var(--shadow-lift)]">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="font-display text-lg font-semibold text-foreground">{title}</h3>
          <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-secondary"><X className="h-4 w-4" /></button>
        </div>
        {children}
      </motion.div>
    </div>
  );
}

export function StatusPill({ status }: { status: string }) {
  const map: Record<string, string> = {
    APPROVED: "bg-emerald-500/15 text-emerald-300", DECLINED: "bg-red-500/15 text-red-300",
    IN_REVIEW: "bg-amber-500/15 text-amber-300", EXPIRED: "bg-secondary text-muted-foreground",
    ABANDONED: "bg-secondary text-muted-foreground", IN_PROGRESS: "bg-sky-500/15 text-sky-300", NOT_STARTED: "bg-secondary text-muted-foreground",
  };
  return <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${map[status] ?? "bg-secondary text-muted-foreground"}`}>{status.replace(/_/g, " ").toLowerCase()}</span>;
}
