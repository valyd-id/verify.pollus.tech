import { useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { useApps } from "../apps";
import { fadeUp } from "../components/motion";
import { WorkflowWizard } from "./WorkflowWizard";

/** Full-page "new workflow" wizard (route: /dashboard/workflows/new). */
export function WorkflowNew() {
  const { selected } = useApps();
  const navigate = useNavigate();

  if (!selected) {
    return <div className="grid h-[50vh] place-items-center text-sm text-muted-foreground">Create an app first to add a workflow.</div>;
  }

  return (
    <motion.div variants={fadeUp} initial="hidden" animate="show">
      <WorkflowWizard app={selected} onClose={() => navigate("/dashboard/workflows")} onCreated={() => {}} />
    </motion.div>
  );
}
