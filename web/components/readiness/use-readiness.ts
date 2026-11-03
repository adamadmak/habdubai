"use client";
import { useApiQuery, ApiError, type HealthPayload, type ReadinessItem, type ReadinessPayload, type ReadinessSeverity } from "@/lib/api";
import type { RingTone } from "./score-ring";

/**
 * Both launch checks, refreshed every 30 seconds while a page that shows them is
 * open, and never served stale: an admin who fixes a setting and comes straight
 * back sees the item turn green, not the copy from a moment ago.
 */
export function useReadiness() {
  return useApiQuery<ReadinessPayload>(["readiness"], "/admin/readiness", { refetchInterval: 30_000, refetchOnWindowFocus: true, staleTime: 0 });
}

export function useHealth() {
  return useApiQuery<HealthPayload>(["health"], "/health", { refetchInterval: 30_000, refetchOnWindowFocus: true, staleTime: 0 });
}

/** The API has not shipped this endpoint yet (or answers it on another path): not an error to shout about. */
export const isMissing = (error: unknown) => error instanceof ApiError && (error.status === 404 || error.status === 405);

export const SEVERITIES: { key: ReadinessSeverity; title: string; hint: string }[] = [
  { key: "blocker", title: "Blockers", hint: "Launch waits for these." },
  { key: "important", title: "Important", hint: "Finish before members arrive in numbers." },
  { key: "nice", title: "Nice to have", hint: "Polish. Launch without them if you must." },
];

export function summarise(data: ReadinessPayload | undefined) {
  const items: ReadinessItem[] = Array.isArray(data?.items) ? data!.items : [];
  const of = (severity: ReadinessSeverity) => items.filter((i) => i.severity === severity);
  const left = (severity: ReadinessSeverity) => of(severity).filter((i) => !i.ok).length;
  const blockers = left("blocker");
  const tone: RingTone = !data ? "muted" : data.ready ? "success" : blockers > 0 && data.score < 60 ? "destructive" : "warning";
  const headline = !data
    ? "Launch checklist"
    : data.ready
      ? "Ready for launch"
      : blockers > 0
        ? `${blockers} blocker${blockers === 1 ? "" : "s"} left`
        : "Almost ready";
  return { items, of, left, blockers, tone, headline };
}
