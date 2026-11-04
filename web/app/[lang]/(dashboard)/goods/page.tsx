"use client";
import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import toast from "react-hot-toast";
import { Link2, Package, Scale } from "lucide-react";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import { useApiQuery, useApiMutation, formatDate, formatDateTime, errorText, type GoodsMatchRow, type GoodsMember, type GoodsPostRow } from "@/lib/api";
import { pageNumber, useLastPageAtMost, useUrlState } from "@/lib/use-url-state";

/** "AED 15 per kg", "Free", "To agree": what a post says about the carry fee, paid member to member. */
function fee(p: GoodsPostRow): string | null {
  if (p.price === undefined) return null;
  if (p.price === null) return "Fee to agree";
  if (p.price === 0) return "Free";
  return `${p.currency ?? ""} ${p.price}${p.kind === "traveller" ? " per kg" : ""}`.trim();
}

type Paged<T> = { data: T[]; meta: { total: number; page: number; last_page: number }; counts: Record<string, number> };

type Outcome = "completed" | "failed" | "cancelled";
type Decision = { match: GoodsMatchRow; outcome: Outcome; failed_member_id: number | null };

const MATCH_TABS = [
  ["live", "In progress"],
  ["agreed", "Agreed"],
  ["completed", "Delivered"],
  ["failed", "Failed"],
  ["unconfirmed", "Unconfirmed"],
  ["cancelled", "Cancelled"],
  ["all", "All"],
] as const;

const POST_TABS = [
  ["open", "Open"],
  ["matched", "Matched"],
  ["expired", "Date passed"],
  ["closed", "Closed"],
  ["hidden", "Hidden"],
  ["all", "All"],
] as const;

const MATCH_COLOR: Record<string, "info" | "success" | "warning" | "destructive" | "secondary"> = {
  requested: "secondary", introduced: "info", agreed: "info", disputed: "destructive", completed: "success",
  failed: "destructive", cancelled: "secondary", declined: "secondary", expired: "secondary", unconfirmed: "warning",
};

const POST_COLOR: Record<string, "info" | "success" | "warning" | "destructive" | "secondary"> = {
  open: "info", matched: "success", expired: "secondary", closed: "secondary", hidden: "destructive",
};

const ANSWER: Record<string, string> = { yes: "✅ arrived", no: "❌ problem", wait: "⏳ not yet" };

const LIVE = ["requested", "introduced", "agreed"];

function Chips({ tabs, value, counts, onChange }: { tabs: readonly (readonly [string, string])[]; value: string; counts?: Record<string, number>; onChange: (v: string) => void }) {
  const total = counts ? Object.values(counts).reduce((s, n) => s + n, 0) : 0;
  const count = (v: string) => (v === "all" ? total : v === "live" ? LIVE.reduce((s, k) => s + (counts?.[k] ?? 0), 0) : counts?.[v] ?? 0);

  return (
    <div className="flex flex-wrap gap-2">
      {tabs.map(([v, label]) => (
        <button
          key={v}
          type="button"
          aria-pressed={value === v}
          onClick={() => onChange(v)}
          className={cn("px-3 py-1.5 rounded-md text-sm font-medium border", value === v ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary")}
        >
          {label}
          {counts && <span className="ml-1.5 opacity-75">{count(v)}</span>}
        </button>
      ))}
    </div>
  );
}

function Person({ member, role }: { member: GoodsMember | null; role?: string }) {
  if (!member) return <span className="text-default-500">—</span>;
  return (
    <div className="text-sm">
      <Link className="text-primary font-medium" href={`/members/${member.id}`}>{member.name}</Link>
      {member.username && <span className="text-default-500"> @{member.username}</span>}
      {role && <span className="text-default-500"> · {role}</span>}
      <div className="text-xs text-default-500">
        {member.deals} deal{member.deals === 1 ? "" : "s"}
        {member.rating_count > 0 && <> · ⭐ {member.rating_avg?.toFixed(1)} ({member.rating_count})</>}
        {member.failures_year > 0 && <span className="text-destructive"> · {member.failures_year} failed this year</span>}
      </div>
    </div>
  );
}

function Pager({ meta, page, setPage }: { meta?: { page: number; last_page: number }; page: number; setPage: (p: number) => void }) {
  if (!meta || meta.last_page <= 1) return null;
  return (
    <div className="flex items-center justify-between p-4 border-t border-default-200">
      <span className="text-sm text-default-500">Page {meta.page} of {meta.last_page}</span>
      <div className="flex gap-2">
        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
        <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
      </div>
    </div>
  );
}

const GoodsInner = () => {
  const [url, setUrl] = useUrlState({ tab: "disputes", matches: "live", matches_page: "1", posts: "open", posts_page: "1", search: "" });
  const matchStatus = url.matches;
  const matchPage = pageNumber(url.matches_page);
  const setMatchPage = (next: number) => setUrl({ matches_page: String(next) });
  const postStatus = url.posts;
  const postPage = pageNumber(url.posts_page);
  const setPostPage = (next: number) => setUrl({ posts_page: String(next) });
  const query = url.search;
  const [search, setSearch] = useState(query);
  // Back and forward change the address; the box follows it.
  useEffect(() => setSearch(query), [query]);
  const [decision, setDecision] = useState<Decision | null>(null);
  const [note, setNote] = useState("");
  const [hiding, setHiding] = useState<GoodsPostRow | null>(null);
  const [reason, setReason] = useState("");

  const disputes = useApiQuery<Paged<GoodsMatchRow>>(["goods-matches", "disputed"], "/admin/goods/matches?status=disputed", { refetchInterval: 30_000 });

  const mqs = new URLSearchParams({ status: matchStatus, page: String(matchPage) });
  const matches = useApiQuery<Paged<GoodsMatchRow>>(["goods-matches", mqs.toString()], `/admin/goods/matches?${mqs.toString()}`, { placeholderData: (previous) => previous });
  useLastPageAtMost(matches.data?.meta.last_page, matchPage, setMatchPage);

  const pqs = new URLSearchParams({ status: postStatus, page: String(postPage) });
  if (query) pqs.set("search", query);
  const posts = useApiQuery<Paged<GoodsPostRow>>(["goods-posts", pqs.toString()], `/admin/goods/posts?${pqs.toString()}`, { placeholderData: (previous) => previous });
  useLastPageAtMost(posts.data?.meta.last_page, postPage, setPostPage);

  const resolve = useApiMutation<unknown, Decision & { note: string }>(
    (req, d) => req(`/admin/goods/matches/${d.match.id}/resolve`, "POST", { outcome: d.outcome, failed_member_id: d.failed_member_id, note: d.note || null }),
    [["goods-matches"], ["dashboard"]]
  );
  const hide = useApiMutation<unknown, { post: GoodsPostRow; reason: string }>(
    (req, { post, reason }) => req(`/admin/goods/posts/${post.id}/hide`, "POST", { reason }),
    [["goods-posts"], ["goods-matches"], ["dashboard"]]
  );

  const decide = (match: GoodsMatchRow, outcome: Outcome, failed: GoodsMember | null = null) => {
    setNote("");
    setDecision({ match, outcome, failed_member_id: failed?.id ?? null });
  };

  const submitDecision = () => {
    if (!decision) return;
    resolve
      .mutateAsync({ ...decision, note })
      .then(() => { toast.success("Decided. Both members have been told."); setDecision(null); })
      .catch((e: Error) => toast.error(errorText(e)));
  };

  const submitHide = () => {
    if (!hiding) return;
    hide
      .mutateAsync({ post: hiding, reason })
      .then(() => { toast.success("Hidden. The group card is gone and the member was told."); setHiding(null); setReason(""); })
      .catch((e: Error) => toast.error(errorText(e)));
  };

  const decisionText = (d: Decision) => {
    if (d.outcome === "completed") return "Counts as delivered: both get a deal on their record and are asked to rate each other.";
    if (d.outcome === "cancelled") return "Closed with nobody at fault. Nothing goes on either record.";
    const who = d.failed_member_id === d.match.traveller?.id ? d.match.traveller : d.match.sender;
    const second = (who?.failures_year ?? 0) + 1 >= 2;
    return `Failed, with ${who?.name ?? "this member"} at fault.` + (second ? " This is their second failure within a year: their open posts are hidden and they cannot post again." : "");
  };

  return (
    <div>
      <PageTitle
        title="Goods board"
        description="Travellers with spare kilos and members who need something carried. No money goes through the platform; members agree between themselves. A “no” at the check-in after the flight opens a dispute here."
      />

      <Tabs value={["disputes", "matches", "posts"].includes(url.tab) ? url.tab : "disputes"} onValueChange={(tab) => setUrl({ tab })}>
        <TabsList>
          <TabsTrigger value="disputes">
            Disputes{disputes.data && disputes.data.meta.total > 0 && <Badge color="destructive" className="ml-2">{disputes.data.meta.total}</Badge>}
          </TabsTrigger>
          <TabsTrigger value="matches">Matches</TabsTrigger>
          <TabsTrigger value="posts">Posts</TabsTrigger>
        </TabsList>

        <TabsContent value="disputes" className="mt-4 space-y-3">
          {disputes.error && <ErrorState error={disputes.error} onRetry={() => disputes.refetch()} title="Disputes could not be loaded" />}
          {disputes.isLoading && <LoadingRows />}
          {disputes.data?.data.length === 0 && (
            <Card>
              <EmptyState icon={Scale} title="No disputes">
                Every check-in so far has been fine. A “no” after a flight opens a dispute here for you to decide.
              </EmptyState>
            </Card>
          )}
          {disputes.data?.data.map((m) => (
            <Card key={m.id}>
              <CardContent className="p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="font-medium text-default-900">
                    #{m.id} · {m.route} · flight {formatDate(m.flight_date)} · {m.kilos} kg
                  </div>
                  <span className="text-xs text-default-500">agreed {formatDateTime(m.agreed_at)}</span>
                </div>
                {m.parcel && <blockquote className="border-l-4 border-default-300 pl-3 whitespace-pre-wrap text-default-800">{m.parcel}</blockquote>}
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="rounded-md border border-default-200 p-3">
                    <Person member={m.traveller} role="traveller" />
                    <div className="text-sm mt-1">Check-in: {m.checkin.traveller ? ANSWER[m.checkin.traveller] : "no answer"}</div>
                  </div>
                  <div className="rounded-md border border-default-200 p-3">
                    <Person member={m.sender} role="sender" />
                    <div className="text-sm mt-1">Check-in: {m.checkin.sender ? ANSWER[m.checkin.sender] : "no answer"}</div>
                  </div>
                </div>
                <p className="text-xs text-default-500">Talk to both before deciding. Two failures within a year take a member off the board.</p>
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" color="success" onClick={() => decide(m, "completed")}>It was delivered</Button>
                  <Button size="sm" variant="outline" color="destructive" disabled={!m.traveller} onClick={() => decide(m, "failed", m.traveller)}>Failed: traveller at fault</Button>
                  <Button size="sm" variant="outline" color="destructive" disabled={!m.sender} onClick={() => decide(m, "failed", m.sender)}>Failed: sender at fault</Button>
                  <Button size="sm" variant="outline" onClick={() => decide(m, "cancelled")}>Close, nobody at fault</Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </TabsContent>

        <TabsContent value="matches" className="mt-4 space-y-4">
          <Chips tabs={MATCH_TABS} value={matchStatus} counts={matches.data?.counts} onChange={(v) => setUrl({ matches: v, matches_page: "1" })} />
          <Card>
            <CardContent className="p-0">
              {matches.error && <ErrorState className="m-4" error={matches.error} onRetry={() => matches.refetch()} title="Matches could not be loaded" />}
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>#</TableHead>
                    <TableHead>Trip</TableHead>
                    <TableHead>Traveller</TableHead>
                    <TableHead>Sender</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Check-in</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {matches.isLoading && <TableMessage colSpan={7}><LoadingRows className="p-4" /></TableMessage>}
                  {matches.data?.data.length === 0 && (
                    <TableMessage colSpan={7}>
                      <EmptyState icon={Link2} compact title="No matches here">A match starts when a member presses “Interested” on a trip or a parcel.</EmptyState>
                    </TableMessage>
                  )}
                  {matches.data?.data.map((m) => (
                    <TableRow key={m.id}>
                      <TableCell className="text-sm">{m.id}</TableCell>
                      <TableCell className="text-sm">
                        <div className="whitespace-nowrap">{m.route}</div>
                        <div className="text-xs text-default-500">{formatDate(m.flight_date)} · {m.kilos ?? "?"} kg</div>
                      </TableCell>
                      <TableCell><Person member={m.traveller} /></TableCell>
                      <TableCell><Person member={m.sender} /></TableCell>
                      <TableCell>
                        <Badge color={MATCH_COLOR[m.status]} variant="soft" className="capitalize">{m.status}</Badge>
                        {m.ratings.length > 0 && <div className="text-xs text-default-500 mt-1">{m.ratings.map((r) => `${r.stars}⭐${r.visible ? "" : " (hidden)"}`).join(", ")}</div>}
                      </TableCell>
                      <TableCell className="text-xs text-default-600 whitespace-nowrap">
                        {m.checkin_on ? <>
                          <div>{formatDate(m.checkin_on)}</div>
                          <div>T: {m.checkin.traveller ? ANSWER[m.checkin.traveller] : "—"}</div>
                          <div>S: {m.checkin.sender ? ANSWER[m.checkin.sender] : "—"}</div>
                        </> : "—"}
                      </TableCell>
                      <TableCell>
                        {["agreed", "unconfirmed", "disputed"].includes(m.status) && (
                          <Button size="sm" variant="outline" color="secondary" onClick={() => decide(m, "completed")}>Decide</Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <Pager meta={matches.data?.meta} page={matchPage} setPage={setMatchPage} />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="posts" className="mt-4 space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <Chips tabs={POST_TABS} value={postStatus} counts={posts.data?.counts} onChange={(v) => setUrl({ posts: v, posts_page: "1" })} />
            <form className="flex gap-2 ml-auto" role="search" onSubmit={(e) => { e.preventDefault(); setUrl({ search: search.trim(), posts_page: "1" }); }}>
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Member or words in the post" className="w-60" aria-label="Search posts" />
              <Button type="submit" variant="outline">Search</Button>
            </form>
          </div>
          <Card>
            <CardContent className="p-0">
              {posts.error && <ErrorState className="m-4" error={posts.error} onRetry={() => posts.refetch()} title="Posts could not be loaded" />}
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Post</TableHead>
                    <TableHead>Member</TableHead>
                    <TableHead>What</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {posts.isLoading && <TableMessage colSpan={5}><LoadingRows className="p-4" /></TableMessage>}
                  {posts.data?.data.length === 0 && (
                    <TableMessage colSpan={5}>
                      <EmptyState icon={Package} compact title={query ? "No post matches" : "No posts here"}>
                        {query ? "Search looks at the member's name and the words of the post." : "Members post trips with /travel and parcels with /send, in the bot or on the website."}
                      </EmptyState>
                    </TableMessage>
                  )}
                  {posts.data?.data.map((p) => (
                    <TableRow key={p.id}>
                      <TableCell className="text-sm">
                        <div className="whitespace-nowrap">{p.kind === "traveller" ? "🧳 Trip" : "📦 Parcel"} · {p.route}</div>
                        <div className="text-xs text-default-500">
                          {p.kind === "sender" && "around "}{formatDate(p.date)} · {p.kind === "traveller" ? `${p.kilos_left ?? p.kilos} of ${p.kilos} kg free` : `${p.kilos} kg`}
                        </div>
                      </TableCell>
                      <TableCell><Person member={p.member} /></TableCell>
                      <TableCell className="text-sm max-w-md">
                        <div className="text-xs text-default-500">{p.categories.join(", ")}{fee(p) && <> · <span className="text-default-700">{fee(p)}</span></>}{p.mode === "request" && " · handed over at the other end"}</div>
                        {p.description && <div className="whitespace-pre-wrap">{p.description}</div>}
                        {p.hidden_reason && <div className="text-xs text-destructive mt-1">Hidden: {p.hidden_reason}</div>}
                      </TableCell>
                      <TableCell>
                        <Badge color={POST_COLOR[p.status]} variant="soft" className="capitalize">{p.status === "expired" ? "date passed" : p.status}</Badge>
                        {!p.in_group && p.status === "open" && <div className="text-xs text-default-500 mt-1">not in the group</div>}
                      </TableCell>
                      <TableCell>
                        {p.status !== "hidden" && (
                          <Button size="sm" variant="outline" color="destructive" onClick={() => { setReason(""); setHiding(p); }}>Hide</Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <Pager meta={posts.data?.meta} page={postPage} setPage={setPostPage} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={decision !== null} onOpenChange={(o) => !o && setDecision(null)}>
        <DialogContent size="lg">
          <DialogHeader>
            <DialogTitle>Decide match #{decision?.match.id}</DialogTitle>
            <DialogDescription>{decision?.match.route} · {formatDate(decision?.match.flight_date)} · {decision?.match.traveller?.name} carrying for {decision?.match.sender?.name}</DialogDescription>
          </DialogHeader>
          {decision && (
            <div className="space-y-4">
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant={decision.outcome === "completed" ? undefined : "outline"} onClick={() => setDecision({ ...decision, outcome: "completed", failed_member_id: null })}>Delivered</Button>
                <Button size="sm" variant={decision.outcome === "failed" && decision.failed_member_id === decision.match.traveller?.id ? undefined : "outline"} color="destructive" disabled={!decision.match.traveller}
                  onClick={() => setDecision({ ...decision, outcome: "failed", failed_member_id: decision.match.traveller?.id ?? null })}>Traveller at fault</Button>
                <Button size="sm" variant={decision.outcome === "failed" && decision.failed_member_id === decision.match.sender?.id ? undefined : "outline"} color="destructive" disabled={!decision.match.sender}
                  onClick={() => setDecision({ ...decision, outcome: "failed", failed_member_id: decision.match.sender?.id ?? null })}>Sender at fault</Button>
                <Button size="sm" variant={decision.outcome === "cancelled" ? undefined : "outline"} onClick={() => setDecision({ ...decision, outcome: "cancelled", failed_member_id: null })}>Nobody at fault</Button>
              </div>
              <p className="text-sm text-default-700">{decisionText(decision)}</p>
              <div className="space-y-1.5">
                <Label htmlFor="goods-note">Note for the record</Label>
                <Textarea id="goods-note" rows={3} value={note} onChange={(e) => setNote(e.target.value)} placeholder="What both sides said, and why you decided this way. Members do not see it." />
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setDecision(null)}>Cancel</Button>
            <Button color={decision?.outcome === "failed" ? "destructive" : undefined} disabled={resolve.isPending} onClick={submitDecision}>Decide</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={hiding !== null} onOpenChange={(o) => !o && setHiding(null)}>
        <DialogContent size="md">
          <DialogHeader>
            <DialogTitle>Hide this post</DialogTitle>
            <DialogDescription>
              {hiding?.kind === "traveller" ? "Trip" : "Parcel"} by {hiding?.member?.name} · {hiding?.route} · {formatDate(hiding?.date)}.
              The group card is deleted, open requests about it end, and the member is told why.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="goods-reason">Reason (sent to the member)</Label>
            <Input id="goods-reason" minLength={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="For example: asked for money to carry" />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setHiding(null)}>Cancel</Button>
            <Button color="destructive" disabled={hide.isPending || reason.trim().length < 3} onClick={submitHide}>Hide post</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

const GoodsPage = () => (
  <Suspense>
    <GoodsInner />
  </Suspense>
);

export default GoodsPage;
