"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import { useApiQuery, useApiMutation, formatDateTime, type EmployerReportRow } from "@/lib/api";

const SafetyPage = () => {
  const [status, setStatus] = useState<"open" | "closed" | "all">("open");
  const { data, isLoading, error } = useApiQuery<{ data: EmployerReportRow[]; counts: Record<string, number> }>(["employer-reports", status], `/admin/employer-reports?status=${status}`);
  const [notes, setNotes] = useState<Record<number, string>>({});

  const update = useApiMutation<unknown, { id: number; status: "open" | "closed"; note: string | null }>(
    (req, { id, ...body }) => req(`/admin/employer-reports/${id}`, "PUT", body),
    [["employer-reports"]]
  );

  return (
    <div>
      <PageTitle title="Safety reports" description="Private reports about employers from /safe in the bot. Nothing here is ever posted. The reporter is shown only when they agreed to be contacted." />

      <div className="flex gap-2 mb-4">
        {(["open", "closed", "all"] as const).map((s) => (
          <button key={s} type="button" onClick={() => setStatus(s)}
            className={cn("px-3 py-1.5 rounded-md text-sm font-medium border capitalize", status === s ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary")}>
            {s}{data && s !== "all" && <span className="ml-1.5 opacity-75">{data.counts[s] ?? 0}</span>}
          </button>
        ))}
      </div>

      {error && <div className="text-destructive">{error.message}</div>}
      {isLoading && <div className="text-default-500">Loading…</div>}
      {data?.data.length === 0 && <Card><CardContent className="p-6 text-default-500">No {status === "all" ? "" : status} reports.</CardContent></Card>}

      <div className="space-y-3">
        {data?.data.map((r) => (
          <Card key={r.id}>
            <CardContent className="p-4 space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="font-medium text-default-900">
                  {r.employer_name}
                  {r.times_named > 1 && <Badge color="destructive" variant="soft" className="ml-2">named in {r.times_named} reports</Badge>}
                </div>
                <div className="text-sm text-default-500">{formatDateTime(r.created_at)}</div>
              </div>
              <p className="whitespace-pre-wrap text-sm text-default-800">{r.text}</p>
              <div className="text-sm">
                {r.member ? (
                  <span>Agreed to be contacted: <b>{r.member.name}</b>{r.member.username && <> · <a className="text-primary" href={`https://t.me/${r.member.username}`} target="_blank" rel="noreferrer">@{r.member.username}</a></>}</span>
                ) : (
                  <span className="text-default-500">Did not agree to be contacted.</span>
                )}
              </div>
              <Textarea rows={2} placeholder="Note for other moderators: what was done" value={notes[r.id] ?? r.note ?? ""} onChange={(e) => setNotes({ ...notes, [r.id]: e.target.value })} />
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={update.isPending} onClick={() => update.mutateAsync({ id: r.id, status: r.status, note: (notes[r.id] ?? r.note) || null }).then(() => toast.success("Note saved."))}>Save note</Button>
                <Button size="sm" disabled={update.isPending} onClick={() => update.mutateAsync({ id: r.id, status: r.status === "open" ? "closed" : "open", note: (notes[r.id] ?? r.note) || null }).then(() => toast.success(r.status === "open" ? "Closed." : "Reopened."))}>
                  {r.status === "open" ? "Close" : "Reopen"}
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
};

export default SafetyPage;
