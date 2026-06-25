import { useEffect, useState } from "react";
import { motion } from "framer-motion";
import { Wallet, Plus, ArrowDownCircle, ArrowUpCircle, RotateCcw, Loader2, Receipt } from "lucide-react";
import { api, type BillingTxn } from "../api";
import { Button, Card, Modal, PageHeader } from "../components/ui";
import { fadeUp, listContainer, listItem } from "../components/motion";
import { useBalance, formatMoney, currencySymbol } from "../balance";

// Turn a ledger reason ("check:credential", "refund:age", "top_up") into a label.
function reasonLabel(t: BillingTxn): string {
  if (t.reason === "top_up") return "Top-up";
  const [, feature] = t.reason.split(":");
  const name = feature ? feature.replaceAll("_", " ") : t.reason;
  if (t.type === "refund") return `Refund — ${name}`;
  if (t.type === "debit") return `API usage — ${name}`;
  return name;
}

const TXN_UI = {
  credit: { icon: ArrowUpCircle, color: "text-emerald-300", bg: "bg-emerald-500/15", sign: "+" },
  refund: { icon: RotateCcw, color: "text-sky-300", bg: "bg-sky-500/15", sign: "+" },
  debit: { icon: ArrowDownCircle, color: "text-muted-foreground", bg: "bg-secondary", sign: "−" },
} as const;

const QUICK = [10, 25, 50, 100];

export function Billing() {
  const { balance, currency, ready, reload } = useBalance();
  const [txns, setTxns] = useState<BillingTxn[]>([]);
  const [loadingTxns, setLoadingTxns] = useState(true);
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState("25");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadTxns = async () => {
    const t = await api.transactions(25);
    if (t.success && t.data) setTxns(t.data.transactions);
    setLoadingTxns(false);
  };
  useEffect(() => { loadTxns(); }, []);

  const topUp = async () => {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) { setError("Enter an amount greater than 0."); return; }
    setBusy(true);
    setError(null);
    const r = await api.topUp(value);
    setBusy(false);
    if (r.success && r.data) {
      setOpen(false);
      setAmount("25");
      await Promise.all([reload(), loadTxns()]); // refresh navbar pill + history
    } else {
      setError(r.error?.message ?? "Top-up failed.");
    }
  };

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-4xl">
      <PageHeader
        title="Billing"
        subtitle="Prepaid balance. Each API call deducts its cost; failed requests on our side are refunded automatically."
      />

      {/* Balance hero */}
      <div className="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-6 text-white shadow-[0_10px_30px_-12px_rgba(79,70,229,0.6)] sm:p-7">
        <div className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10" />
        <div className="pointer-events-none absolute -bottom-16 -right-4 h-40 w-40 rounded-full bg-white/5" />
        <div className="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <div className="flex items-center gap-2 text-sm font-medium text-indigo-100">
              <Wallet className="h-4 w-4" /> Current balance
            </div>
            <div className="mt-2 text-4xl font-semibold tabular-nums sm:text-5xl">
              {ready ? formatMoney(balance, currency) : <Loader2 className="h-8 w-8 animate-spin text-white/70" />}
            </div>
            <p className="mt-2 text-xs text-indigo-100/80">Test mode — top-ups are credited instantly (no payment yet).</p>
          </div>
          <button
            onClick={() => setOpen(true)}
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition-transform hover:scale-[1.02] active:scale-95"
          >
            <Plus className="h-4 w-4" /> Top up
          </button>
        </div>
      </div>

      {/* Transactions */}
      <div className="mb-3 flex items-center gap-2">
        <Receipt className="h-4 w-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Recent transactions</h2>
      </div>
      <Card className="overflow-hidden">
        {loadingTxns ? (
          <div className="grid place-items-center py-14"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>
        ) : txns.length === 0 ? (
          <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-secondary text-muted-foreground"><Receipt className="h-6 w-6" /></span>
            <p className="mt-3 text-sm font-medium text-foreground">No transactions yet</p>
            <p className="mt-1 text-sm text-muted-foreground">Top up your balance to get started.</p>
          </div>
        ) : (
          <motion.ul variants={listContainer} initial="hidden" animate="show" className="divide-y divide-border">
            {txns.map((t) => {
              const ui = TXN_UI[t.type];
              const Icon = ui.icon;
              return (
                <motion.li key={t.id} variants={listItem} className="flex items-center gap-3 px-4 py-3 sm:px-5">
                  <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-full ${ui.bg} ${ui.color}`}>
                    <Icon className="h-[18px] w-[18px]" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-medium capitalize text-foreground">{reasonLabel(t)}</div>
                    <div className="text-xs text-muted-foreground">{new Date(t.created_at).toLocaleString()}</div>
                  </div>
                  <div className="text-right">
                    <div className={`text-sm font-semibold tabular-nums ${ui.color}`}>{ui.sign}{formatMoney(t.amount, currency)}</div>
                    <div className="text-xs tabular-nums text-muted-foreground">{formatMoney(t.balance_after, currency)}</div>
                  </div>
                </motion.li>
              );
            })}
          </motion.ul>
        )}
      </Card>

      {open && (
        <Modal title="Top up balance" onClose={() => setOpen(false)}>
          <label className="text-sm font-medium text-foreground">Amount ({currency})</label>
          <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-border px-3 py-2.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/30">
            <span className="text-muted-foreground">{currencySymbol(currency)}</span>
            <input
              autoFocus
              type="number"
              min="0"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="min-w-0 flex-1 text-sm text-foreground outline-none placeholder:text-muted-foreground"
            />
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            {QUICK.map((q) => (
              <button
                key={q}
                type="button"
                onClick={() => setAmount(String(q))}
                className={`rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
                  amount === String(q) ? "border-primary bg-primary-soft text-primary" : "border-border text-muted-foreground hover:border-primary hover:text-primary"
                }`}
              >
                {formatMoney(q, currency)}
              </button>
            ))}
          </div>
          {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
            <Button onClick={topUp} disabled={busy}>
              {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />} Add funds
            </Button>
          </div>
        </Modal>
      )}
    </motion.div>
  );
}
