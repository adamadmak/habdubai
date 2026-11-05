"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
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
import ConfirmButton from "@/components/confirm-button";
import { ErrorState, PageLoading } from "@/components/states";
import { runAction, toastResult } from "@/lib/toast";
import { useLeaveGuard } from "@/lib/unsaved";
import { useApiQuery, useApiMutation, formatDateTime, ApiError, type JobDetail, type AuditEntry, CATEGORY_NAMES, JOB_EMIRATE_NAMES } from "@/lib/api";

type Payload = { job: JobDetail; audit: AuditEntry[]; choices: { categories: string[]; emirates: string[] } };

type Draft = Pick<JobDetail, "title" | "company" | "category" | "emirate" | "sub_area" | "salary_min" | "salary_max" | "accommodation" | "visa" | "summary_am" | "summary_ti" | "summary_en" | "summary_om">;

const draftOf = (job: JobDetail): Draft => ({
  title: job.title, company: job.company, category: job.category, emirate: job.emirate, sub_area: job.sub_area,
  salary_min: job.salary_min, salary_max: job.salary_max, accommodation: job.accommodation, visa: job.visa,
  summary_am: job.summary_am, summary_ti: job.summary_ti, summary_en: job.summary_en, summary_om: job.summary_om,
});

const JobPage = () => {
  const { id } = useParams<{ id: string }>();
  const key = ["job", id];
  // No refetch on focus: a moderator correcting a listing must not lose the edit by switching tabs.
  const { data, isLoading, error, refetch } = useApiQuery<Payload>(key, `/admin/jobs/${id}`, { refetchOnWindowFocus: false });
  const [draft, setDraft] = useState<Draft | null>(null);
  const [dirty, setDirty] = useState(false);
  const [rejectReason, setRejectReason] = useState("");
  const [blockCompany, setBlockCompany] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  // While a correction is unsaved, closing the tab asks first.
  useLeaveGuard(Boolean(dirty && data && draft && JSON.stringify(draft) !== JSON.stringify(draftOf(data.job))));

  // The form follows the server until the moderator types; after that only a save resets it.
  useEffect(() => {
    if (data && !dirty) setDraft(draftOf(data.job));
  }, [data, dirty]);

  const invalidate = [key, ["jobs"], ["dashboard"]];
  const approve = useApiMutation<unknown, Draft>((request, body) => request(`/admin/jobs/${id}/approve`, "POST", body), invalidate);
  const save = useApiMutation<{ job: Partial<JobDetail> }, Draft>((request, body) => request(`/admin/jobs/${id}`, "PATCH", body), invalidate);
  const reject = useApiMutation<unknown, { reason: string; block_company: boolean }>((request, body) => request(`/admin/jobs/${id}/reject`, "POST", body), invalidate);
  const close = useApiMutation<unknown>((request) => request(`/admin/jobs/${id}/close`, "POST"), invalidate);

  if (error) {
    return (
      <div>
        <PageTitle title="Job"><Button asChild variant="outline" color="secondary" size="sm"><Link href="/jobs">All jobs</Link></Button></PageTitle>
        <ErrorState error={error} onRetry={() => refetch()} title="This job could not be loaded" />
      </div>
    );
  }
  if (isLoading || !data || !draft) return <PageLoading />;

  const job = data.job;
  // A successful save hands the form back to the server's copy.
  const act = (promise: Promise<unknown>, done: string) => runAction(promise.then(() => { setErrors({}); setDirty(false); }).catch(showFieldErrors), done);
  // What the server refused goes beside its field; the toast still says it failed.
  const showFieldErrors = (e: unknown): never => {
    if (e instanceof ApiError) setErrors(e.errors ?? {});
    throw e;
  };
  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;
  // The PATCH answers with the summary only (no summaries or salary numbers); the draft itself is what was
  // saved, so keep it and let the next refetch confirm it rather than snapping to the cached pre-save job.
  // The PATCH answers with the summary only, and the cache still holds the pre-save job until the refetch
  // lands: keep the draft (it is what was saved) and hand it back to the server copy only once that is fresh.
  const saveDraft = (body: Draft) =>
    runAction(
      save.mutateAsync(body).then(() => refetch()).then(() => { setErrors({}); setDirty(false); }).catch(showFieldErrors),
      job.status === "published" ? "Saved; the channel card is updated." : "Saved."
    );
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setDirty(true);
    setDraft({ ...draft, [k]: v });
  };
  const canApprove = ["review", "rejected", "pending"].includes(job.status);

  return (
    <div>
      <PageTitle title={job.title} description={`${job.company ?? "Company not stated"} · from ${job.source ?? "unknown source"} · ${formatDateTime(job.created_at)}`}>
        <Button asChild variant="outline" color="secondary" size="sm"><Link href="/jobs">All jobs</Link></Button>
      </PageTitle>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">Listing</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="md:col-span-2">
                  <Label htmlFor="jobs-detail-title" className="mb-1.5 block">Title</Label>
                  <Input id="jobs-detail-title" value={draft.title} onChange={(e) => set("title", e.target.value)} />
                  {err("title")}
                </div>
                <div>
                  <Label htmlFor="jobs-detail-company" className="mb-1.5 block">Company</Label>
                  <Input id="jobs-detail-company" value={draft.company ?? ""} onChange={(e) => set("company", e.target.value || null)} />
                  {err("company")}
                </div>
                <div>
                  <Label htmlFor="jobs-detail-area" className="mb-1.5 block">Area</Label>
                  <Input id="jobs-detail-area" value={draft.sub_area ?? ""} onChange={(e) => set("sub_area", e.target.value || null)} placeholder="Deira, Al Quoz…" />
                  {err("sub_area")}
                </div>
                <div>
                  <Label htmlFor="jobs-detail-work" className="mb-1.5 block">Work</Label>
                  <Select value={draft.category} onValueChange={(v) => set("category", v)}>
                    <SelectTrigger id="jobs-detail-work"><SelectValue /></SelectTrigger>
                    <SelectContent>{data.choices.categories.map((c) => <SelectItem key={c} value={c}>{CATEGORY_NAMES[c] ?? c}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="jobs-detail-where" className="mb-1.5 block">Where</Label>
                  <Select value={draft.emirate} onValueChange={(v) => set("emirate", v)}>
                    <SelectTrigger id="jobs-detail-where"><SelectValue /></SelectTrigger>
                    <SelectContent>{data.choices.emirates.map((e) => <SelectItem key={e} value={e}>{JOB_EMIRATE_NAMES[e] ?? e}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="jobs-detail-salary-from-monthly" className="mb-1.5 block">Salary from (monthly)</Label>
                  <Input id="jobs-detail-salary-from-monthly" type="number" min={0} value={draft.salary_min ?? ""} onChange={(e) => set("salary_min", e.target.value ? Number(e.target.value) : null)} />
                  {err("salary_min")}
                </div>
                <div>
                  <Label htmlFor="jobs-detail-salary-to" className="mb-1.5 block">Salary to</Label>
                  <Input id="jobs-detail-salary-to" type="number" min={0} value={draft.salary_max ?? ""} onChange={(e) => set("salary_max", e.target.value ? Number(e.target.value) : null)} />
                  {err("salary_max")}
                </div>
                {(["accommodation", "visa"] as const).map((field) => (
                  <div key={field}>
                    <Label htmlFor={`job-${field}`} className="mb-1.5 block capitalize">{field}</Label>
                    <Select value={draft[field]} onValueChange={(v) => set(field, v as Draft[typeof field])}>
                      <SelectTrigger id={`job-${field}`}><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="yes">Provided</SelectItem>
                        <SelectItem value="no">Not provided</SelectItem>
                        <SelectItem value="unknown">Not stated</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                ))}
              </div>

              {([["summary_am", "Amharic summary"], ["summary_ti", "Tigrinya summary"], ["summary_en", "English summary"], ["summary_om", "Afaan Oromoo summary"]] as const).map(([field, label]) => (
                <div key={field}>
                  <Label htmlFor={`job-${field}`} className="mb-1.5 block">{label}</Label>
                  <Textarea id={`job-${field}`} rows={3} value={draft[field] ?? ""} onChange={(e) => set(field, e.target.value || null)} className="text-base leading-7" />
                  {err(field)}
                </div>
              ))}

              <div className="flex flex-wrap gap-2">
                {canApprove ? (
                  <Button disabled={approve.isPending} onClick={() => act(approve.mutateAsync(draft), "Approved and published.")}>Approve and publish</Button>
                ) : (
                  <Button variant="outline" disabled={save.isPending} onClick={() => saveDraft(draft)}>Save changes</Button>
                )}
                {job.url && <Button asChild variant="outline" color="secondary"><a href={job.url} target="_blank" rel="noreferrer">Open the original</a></Button>}
              </div>
            </CardContent>
          </Card>

          {job.business && (
            <Card>
              <CardHeader><CardTitle className="text-base">Posted by a business</CardTitle></CardHeader>
              <CardContent className="space-y-1 text-sm">
                <p className="font-semibold">{job.business.name} · {job.business.verified ? "verified" : "not verified"} · listing {job.business.status}</p>
                {job.apply_contact && <p>Applicants reach them at <a className="text-primary underline" href={job.apply_contact} target="_blank" rel="noopener noreferrer">{job.apply_contact.replace("https://", "")}</a></p>}
                <p className="text-default-600">Pay, kind of work and place are what the business stated.</p>
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader><CardTitle className="text-base">As {job.business ? "written by the business" : "fetched"}</CardTitle></CardHeader>
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
                      <AlertDialogAction color="destructive" onClick={() => act(reject.mutateAsync({ reason: rejectReason, block_company: blockCompany }), "Rejected.")}>Reject</AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              )}

              {job.status === "published" && (
                <ConfirmButton
                  variant="outline"
                  color="secondary"
                  className="w-full"
                  label="Mark closed"
                  title="Mark this job closed?"
                  description="The channel card is marked closed and members stop getting alerts for it. It cannot be reopened from here."
                  confirmLabel="Mark closed"
                  tone="warning"
                  onConfirm={() => toastResult(close.mutateAsync(), "Closed; the card is marked closed.")}
                />
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
                      <div className="text-default-900">{entry.action.replace(/[._]/g, " ")}</div>
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
