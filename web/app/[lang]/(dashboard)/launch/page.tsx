"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import {
  AlertTriangle, ArrowUpRight, CheckCircle2, CircleDashed, Clock, Database, Inbox, RefreshCw, Rocket, Server, XCircle, Activity,
} from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import PageTitle from "@/components/page-title";
import { EmptyState, ErrorState } from "@/components/states";
import ScoreRing from "@/components/readiness/score-ring";
import { isMissing, SEVERITIES, summarise, useHealth, useReadiness } from "@/components/readiness/use-readiness";
import { menusConfig } from "@/config/menus";
import { cn } from "@/lib/utils";
import { ApiError, timeAgo, type HealthPayload, type ReadinessItem, type ReadinessSeverity } from "@/lib/api";

/** "Settings" for "/settings": the button says where it goes. */
function pageName(href: string): string | null {
  const path = href.split(/[?#]/)[0];
  return menusConfig.mainNav.find((item) => item.href === path)?.title ?? null;
}

const isExternal = (href: string) => /^https?:\/\//i.test(href);

/** Seconds since a timestamp, re-rendered every few seconds so "updated 20 s ago" stays true. */
function useSecondsSince(at: number) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), 5000);
    return () => window.clearInterval(id);
  }, []);
  return at ? Math.max(0, (now - at) / 1000) : null;
}

const LaunchPage = () => {
  const readiness = useReadiness();
  const health = useHealth();
  const queryClient = useQueryClient();
  const since = useSecondsSince(readiness.dataUpdatedAt);
  const { items, of, left, headline, tone } = summarise(readiness.data);
  const checking = readiness.isFetching || health.isFetching;

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["readiness"] });
    queryClient.invalidateQueries({ queryKey: ["health"] });
  };

  return (
    <div>
      <PageTitle
        eyebrow="Launch"
        title="Launch checklist"
        description="What the API checks before members arrive: settings, the bot, the groups, content and the scheduler. It refreshes every 30 seconds while this page is open."
      >
        <span className="text-xs text-default-500" aria-live="polite">
          {checking ? "Checking…" : since !== null ? `Checked ${timeAgo(since)}` : ""}
        </span>
        <Button variant="outline" color="secondary" size="sm" onClick={refresh} disabled={checking}>
          <RefreshCw className={cn("mr-1.5 h-3.5 w-3.5", checking && "motion-safe:animate-spin")} aria-hidden="true" /> Check now
        </Button>
      </PageTitle>

      {/* The score, over a soft wash of the brand's blue and gold. */}
      <section aria-labelledby="launch-score" className="relative mb-6">
        <div aria-hidden="true" className="pointer-events-none absolute -inset-x-4 -top-6 -bottom-4 overflow-hidden rounded-[28px]">
          <div className="absolute -left-10 -top-16 h-64 w-64 rounded-full bg-primary/15 blur-3xl" />
          <div className="absolute right-0 -top-10 h-56 w-72 rounded-full bg-gold/20 blur-3xl" />
        </div>
        <div className="glass relative rounded-2xl p-6 md:p-8">
          {readiness.isLoading ? (
            <div className="flex items-center gap-8" role="status" aria-label="Loading the checklist">
              <Skeleton className="h-40 w-40 shrink-0 rounded-full bg-default-100" />
              <div className="min-w-0 flex-1 space-y-3">
                <Skeleton className="h-9 w-72 max-w-full bg-default-100" />
                <Skeleton className="h-4 w-96 max-w-full bg-default-100" />
                <Skeleton className="h-4 w-80 max-w-full bg-default-100" />
              </div>
            </div>
          ) : readiness.error ? (
            isMissing(readiness.error) ? (
              <EmptyState icon={Rocket} title="The readiness check is not on this API yet" compact>
                It arrives with the next API deploy (<code className="font-mono text-xs">GET /api/admin/readiness</code>). Nothing to do here: this page asks again every 30 seconds and fills in by itself.
              </EmptyState>
            ) : (
              <ErrorState error={readiness.error} onRetry={refresh} title="The checklist could not be loaded" />
            )
          ) : (
            <div className="flex flex-col items-center gap-6 md:flex-row md:items-center md:gap-10">
              <ScoreRing score={readiness.data?.score ?? null} tone={tone} />
              <div className="min-w-0 flex-1 text-center md:text-left">
                <h2 id="launch-score" className={cn("font-display text-[32px] leading-tight font-semibold", readiness.data?.ready ? "text-success" : "text-default-900")}>
                  {headline}
                </h2>
                <p className="mt-1 text-sm text-default-600">
                  {readiness.data?.ready
                    ? "Nothing blocks launch. What is left below is worth doing, but members can arrive."
                    : "Clear every blocker first. Each one says what is wrong and takes you to the page that fixes it."}
                </p>
                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                  {SEVERITIES.map((s) => {
                    const total = of(s.key).length;
                    const done = total - left(s.key);
                    return (
                      <a key={s.key} href={`#group-${s.key}`} className="rounded-xl border border-border/80 bg-card/70 px-4 py-3 text-left transition-colors hover:border-primary/40">
                        <div className="flex items-baseline justify-between gap-2">
                          <span className="text-sm font-medium text-default-700">{s.title}</span>
                          <span className="text-sm tabular-nums text-default-500">{done}/{total}</span>
                        </div>
                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-default-100">
                          <div
                            className={cn("h-full rounded-full", done === total ? "bg-success" : s.key === "blocker" ? "bg-destructive" : s.key === "important" ? "bg-gold" : "bg-primary")}
                            style={{ width: `${total ? (done / total) * 100 : 100}%` }}
                          />
                        </div>
                      </a>
                    );
                  })}
                </div>
              </div>
            </div>
          )}
        </div>
      </section>

      <HealthStrip health={health.data} error={health.error} loading={health.isLoading} />

      {readiness.data && items.length === 0 && (
        <Card className="mt-6"><EmptyState icon={Inbox} title="No checks came back">The API answered without a single item. That is unusual: check the API logs.</EmptyState></Card>
      )}

      {readiness.data && items.length > 0 && (
        <div className="mt-8 space-y-8">
          {SEVERITIES.map((s) => (
            <Group key={s.key} severity={s.key} title={s.title} hint={s.hint} items={of(s.key)} />
          ))}
        </div>
      )}
    </div>
  );
};

const Group = ({ severity, title, hint, items }: { severity: ReadinessSeverity; title: string; hint: string; items: ReadinessItem[] }) => {
  if (items.length === 0) return null;
  const failing = items.filter((i) => !i.ok);
  const passing = items.filter((i) => i.ok);

  return (
    <section id={`group-${severity}`} aria-labelledby={`group-${severity}-title`} className="scroll-mt-24">
      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h2 id={`group-${severity}-title`} className="text-lg font-semibold text-default-900">{title}</h2>
          <p className="text-sm text-default-500">{hint}</p>
        </div>
        <span className={cn("text-sm font-medium", failing.length ? "text-default-700" : "text-success")}>
          {failing.length ? `${failing.length} to do · ${passing.length} done` : `All ${passing.length} done`}
        </span>
      </div>

      <Card className="overflow-hidden">
        <ul className="divide-y divide-border/80">
          {failing.map((item) => <FailingItem key={item.key} item={item} />)}
        </ul>
        {passing.length > 0 && (
          <details className={cn("group", failing.length > 0 && "border-t border-border/80")} open={failing.length === 0}>
            <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm font-medium text-default-600 hover:text-default-900">
              <CheckCircle2 className="h-4 w-4 text-success" aria-hidden="true" />
              {passing.length} passed
              <span className="text-default-400 group-open:hidden">· show</span>
            </summary>
            <ul className="divide-y divide-border/60 bg-default-50/40">
              {passing.map((item) => (
                <li key={item.key} className="flex items-start gap-3 px-5 py-2.5">
                  <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-success" aria-label="Passed" />
                  <div className="min-w-0 text-sm">
                    <span className="font-medium text-default-800">{item.label}</span>
                    {item.detail && <span className="text-default-500"> · {item.detail}</span>}
                  </div>
                </li>
              ))}
            </ul>
          </details>
        )}
      </Card>
    </section>
  );
};

const FailingItem = ({ item }: { item: ReadinessItem }) => {
  const Icon = item.severity === "blocker" ? XCircle : item.severity === "important" ? AlertTriangle : CircleDashed;
  const color = item.severity === "blocker" ? "text-destructive" : item.severity === "important" ? "text-warning" : "text-default-400";
  const name = item.href && !isExternal(item.href) ? pageName(item.href) : null;

  return (
    <li className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start">
      <Icon className={cn("mt-0.5 h-5 w-5 shrink-0", color)} aria-label={item.severity === "blocker" ? "Blocker, not done" : "Not done"} />
      <div className="min-w-0 flex-1">
        <div className="font-semibold text-default-900">{item.label}</div>
        {item.detail && <p className="mt-0.5 text-sm text-default-600">{item.detail}</p>}
        {item.fix && (
          <p className="mt-2 rounded-lg bg-primary-50/70 px-3 py-2 text-sm text-default-800 ring-1 ring-primary-100">
            <span className="font-semibold text-primary-800">To fix: </span>
            {item.fix}
          </p>
        )}
      </div>
      {item.href && (
        <Button asChild size="sm" variant={item.severity === "blocker" ? undefined : "outline"} color={item.severity === "blocker" ? "primary" : "secondary"} className="shrink-0 self-start">
          {isExternal(item.href) ? (
            <a href={item.href} target="_blank" rel="noreferrer">Open <ArrowUpRight className="ml-1 h-3.5 w-3.5" aria-hidden="true" /></a>
          ) : (
            <Link href={item.href}>{name ? `Go to ${name}` : "Fix it"} <ArrowUpRight className="ml-1 h-3.5 w-3.5" aria-hidden="true" /></Link>
          )}
        </Button>
      )}
    </li>
  );
};

/** The running API's own health: database, scheduler, queue, failed Telegram updates. */
const HealthStrip = ({ health, error, loading }: { health?: HealthPayload; error: unknown; loading: boolean }) => {
  if (loading) {
    return (
      <div className="grid gap-3 grid-cols-2 lg:grid-cols-5" role="status" aria-label="Loading system health">
        {Array.from({ length: 5 }, (_, i) => <Skeleton key={i} className="h-[76px] rounded-xl bg-default-100" />)}
      </div>
    );
  }
  // The API answers 503 (with the same JSON) when its database is down: that is a result, not a failed read.
  const down = error instanceof ApiError && error.status === 503;
  if ((error && !down) || (!health && !down)) {
    return (
      <p className="flex items-center gap-2 text-sm text-default-500">
        <Activity className="h-4 w-4" aria-hidden="true" />
        {isMissing(error) ? "System health (GET /api/health) arrives with the next API deploy." : "System health could not be read just now."}
      </p>
    );
  }
  if (down) {
    health = { ok: false, db: false, tick_age_seconds: null, queue_pending: 0, failed_updates_24h: 0 };
  }
  health = health as HealthPayload;

  const dbOk = health.db === true || health.db === "ok";
  const tick = health.tick_age_seconds;
  const tickOk = tick !== null && tick <= 300;
  const tiles = [
    { icon: Server, label: "API", value: health.ok ? "Answering" : "Trouble", ok: health.ok },
    { icon: Database, label: "Database", value: dbOk ? "Connected" : typeof health.db === "string" && health.db !== "down" ? health.db : "Not reachable", ok: dbOk, note: down ? "The API answered 503" : undefined },
    { icon: Clock, label: "Scheduler tick", value: tick === null ? "Never" : timeAgo(tick), ok: tickOk, note: tickOk ? undefined : "Should tick every minute" },
    { icon: Inbox, label: "Queue waiting", value: String(health.queue_pending), ok: health.queue_pending < 100 },
    { icon: AlertTriangle, label: "Failed updates, 24 h", value: String(health.failed_updates_24h), ok: health.failed_updates_24h === 0 },
  ];

  return (
    <div className="grid gap-3 grid-cols-2 lg:grid-cols-5">
      {tiles.map((t) => (
        <Card key={t.label}>
          <CardContent className="p-4">
            <div className="flex items-center gap-1.5 text-xs font-medium text-default-500">
              <t.icon className="h-3.5 w-3.5" aria-hidden="true" /> {t.label}
            </div>
            <div className={cn("mt-1 text-lg font-semibold tabular-nums", t.ok ? "text-default-900" : "text-destructive")}>
              <span className={cn("mr-1.5 inline-block h-2 w-2 rounded-full align-middle", t.ok ? "bg-success" : "bg-destructive")} aria-hidden="true" />
              {t.value}
              <span className="sr-only">{t.ok ? " (fine)" : " (needs attention)"}</span>
            </div>
            {t.note && <div className="text-xs text-default-500">{t.note}</div>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
};

export default LaunchPage;
