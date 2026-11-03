"use client";
import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import PageTitle from "@/components/page-title";
import { useApiQuery, type DashboardPayload, LANGUAGE_NAMES } from "@/lib/api";

const EMIRATES: Record<string, string> = { dubai: "Dubai", sharjah: "Sharjah", abu_dhabi: "Abu Dhabi", ajman: "Ajman", other: "Other emirate" };

const Stat = ({ label, value, href, tone }: { label: string; value: number | string; href?: string; tone?: "warning" | "destructive" }) => {
  const body = (
    <Card className="h-full">
      <CardContent className="p-5">
        <div className="text-sm text-default-600">{label}</div>
        <div className={`mt-1 text-3xl font-semibold ${tone === "warning" ? "text-warning" : tone === "destructive" ? "text-destructive" : "text-default-900"}`}>{value}</div>
      </CardContent>
    </Card>
  );
  return href ? <Link href={href} className="block h-full">{body}</Link> : body;
};

const DashboardPage = () => {
  const { data, isLoading, error } = useApiQuery<DashboardPayload>(["dashboard"], "/admin/dashboard", { refetchInterval: 60_000 });

  if (error) return <div className="text-destructive">Could not load the dashboard: {error.message}</div>;
  if (isLoading || !data) return <div className="text-default-600">Loading…</div>;

  const m = data.members;

  return (
    <div>
      <PageTitle title="Dashboard" description="Who has joined, and whether the bot is listening.">
        {data.bot.configured ? (
          <Badge color="success" variant="soft">Bot @{data.bot.username || "configured"}</Badge>
        ) : (
          <Link href="/settings"><Badge color="destructive" variant="soft">Bot token not set</Badge></Link>
        )}
      </PageTitle>

      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Stat label="Active members" value={m.active} href="/members?status=active" />
        <Stat label="Verified today" value={m.verified_today} />
        <Stat label="Still joining" value={m.onboarding} href="/members?status=onboarding" />
        <Stat label="Need review" value={m.needs_review} href="/members?status=needs_review" tone={m.needs_review > 0 ? "warning" : undefined} />
        <Stat label="Banned" value={m.banned} href="/members?status=banned" />
        <Stat label="Left" value={m.left} href="/members?status=left" />
        <Stat label="Telegram updates today" value={data.updates.today} />
        <Stat label="Failed updates" value={data.updates.failed} tone={data.updates.failed > 0 ? "destructive" : undefined} />
      </div>

      <h2 className="text-lg font-semibold text-default-900 mt-8 mb-3">Jobs</h2>
      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Stat label="Published today" value={data.jobs.published_today} href="/jobs?status=published" />
        <Stat label="Open jobs" value={data.jobs.open} href="/jobs?status=published" />
        <Stat label="Waiting for review" value={data.jobs.review} href="/jobs?status=review" tone={data.jobs.review > 0 ? "warning" : undefined} />
        <Stat label="Duplicates caught today" value={data.jobs.duplicates_today} href="/jobs?status=duplicate" />
        <Stat label="Sources running" value={data.sources.active} href="/job-sources" />
        <Stat label="Sources failing or paused" value={data.sources.failing + data.sources.paused} href="/job-sources" tone={data.sources.failing + data.sources.paused > 0 ? "destructive" : undefined} />
        <Stat label="Alerts waiting to send" value={data.jobs.alerts_queued} />
        <Stat label="Classifier" value={data.jobs.classifier === "claude" ? "Claude" : "Rules"} href="/job-settings" tone={data.jobs.classifier === "rules" ? "warning" : undefined} />
      </div>
      <h2 className="text-lg font-semibold text-default-900 mt-8 mb-3">Community</h2>
      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Stat label="Open questions" value={data.community.questions_open} href="/questions" />
        <Stat label="Questions today" value={data.community.questions_today} href="/questions" />
        <Stat label="Announcements waiting" value={data.community.announcements_pending} href="/announcements" tone={data.community.announcements_pending > 0 ? "warning" : undefined} />
        <Stat label="Open safety reports" value={data.community.safety_open} href="/safety" tone={data.community.safety_open > 0 ? "destructive" : undefined} />
        <Stat label="Live FAQ entries" value={data.community.faq_live} href="/faq" />
        <Stat label="Reported messages" value={data.community.reports_open} href="/moderation" tone={data.community.reports_open > 0 ? "destructive" : undefined} />
      </div>

      <h2 className="text-lg font-semibold text-default-900 mt-8 mb-3">Goods board</h2>
      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Stat label="Open posts" value={data.goods.open_posts} href="/goods" />
        <Stat label="Matches in progress" value={data.goods.live_matches} href="/goods" />
        <Stat label="Disputes to decide" value={data.goods.disputes} href="/goods" tone={data.goods.disputes > 0 ? "destructive" : undefined} />
        <Stat label="Delivered" value={data.goods.completed} href="/goods" />
      </div>

      <h2 className="text-lg font-semibold text-default-900 mt-8 mb-3">Business directory</h2>
      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Stat label="Listed businesses" value={data.directory.listed} href="/directory?status=approved" />
        <Stat label="Waiting for review" value={data.directory.pending} href="/directory?status=pending" tone={data.directory.pending > 0 ? "warning" : undefined} />
        <Stat label="Owner edits waiting" value={data.directory.owner_edits} href="/directory?status=changes" tone={data.directory.owner_edits > 0 ? "warning" : undefined} />
        <Stat label="Ownership claims" value={data.directory.claims} href="/directory?status=claims" tone={data.directory.claims > 0 ? "warning" : undefined} />
        <Stat label="Hidden after reports" value={data.directory.hidden} href="/directory?status=hidden" tone={data.directory.hidden > 0 ? "destructive" : undefined} />
        <Stat label="Business owners signed up" value={data.directory.owners} />
      </div>

      {(!data.jobs.channel || !data.tick) && (
        <div className="mt-3 space-y-1 text-sm text-warning">
          {!data.jobs.channel && <div>No jobs channel is set, so jobs reach members only through alerts. <Link href="/job-settings" className="underline">Set the channel</Link>.</div>}
          {!data.tick && <div>The scheduler has not ticked yet, so sources are not fetched and alerts are not sent on their own.</div>}
        </div>
      )}

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

const Breakdown = ({ rows, labels }: { rows: Record<string, number>; labels: Record<string, string> }) => {
  const entries = Object.entries(rows).sort((a, b) => b[1] - a[1]);
  const total = entries.reduce((sum, [, n]) => sum + n, 0);
  if (entries.length === 0) return <div className="text-sm text-default-500">Nobody yet.</div>;
  return (
    <ul className="space-y-3">
      {entries.map(([key, n]) => (
        <li key={key}>
          <div className="flex justify-between text-sm">
            <span className="text-default-800">{labels[key] ?? key ?? "Unknown"}</span>
            <span className="text-default-600">{n}</span>
          </div>
          <div className="mt-1 h-2 rounded bg-default-100 overflow-hidden">
            <div className="h-full bg-primary" style={{ width: `${total ? Math.round((n / total) * 100) : 0}%` }} />
          </div>
        </li>
      ))}
    </ul>
  );
};

export default DashboardPage;
