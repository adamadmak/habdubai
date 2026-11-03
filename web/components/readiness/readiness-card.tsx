"use client";
import Link from "next/link";
import { ArrowRight, Rocket, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { errorText } from "@/lib/api";
import ScoreRing from "./score-ring";
import { isMissing, summarise, useReadiness } from "./use-readiness";

/** The dashboard's view of the launch checklist: score, what blocks launch, and the way in. */
const ReadinessCard = () => {
  const { data, error, isLoading } = useReadiness();
  const { headline, tone, blockers, of } = summarise(data);

  if (isLoading) {
    return (
      <div className="glass rounded-2xl p-5 flex items-center gap-5" role="status" aria-label="Loading the launch checklist">
        <Skeleton className="h-[76px] w-[76px] rounded-full bg-default-100" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-5 w-48 bg-default-100" />
          <Skeleton className="h-4 w-72 max-w-full bg-default-100" />
        </div>
      </div>
    );
  }

  if (error || !data) {
    const missing = isMissing(error);
    return (
      <div className="glass rounded-2xl p-5 flex flex-wrap items-center gap-4">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary ring-1 ring-primary-100">
          <Rocket className="h-5 w-5" aria-hidden="true" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="font-semibold text-default-900">Launch checklist</div>
          <div className="text-sm text-default-600">
            {missing ? "The readiness check arrives with the next API deploy. This card fills in by itself." : `The readiness check could not be loaded: ${errorText(error)}`}
          </div>
        </div>
        <Button asChild variant="outline" color="secondary" size="sm">
          <Link href="/launch">Open the checklist</Link>
        </Button>
      </div>
    );
  }

  const open = of("blocker").filter((i) => !i.ok).slice(0, 3);

  return (
    <div className="glass rounded-2xl p-5 flex flex-wrap items-center gap-5">
      <ScoreRing score={data.score} tone={tone} size={76} />
      <div className="min-w-0 flex-1">
        <div className="text-[11px] font-semibold uppercase tracking-[0.14em] text-primary">Launch</div>
        <div className="font-display text-xl font-semibold text-default-900">{headline}</div>
        {open.length > 0 ? (
          <ul className="mt-1.5 space-y-1">
            {open.map((item) => (
              <li key={item.key} className="flex items-start gap-1.5 text-sm text-default-700">
                <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" aria-hidden="true" />
                <span className="min-w-0">{item.label}</span>
              </li>
            ))}
            {blockers > open.length && <li className="text-sm text-default-500">and {blockers - open.length} more</li>}
          </ul>
        ) : (
          <div className="text-sm text-default-600">
            {data.ready ? "Every blocker is cleared. The checklist still lists polish worth doing." : "No blockers left; a few important items remain."}
          </div>
        )}
      </div>
      <Button asChild size="sm">
        <Link href="/launch">
          Open the checklist <ArrowRight className="ml-1.5 h-4 w-4" aria-hidden="true" />
        </Link>
      </Button>
    </div>
  );
};

export default ReadinessCard;
