"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import { useApiQuery, useApiMutation, formatDateTime, type QuestionRow, type QuestionDetail } from "@/lib/api";

type Page = { data: QuestionRow[]; meta: { total: number; page: number; last_page: number }; counts: Record<string, number>; categories: Record<string, string> };

const TABS = [
  ["all", "All"],
  ["open", "Open"],
  ["solved", "Solved"],
  ["closed", "Closed"],
] as const;

const STATUS_COLOR = { open: "info", solved: "success", closed: "secondary" } as const;

const QuestionsPage = () => {
  const [status, setStatus] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [openId, setOpenId] = useState<number | null>(null);

  const qs = new URLSearchParams({ status, page: String(page) });
  if (query) qs.set("search", query);
  const { data, isLoading, error } = useApiQuery<Page>(["questions", qs.toString()], `/admin/questions?${qs.toString()}`, { refetchInterval: 30_000 });
  const detail = useApiQuery<{ question: QuestionDetail }>(["question", openId], `/admin/questions/${openId}`, { enabled: openId !== null });

  const invalidate = [["questions"], ["question", openId], ["faq"], ["dashboard"]];
  const solve = useApiMutation<unknown, number>((req, replyId) => req(`/admin/questions/${openId}/solve`, "POST", { reply_id: replyId }), invalidate);
  const promote = useApiMutation<{ faq_id: number; written_by: string }>((req) => req(`/admin/questions/${openId}/promote`, "POST"), invalidate);

  const total = data ? Object.values(data.counts).reduce((s, n) => s + n, 0) : 0;
  const q = detail.data?.question;

  return (
    <div>
      <PageTitle title="Questions" description="Asked in the bot, answered in the questions group. Solved ones can become FAQ entries the assistant learns from." />

      <div className="flex flex-wrap items-center gap-2 mb-4">
        {TABS.map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => { setStatus(value); setPage(1); }}
            className={cn("px-3 py-1.5 rounded-md text-sm font-medium border", status === value ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary")}
          >
            {label}
            {data && <span className="ml-1.5 opacity-75">{value === "all" ? total : data.counts[value] ?? 0}</span>}
          </button>
        ))}
        <form className="flex gap-2 ml-auto" onSubmit={(e) => { e.preventDefault(); setQuery(search.trim()); setPage(1); }}>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Words in the question" className="w-56" />
          <Button type="submit" variant="outline">Search</Button>
        </form>
      </div>

      <Card>
        <CardContent className="p-0">
          {error && <div className="p-4 text-destructive">{error.message}</div>}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Question</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Topic</TableHead>
                <TableHead>Asked by</TableHead>
                <TableHead>Answers</TableHead>
                <TableHead>Last activity</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableRow><TableCell colSpan={6} className="text-default-500">Loading…</TableCell></TableRow>}
              {data?.data.length === 0 && <TableRow><TableCell colSpan={6} className="text-default-500">No questions yet.</TableCell></TableRow>}
              {data?.data.map((row) => (
                <TableRow key={row.id} className="cursor-pointer" onClick={() => setOpenId(row.id)}>
                  <TableCell className="max-w-md font-medium text-default-900">{row.title}{row.faq_id && <Badge color="info" variant="soft" className="ml-2">in FAQ</Badge>}</TableCell>
                  <TableCell><Badge color={STATUS_COLOR[row.status]} variant="soft" className="capitalize">{row.status}</Badge></TableCell>
                  <TableCell className="text-sm">{data.categories[row.category] ?? row.category}</TableCell>
                  <TableCell className="text-sm">{row.asker?.name ?? "—"}{row.anonymous && <span className="text-default-500"> (anonymous in the group)</span>}</TableCell>
                  <TableCell>{row.replies_count}</TableCell>
                  <TableCell className="text-sm text-default-600 whitespace-nowrap">{formatDateTime(row.last_activity_at ?? row.created_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between p-4 border-t border-default-200">
              <span className="text-sm text-default-500">Page {data.meta.page} of {data.meta.last_page}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
                <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={openId !== null} onOpenChange={(o) => !o && setOpenId(null)}>
        <DialogContent size="3xl" className="max-h-[90vh] overflow-y-auto">
          {!q ? (
            <div className="p-6 text-default-500">Loading…</div>
          ) : (
            <>
              <DialogHeader>
                <DialogTitle>{q.title}</DialogTitle>
                <DialogDescription>
                  {q.asker?.name ?? "A member"}{q.anonymous ? " (anonymous in the group)" : ""} · {formatDateTime(q.created_at)} · <span className="capitalize">{q.status}</span>
                </DialogDescription>
              </DialogHeader>
              <p className="whitespace-pre-wrap text-default-800">{q.body}</p>

              <div>
                <div className="font-medium text-default-900 mb-2">Answers ({q.replies.length})</div>
                {q.replies.length === 0 && <div className="text-sm text-default-500">No answers yet.</div>}
                <ul className="space-y-3">
                  {q.replies.map((r) => (
                    <li key={r.id} className={cn("rounded-md border p-3", r.is_solution ? "border-success bg-success/5" : "border-default-200")}>
                      <div className="flex items-center justify-between gap-2 text-sm">
                        <span className="font-medium">{r.member?.name ?? "?"} <span className="text-default-500">· {r.member?.points ?? 0} points</span></span>
                        <span className="text-default-500">{formatDateTime(r.created_at)}</span>
                      </div>
                      <p className="mt-1 whitespace-pre-wrap text-sm">{r.text}</p>
                      <div className="mt-2 flex items-center gap-2">
                        {r.is_solution && <Badge color="success" variant="soft">solution</Badge>}
                        {q.status !== "solved" && r.member?.id !== q.asker?.id && (
                          <Button size="sm" variant="outline" disabled={solve.isPending} onClick={() => solve.mutateAsync(r.id).then(() => toast.success("Marked as the solution; the group is told.")).catch((e: Error) => toast.error(e.message))}>
                            Mark as the solution
                          </Button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </div>

              <DialogFooter>
                {q.status === "solved" && !q.faq_id && (
                  <Button
                    disabled={promote.isPending}
                    onClick={() =>
                      promote
                        .mutateAsync()
                        .then((r) => toast.success(r.written_by === "claude" ? "Added to the FAQ in three languages." : "Added to the FAQ as an English draft; check it on the FAQ page before turning it on."))
                        .catch((e: Error) => toast.error(e.message))
                    }
                  >
                    {promote.isPending ? "Writing…" : "Add to the FAQ"}
                  </Button>
                )}
                {q.faq_id && <Badge color="info" variant="soft">In the FAQ</Badge>}
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default QuestionsPage;
