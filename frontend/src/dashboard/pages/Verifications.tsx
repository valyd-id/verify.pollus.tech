import { useEffect, useState } from "react";
import { motion } from "framer-motion";
import { api, type Session } from "../api";
import { useApps } from "../apps";
import { Card, PageHeader, StatusPill } from "../components/ui";
import { fadeUp, listContainer, listItem } from "../components/motion";

export function Verifications() {
  const { selected } = useApps();
  const [sessions, setSessions] = useState<Session[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!selected) return;
    setLoading(true);
    api.sessions(selected.id).then((r) => {
      if (r.success && r.data) setSessions(r.data.sessions);
      setLoading(false);
    });
  }, [selected?.id]);

  if (!selected) return <div className="grid h-[50vh] place-items-center text-sm text-muted-foreground">Create an app first.</div>;

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show" className="mx-auto max-w-5xl">
      <PageHeader title="Verifications" subtitle="Sessions created with this app's workflows." />
      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border bg-secondary text-left text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-4 py-3 font-medium">Session</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Mode</th>
                <th className="px-4 py-3 font-medium">Reference</th>
                <th className="px-4 py-3 font-medium">Created</th>
              </tr>
            </thead>
            <motion.tbody className="divide-y divide-border" variants={listContainer} initial="hidden" animate="show">
              {loading ? (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-muted-foreground">Loading…</td></tr>
              ) : sessions.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-muted-foreground">No verification sessions yet.</td></tr>
              ) : (
                sessions.map((s) => (
                  <motion.tr key={s.session_id} variants={listItem} className="hover:bg-secondary">
                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{s.session_id.slice(0, 12)}…</td>
                    <td className="px-4 py-3"><StatusPill status={s.status} /></td>
                    <td className="px-4 py-3 text-muted-foreground">{s.mode}</td>
                    <td className="px-4 py-3 text-muted-foreground">{s.vendor_data ?? "—"}</td>
                    <td className="px-4 py-3 text-muted-foreground">{new Date(s.created_at).toLocaleString()}</td>
                  </motion.tr>
                ))
              )}
            </motion.tbody>
          </table>
        </div>
      </Card>
    </motion.div>
  );
}
