"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import PageTitle from "@/components/page-title";
import { JobStatusBadge, ScamScore } from "@/components/job-badges";
import { useApiQuery, useApiMutation, formatDateTime, type JobDetail, type AuditEntry, CATEGORY_NAMES, JOB_EMIRATE_NAMES } from "@/lib/api";

type Payload = { job: JobDetail; audit: AuditEntry[]; choices: { categories: string[]; emirates: string[] } };

type Draft = Pick<JobDetail, "title" | "company" | "category" | "emirate" | "sub_area" | "salary_min" | "salary_max" | "accommodation" | "visa" | "summary_am" | "summary_ti" | "summary_en">;

const draftOf = (job: JobDetail): Draft => ({
  title: job.title, company: job.company, category: job.category, emirate: job.emirate, sub_area: job.sub_area,
  salary_min: job.salary_min, salary_max: job.salary_max, accommodation: job.accommodation, visa: job.visa,
  summary_am: job.summary_am, summary_ti: job.summary_ti, summary_en: job.summary_en,
});

const JobPage = () => {
  const { id } = useParams<{ id: string }>();
  const key = ["job", id];
  const { data, isLoading, error } = useApiQuery<Payload>(key, `/admin/jobs/${id}`);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [blockCompany, setBlockCompany] = useState(false);

  useEffect(() => {
    if (data) setDraft(draftOf(data.job));
  }, [data]);

  const invalidate = [key, ["jobs"], ["dashboard"]];
  const approve = useApiMutation<unknown, Draft>((request, body) => request(`/admin/jobs/${id}/approve`, "POST", body), invalidate);
  const save = useApiMutation<unknown, Draft>((request, body) => request(`/admin/jobs/${id}`, "PATCH", body), invalidate);
  const reject = useApiMutation<unknown, { reason: string; block_company: boolean }>((request, body) => request(`/admin/jobs/${id}/reject`, "POST", body), invalidate);
  const close = useApiMutation<unknown>((request) => request(`/admin/jobs/${id}/close`, "POST"), invalidate);

  if (error) return <div className="text-destructive">{error.message}</div>;
  if (isLoading || !data || !draft) return <div className="text-default-600">Loading…</div>;

  const job = data.job;
  const act = (promise: Promise<unknown>, done: string) => promise.then(() => toast.success(done)).catch((e: Error) => toast.error(e.message));
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => setDraft({ ...draft, [k]: v });
  const canApprove = ["review", "rejected", "pending"].includes(job.status);

  return (
    <div>
      <PageTitle title={job.title} description={`${job.company ?? "Company not stated"} · from ${job.source ?? "unknown source"} · ${formatDateTime(job.created_at)}`}>
        <Link href="/jobs"><Button variant="outline" size="sm">All jobs</Button></Link>
      </PageTitle>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">Listing</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">Title</Label>
                  <Input value={draft.title} onChange={(e) => set("title", e.target.value)} />
                </div>
                <div>
                  <Label className="mb-1.5 block">Company</Label>
                  <Input value={draft.company ?? ""} onChange={(e) => set("company", e.target.value || null)} />
                </div>
                <div>
                  <Label className="mb-1.5 block">Area</Label>
                  <Input value={draft.sub_area ?? ""} onChange={(e) => set("sub_area", e.target.value || null)} placeholder="Deira, Al Quoz…" />
                </div>
                <div>
                  <Label className="mb-1.5 block">Work</Label>
                  <Select value={draft.category} onValueChange={(v) => set("category", v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{data.choices.categories.map((c) => <SelectItem key={c} value={c}>{CATEGORY_NAMES[c] ?? c}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="mb-1.5 block">Where</Label>
                  <Select value={draft.emirate} onValueChange={(v) => set("emirate", v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{data.choices.emirates.map((e) => <SelectItem key={e} value={e}>{JOB_EMIRATE_NAMES[e] ?? e}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="mb-1.5 block">Salary from (monthly)</Label>
                  <Input type="number" min={0} value={draft.salary_min ?? ""} onChange={(e) => set("salary_min", e.target.value ? Number(e.target.value) : null)} />
                </div>
                <div>
                  <Label className="mb-1.5 block">Salary to</Label>
                  <Input type="number" min={0} value={draft.salary_max ?? ""} onChange={(e) => set("salary_max", e.target.value ? Number(e.target.value) : null)} />
                </div>
                {(["accommodation", "visa"] as const).map((field) => (
                  <div key={field}>
                    <Label className="mb-1.5 block capitalize">{field}</Label>
                    <Select value={draft[field]} onValueChange={(v) => set(field, v as Draft[typeof field])}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="yes">Provided</SelectItem>
                        <SelectItem value="no">Not provided</SelectItem>
                        <SelectItem value="unknown">Not stated</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                ))}
              </div>

              {([["summary_am", "Amharic summary"], ["summary_ti", "Tigrinya summary"], ["summary_en", "English summary"]] as const).map(([field, label]) => (
                <div key={field}>
                  <Label className="mb-1.5 block">{label}</Label>
                  <Textarea rows={3} value={draft[field] ?? ""} onChange={(e) => set(field, e.target.value || null)} className="text-base leading-7" />
                </div>
              ))}

              <div className="flex flex-wrap gap-2">
                {canApprove ? (
                  <Button disabled={approve.isPending} onClick={() => act(approve.mutateAsync(draft), "Approved and published.")}>Approve and publish</Button>
                ) : (
                  <Button variant="outline" disabled={save.isPending} onClick={() => act(save.mutateAsync(draft), job.status === "published" ? "Saved; the channel card is updated." : "Saved.")}>Save changes</Button>
                )}
                {job.url && <a href={job.url} target="_blank" rel="noreferrer"><Button variant="outline">Open the original</Button></a>}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">As fetched</CardTitle></CardHeader>
            <CardContent>
              <pre className="whitespace-pre-wrap font-sans text-sm text-default-700 max-h-96 overflow-y-auto">{job.raw_text ?? "The raw listing has been pruned."}</pre>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">Decision</CardTitle></CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="flex justify-between"><span className="text-default-500">Status</span><JobStatusBadge status={job.status} /></div>
              {job.review_reason && <div className="rounded-md bg-warning/10 text-warning p-3">{job.review_reason}</div>}
              <div className="flex justify-between"><span className="text-default-500">Scam score</span><ScamScore score={job.scam_score} /></div>
              <div className="flex justify-between"><span className="text-default-500">Classified by</span><span>{job.classified_by === "claude" ? "Claude" : "keyword rules"}</span></div>
              <div className="flex justify-between"><span className="text-default-500">On the channel</span><span>{job.on_channel ? "yes" : "no"}</span></div>
              {job.duplicate_of && <div>Duplicate of <Link className="text-primary" href={`/jobs/${job.duplicate_of}`}>job #{job.duplicate_of}</Link></div>}

              {job.scam_reasons.length > 0 && (
                <div>
                  <div className="text-default-500 mb-1">Why it scored</div>
                  <ul className="list-disc pl-5 space-y-1">{job.scam_reasons.map((r) => <li key={r}>{r}</li>)}</ul>
                </div>
              )}

              {job.reports.length > 0 && (
                <div>
                  <div className="text-default-500 mb-1">Reports from members</div>
                  <ul className="space-y-1">{job.reports.map((r, i) => <li key={i}>{r.member ?? "A member"} · {formatDateTime(r.at)}</li>)}</ul>
                </div>
              )}

              {job.status !== "rejected" && (
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button color="destructive" variant="outline" className="w-full">Reject</Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle>Reject this job?</AlertDialogTitle>
                      <AlertDialogDescription>It comes off the channel and no one is alerted. Blocking the company sends its future listings straight to review.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-3">
                      <div>
                        <Label htmlFor="reject-reason" className="mb-1.5 block">Reason</Label>
                        <Input id="reject-reason" value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} placeholder="Charges a visa fee" />
                      </div>
                      {job.company && (
                        <label className="flex items-center gap-2 text-sm">
                          <Switch checked={blockCompany} onCheckedChange={setBlockCompany} />
                          Block {job.company}
                        </label>
                      )}
                    </div>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancel</AlertDialogCancel>
                      <AlertDialogAction className="bg-destructive text-destructive-foreground" onClick={() => act(reject.mutateAsync({ reason: rejectReason, block_company: blockCompany }), "Rejected.")}>Reject</AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              )}

              {job.status === "published" && (
                <Button variant="outline" className="w-full" disabled={close.isPending} onClick={() => act(close.mutateAsync(), "Closed; the card is marked closed.")}>Mark closed</Button>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">History</CardTitle></CardHeader>
            <CardContent>
              {data.audit.length === 0 ? (
                <div className="text-sm text-default-500">No moderator action yet.</div>
              ) : (
                <ul className="space-y-2 text-sm">
                  {data.audit.map((entry) => (
                    <li key={entry.id}>
                      <div className="text-default-900">{entry.action}</div>
                      <div className="text-xs text-default-500">{formatDateTime(entry.created_at)}</div>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default JobPage;
