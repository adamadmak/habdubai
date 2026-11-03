import { CircleAlert, CircleCheck, EyeOff, Hourglass } from "lucide-react";
import type { ListingStatus } from "@/lib/api";

const STYLE: Record<ListingStatus, { tone: string; icon: typeof Hourglass }> = {
  pending: { tone: "bg-warning-soft text-warning", icon: Hourglass },
  approved: { tone: "bg-success-soft text-success", icon: CircleCheck },
  rejected: { tone: "bg-danger-soft text-danger", icon: CircleAlert },
  hidden: { tone: "bg-surface text-muted", icon: EyeOff },
};

export function StatusPill({ status, text }: { status: ListingStatus; text: string }) {
  const { tone, icon: Icon } = STYLE[status];
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${tone}`}>
      <Icon className="size-3.5" aria-hidden="true" /> {text}
    </span>
  );
}
