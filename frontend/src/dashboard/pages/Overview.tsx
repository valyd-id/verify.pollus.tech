import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { CheckCircle2, XCircle, Clock, Activity, ArrowRight } from "lucide-react";
import { motion } from "framer-motion";
import { api, type Stats, type Session } from "../api";
import { useApps } from "../apps";
import { Card, PageHeader, StatusPill } from "../components/ui";
import { fadeUp, listContainer, listItem } from "../components/motion";

export function Overview() {
  const { selected } = useApps();
  const [stats, setStats] = useState<Stats | null>(null);
  const [recent, setRecent] = useState<Session[]>([]);

  useEffect(() => {
    if (!selected) return;
    api.sessions(selected.id).then((r) => {
      if (r.success && r.data) { setStats(r.data.stats); setRecent(r.data.sessions.slice(0, 5)); }
    });
  }, [selected?.id]);

  if (!selected) {
    return (
      <div className="mx-auto max-w-2xl">
        <Card className="p-8 text-center">
          <h2 className="text-lg font-semibold text-foreground">Welcome to Valyd Verify</h2>
          <p className="mt-1 text-sm text-muted-foreground">Create your first app to get an API key and start verifying.</p>
          <Link to="/dashboard/apps" className="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Create app <ArrowRight className="h-4 w-4" /></Link>
        </Card>
      </div>
    );
  }

  const cards = [
    { label: "Total", value: stats?.total ?? 0, icon: Activity, color: "text-muted-foreground bg-secondary" },
    { label: "Approved", value: stats?.approved ?? 0, icon: CheckCircle2, color: "text-emerald-300 bg-emerald-500/15" },
    { label: "Declined", value: stats?.declined ?? 0, icon: XCircle, color: "text-red-300 bg-red-500/15" },
    { label: "In review", value: stats?.in_review ?? 0, icon: Clock, color: "text-amber-300 bg-amber-500/15" },
  ];

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-5xl">
      <PageHeader title="Overview" subtitle={`${selected.name} · ${selected.app_id}`} />
      <motion.div variants={listContainer} initial="hidden" animate="show" className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {cards.map((c) => (
          <motion.div key={c.label} variants={listItem}>
            <Card className="p-4">
              <span className={`grid h-9 w-9 place-items-center rounded-lg ${c.color}`}><c.icon className="h-4 w-4" /></span>
              <div className="mt-3 text-2xl font-semibold text-foreground">{c.value}</div>
              <div className="text-xs text-muted-foreground">{c.label}</div>
            </Card>
          </motion.div>
        ))}
      </motion.div>

      <div className="mt-6 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Recent verifications</h2>
        <Link to="/dashboard/verifications" className="text-xs font-medium text-primary hover:text-primary">View all</Link>
      </div>
      <Card className="mt-2 divide-y divide-border">
        {recent.length === 0 ? (
          <div className="p-6 text-center text-sm text-muted-foreground">No verifications yet.</div>
        ) : recent.map((s) => (
          <div key={s.session_id} className="flex items-center justify-between px-4 py-3">
            <span className="font-mono text-xs text-muted-foreground">{s.session_id.slice(0, 12)}…</span>
            <div className="flex items-center gap-3">
              <span className="text-xs text-muted-foreground">{new Date(s.created_at).toLocaleDateString()}</span>
              <StatusPill status={s.status} />
            </div>
          </div>
        ))}
      </Card>
    </motion.div>
  );
}
