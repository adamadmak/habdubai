import { Badge } from "@/components/ui/badge";
import type { JobStatus } from "@/lib/api";

const STATUS_COLOR: Record<JobStatus, "success" | "warning" | "info" | "destructive" | "secondary"> = {
  published: "success",
  review: "warning",
  pending: "info",
  rejected: "destructive",
  duplicate: "secondary",
  closed: "secondary",
};

export const JobStatusBadge = ({ status }: { status: JobStatus }) => (
  <Badge color={STATUS_COLOR[status]} variant="soft" className="capitalize">{status === "review" ? "needs review" : status}</Badge>
);

export const ScamScore = ({ score }: { score: number }) => {
  const color = score >= 50 ? "text-destructive" : score >= 30 ? "text-warning" : "text-default-500";
  return <span className={`font-mono text-sm ${color}`} title="Scam score, 0 to 100">{score}</span>;
};
