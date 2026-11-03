"use client";
import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import PageTitle from "@/components/page-title";
import SiteVisitsCard from "@/components/site-visits-card";
import ReadinessCard from "@/components/readiness/readiness-card";
import { ErrorState, PageLoading } from "@/components/states";
import { cn } from "@/lib/utils";
import { useApiQuery, type DashboardPayload, LANGUAGE_NAMES, timeAgo } from "@/lib/api";

const EMIRATES: Record<string, string> = { dubai: "Dubai", sharjah: "Sharjah", abu_dhabi: "Abu Dhabi", ajman: "Ajman", other: "Other emirate" };

type Tone = "warning" | "destructive" | undefined;

const Stat = ({ label, value, href, tone }: { label: string; value: number | string; href?: string; tone?: Tone }) => {
  const body = (
    <Card className={cn("h-full transition-[border-color,box-shadow] duration-150", href && "group-hover:border-primary/40 group-hover:shadow-lift")}>
      <CardContent className="p-4 md:p-5">
        <div className="flex items-start justify-between gap-2">
          <div className="text-[13px] leading-snug text-default-600">{label}</div>
          {href && <ArrowUpRight className="h-3.5 w-3.5 shrink-0 text-default-300 transition-colors group-hover:text-primary" aria-hidden="true" />}
        </div>
        <div className={cn("mt-1.5 text-[28px] leading-none font-semibold tabular-nums tracking-[-0.02em]", tone === "warning" ? "text-warning" : tone === "destructive" ? "text-destructive" : "text-default-900")}>
          {typeof value === "number" ? value.toLocaleString("en-GB") : value}
        </div>
      </CardContent>
    </Card>
  );
  return href ? <Link href={href} className="group block h-full rounded-xl">{body}</Link> : body;
};

const Section = ({ title, children }: { title: string; children: React.ReactNode }) => (
  <section className="mt-8" aria-label={title}>
    <h2 className="mb-3 text-[15px] font-semibold text-default-900">{title}</h2>
    <div className="grid gap-3 md:gap-4 grid-cols-2 lg:grid-cols-4">{children}</div>
  </section>
);

const warnIf = (n: number, tone: Exclude<Tone, undefined> = "warning"): Tone => (n > 0 ? tone : undefined);

const DashboardPage = () => {
  const { data, isLoading, error, refetch } = useApiQuery<DashboardPayload>(["dashboard"], "/admin/dashboard", { refetchInterval: 60_000 });

  if (error) {
    return (
      <div>
        <PageTitle title="Dashboard" description="Who has joined, what waits for a moderator, and whether the bot is listening." />
        <ErrorState error={error} onRetry={() => refetch()} title="The dashboard could not be loaded" />
      </div>
    );
  }
  if (isLoading || !data) return <PageLoading />;

  const m = data.members;
  const sourcesDown = data.sources.failing + data.sources.paused;
  // `tick.seconds` is how long the last run took; its age comes from `tick.at`.
  const tickAge = data.tick ? (Date.now() - Date.parse(data.tick.at)) / 1000 : null;

  return (
    <div>
      <PageTitle title="Dashboard" description="Who has joined, what waits for a moderator, and whether the bot is listening.">
        {data.bot.configured ? (
          <Badge color="success" variant="soft">Bot @{data.bot.username || "configured"}</Badge>
        ) : (
          <Link href="/settings"><Badge color="destructive" variant="soft">Bot token not set</Badge></Link>
        )}
        <Badge color={tickAge !== null && tickAge <= 300 ? "secondary" : "warning"} variant="soft">
          Scheduler {tickAge !== null ? `ticked ${timeAgo(tickAge)}` : "has not ticked"}
        </Badge>
      </PageTitle>

      <ReadinessCard />

      {(!data.jobs.channel || !data.tick) && (
        <div className="mt-4 space-y-1.5 rounded-xl border border-warning/25 bg-warning/[0.05] px-4 py-3 text-sm text-default-800">
          {!data.jobs.channel && <div>No jobs channel is set, so jobs reach members only through alerts. <Link href="/job-settings" className="font-medium text-primary underline-offset-2 hover:underline">Set the channel</Link>.</div>}
          {!data.tick && <div>The scheduler has not ticked yet, so sources are not fetched and alerts are not sent on their own. <Link href="/settings" className="font-medium text-primary underline-offset-2 hover:underline">Check the scheduler token</Link>.</div>}
        </div>
      )}

      <Section title="Members">
        <Stat label="Active members" value={m.active} href="/members?status=active" />
        <Stat label="Verified today" value={m.verified_today} />
        <Stat label="Still joining" value={m.onboarding} href="/members?status=onboarding" />
        <Stat label="Need review" value={m.needs_review} href="/members?status=needs_review" tone={warnIf(m.needs_review)} />
        <Stat label="Banned" value={m.banned} href="/members?status=banned" />
        <Stat label="Left" value={m.left} href="/members?status=left" />
        <Stat label="Telegram updates today" value={data.updates.today} />
        <Stat label="Failed updates" value={data.updates.failed} tone={warnIf(data.updates.failed, "destructive")} />
      </Section>

      <Section title="Jobs">
        <Stat label="Published today" value={data.jobs.published_today} href="/jobs?status=published" />
        <Stat label="Open jobs" value={data.jobs.open} href="/jobs?status=published" />
        <Stat label="Waiting for review" value={data.jobs.review} href="/jobs?status=review" tone={warnIf(data.jobs.review)} />
        <Stat label="Duplicates caught today" value={data.jobs.duplicates_today} href="/jobs?status=duplicate" />
        <Stat label="Sources running" value={data.sources.active} href="/job-sources" />
        <Stat label="Sources failing or paused" value={sourcesDown} href="/job-sources" tone={warnIf(sourcesDown, "destructive")} />
        <Stat label="Alerts waiting to send" value={data.jobs.alerts_queued} />
        <Stat label="Classifier" value={data.jobs.classifier === "claude" ? "Claude" : "Rules"} href="/job-settings" tone={data.jobs.classifier === "rules" ? "warning" : undefined} />
      </Section>

      <Section title="Community">
        <Stat label="Open questions" value={data.community.questions_open} href="/questions" />
        <Stat label="Questions today" value={data.community.questions_today} href="/questions" />
        <Stat label="Announcements waiting" value={data.community.announcements_pending} href="/announcements" tone={warnIf(data.community.announcements_pending)} />
        <Stat label="Open safety reports" value={data.community.safety_open} href="/safety" tone={warnIf(data.community.safety_open, "destructive")} />
        <Stat label="Live FAQ entries" value={data.community.faq_live} href="/faq" />
        <Stat label="Reported messages" value={data.community.reports_open} href="/moderation" tone={warnIf(data.community.reports_open, "destructive")} />
      </Section>

      <Section title="Goods board">
        <Stat label="Open posts" value={data.goods.open_posts} href="/goods" />
        <Stat label="Matches in progress" value={data.goods.live_matches} href="/goods" />
        <Stat label="Disputes to decide" value={data.goods.disputes} href="/goods" tone={warnIf(data.goods.disputes, "destructive")} />
        <Stat label="Delivered" value={data.goods.completed} href="/goods" />
      </Section>

      <Section title="Business directory">
        <Stat label="Listed businesses" value={data.directory.listed} href="/directory?status=approved" />
        <Stat label="Waiting for review" value={data.directory.pending} href="/directory?status=pending" tone={warnIf(data.directory.pending)} />
        <Stat label="Owner edits waiting" value={data.directory.owner_edits} href="/directory?status=changes" tone={warnIf(data.directory.owner_edits)} />
        <Stat label="Ownership claims" value={data.directory.claims} href="/directory?status=claims" tone={warnIf(data.directory.claims)} />
        <Stat label="Hidden after reports" value={data.directory.hidden} href="/directory?status=hidden" tone={warnIf(data.directory.hidden, "destructive")} />
        <Stat label="Business owners signed up" value={data.directory.owners} />
      </Section>

      <div className="mt-8">
        <SiteVisitsCard />
      </div>

      <div className="grid gap-4 md:grid-cols-2 mt-6">
        <Card>
          <CardHeader><CardTitle className="text-base">Active members by emirate</CardTitle></CardHeader>
          <CardContent>
            <Breakdown rows={data.by_emirate} labels={EMIRATES} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">Active members by language</CardTitle></CardHeader>
          <CardContent>
            <Breakdown rows={data.by_language} labels={LANGUAGE_NAMES} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

const Breakdown = ({ rows, labels }: { rows: Record<string, number> | unknown[]; labels: Record<string, string> }) => {
  // An empty PHP map arrives as [], not {}.
  const entries = Array.isArray(rows) ? [] : Object.entries(rows).sort((a, b) => b[1] - a[1]);
  const total = entries.reduce((sum, [, n]) => sum + n, 0);
  if (entries.length === 0) return <div className="text-sm text-default-500">Nobody yet. Members appear here once they finish joining in the bot.</div>;
  return (
    <ul className="space-y-3">
      {entries.map(([key, n]) => (
        <li key={key || "unknown"}>
          <div className="flex justify-between text-sm">
            <span className="text-default-800">{key ? labels[key] ?? key : "Not given"}</span>
            <span className="tabular-nums text-default-600">{n} <span className="text-default-400">· {total ? Math.round((n / total) * 100) : 0}%</span></span>
          </div>
          <div className="mt-1 h-2 rounded-full bg-default-100 overflow-hidden">
            <div className="h-full rounded-full bg-primary" style={{ width: `${total ? Math.round((n / total) * 100) : 0}%` }} />
          </div>
        </li>
      ))}
    </ul>
  );
};

export default DashboardPage;
