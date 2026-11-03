"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Rss } from "lucide-react";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { runAction } from "@/lib/toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import PageTitle from "@/components/page-title";
import { useApi, useApiQuery, useApiMutation, formatDateTime, ApiError, errorText, type JobSourceRow, type SourceKind, type SourceTestResult } from "@/lib/api";

type Form = { id?: number; name: string; kind: SourceKind; interval_minutes: number; active: boolean; config: Record<string, string> };

const EMPTY: Form = { name: "", kind: "rss", interval_minutes: 60, active: true, config: {} };

const FIELDS: Record<SourceKind, { key: string; label: string; hint: string; placeholder?: string; required?: boolean }[]> = {
  rss: [
    { key: "url", label: "Feed URL", hint: "An RSS or Atom feed. Preferred: a feed is meant to be read by machines.", placeholder: "https://example.com/jobs.rss", required: true },
    { key: "limit", label: "Most items per run", hint: "Defaults to 50.", placeholder: "50" },
  ],
  html: [
    { key: "url", label: "Listing page URL", hint: "A search or results page on the site.", placeholder: "https://example.com/jobs?location=dubai", required: true },
    { key: "item", label: "Item selector", hint: "CSS selector for one listing on the page.", placeholder: "li.job-card", required: true },
    { key: "title", label: "Title selector", hint: "Inside the item. Its text is the title.", placeholder: "h2 a", required: true },
    { key: "link", label: "Link selector", hint: "Inside the item; its href is the listing URL. Defaults to the title, then the first link.", placeholder: "a.details" },
    { key: "company", label: "Company selector", hint: "Optional.", placeholder: ".company" },
    { key: "location", label: "Location selector", hint: "Optional.", placeholder: ".location" },
    { key: "snippet", label: "Description selector", hint: "Optional. Without it the whole item's text is used.", placeholder: ".summary" },
    { key: "limit", label: "Most items per run", hint: "Defaults to 50.", placeholder: "50" },
  ],
  telegram: [
    { key: "chat_id", label: "Channel or group id", hint: "Numeric id (-100…). Add the bot to that channel as an admin; posts arrive on their own.", placeholder: "-1001234567890", required: true },
  ],
};

const STATUS_COLOR = { ok: "success", failing: "warning", paused: "destructive", off: "secondary" } as const;

const SourcesPage = () => {
  const { data, isLoading, error, refetch } = useApiQuery<{ data: JobSourceRow[] }>(["job-sources"], "/admin/job-sources");
  const { request } = useApi();
  const [form, setForm] = useState<Form | null>(null);
  const [test, setTest] = useState<SourceTestResult | null>(null);
  const [testing, setTesting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const save = useApiMutation<{ source: JobSourceRow }, Form>(
    (req, f) => {
      const body = { name: f.name, kind: f.kind, interval_minutes: f.interval_minutes, active: f.active, config: cleanConfig(f.config) };
      return f.id ? req(`/admin/job-sources/${f.id}`, "PUT", body) : req("/admin/job-sources", "POST", body);
    },
    [["job-sources"], ["dashboard"]]
  );
  const run = useApiMutation<{ result: { found: number; new: number; error: string | null } }, number>((req, id) => req(`/admin/job-sources/${id}/run`, "POST"), [["job-sources"], ["jobs"], ["dashboard"]]);
  const remove = useApiMutation<unknown, number>((req, id) => req(`/admin/job-sources/${id}`, "DELETE"), [["job-sources"]]);

  const open = (source?: JobSourceRow) => {
    setTest(null);
    setErrors({});
    setForm(
      source
        ? { id: source.id, name: source.name, kind: source.kind, interval_minutes: source.interval_minutes, active: source.active, config: Object.fromEntries(Object.entries(source.config ?? {}).map(([k, v]) => [k, v == null ? "" : String(v)])) }
        : { ...EMPTY, config: {} }
    );
  };

  const runTest = async () => {
    if (!form) return;
    setTesting(true);
    setTest(null);
    try {
      setTest(await request<SourceTestResult>("/admin/job-sources/test", "POST", { kind: form.kind, config: cleanConfig(form.config) }));
    } catch (e) {
      setTest({ ok: false, error: e instanceof ApiError ? Object.values(e.errors).flat()[0] ?? e.message : String(e), listings: [] });
    } finally {
      setTesting(false);
    }
  };

  const submit = () => {
    if (!form) return;
    setErrors({});
    save
      .mutateAsync(form)
      .then(() => {
        toast.success(form.id ? "Source saved." : "Source added.");
        setForm(null);
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(errorText(e));
      });
  };

  return (
    <div>
      <PageTitle title="Job sources" description="Where listings come from. Only a summary and a link are ever published, never the page itself.">
        <Button onClick={() => open()}>Add source</Button>
      </PageTitle>

      <Card>
        <CardContent className="p-0">
          {error && <ErrorState className="m-4" error={error} onRetry={() => refetch()} title="Sources could not be loaded" />}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Source</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Every</TableHead>
                <TableHead>Last run</TableHead>
                <TableHead>Found / new</TableHead>
                <TableHead>Last 7 days</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableMessage colSpan={7}><LoadingRows className="p-4" /></TableMessage>}
              {data?.data.length === 0 && (
                <TableMessage colSpan={7}>
                  <EmptyState icon={Rss} title="No sources yet" action={<Button size="sm" onClick={() => open()}>Add source</Button>} compact>
                    Add an RSS feed, a job site's results page, or a Telegram channel the bot sits in. Test it before saving.
                  </EmptyState>
                </TableMessage>
              )}
              {data?.data.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="max-w-xs">
                    <div className="font-medium text-default-900">{s.name}</div>
                    <div className="text-xs text-default-500 truncate">{s.kind.toUpperCase()} · {String(s.config?.url ?? s.config?.chat_id ?? (s.built_in ? "moderator forwards" : ""))}</div>
                    {s.last_error && <div className="text-xs text-destructive mt-1 line-clamp-2">{s.pause_reason ?? s.last_error}</div>}
                  </TableCell>
                  <TableCell><Badge color={STATUS_COLOR[s.status]} variant="soft" className="capitalize">{s.status}</Badge></TableCell>
                  <TableCell className="text-sm">{s.kind === "telegram" ? "pushed" : `${s.interval_minutes} min`}</TableCell>
                  <TableCell className="text-sm text-default-600 whitespace-nowrap">{formatDateTime(s.last_run_at)}</TableCell>
                  <TableCell className="text-sm">{s.last_found} / {s.last_new}</TableCell>
                  <TableCell className="text-xs text-default-600">
                    {Object.entries(s.last_7_days ?? {}).map(([status, n]) => <div key={status}>{status}: {n}</div>)}
                  </TableCell>
                  <TableCell className="text-right whitespace-nowrap space-x-2">
                    {s.kind !== "telegram" && (
                      <Button size="sm" variant="outline" color="secondary" disabled={run.isPending} onClick={() => run.mutateAsync(s.id).then((r) => (r.result.error ? toast.error(`The source answered with an error: ${r.result.error}`) : toast.success(`Found ${r.result.found}, ${r.result.new} new.`))).catch((e) => toast.error(errorText(e)))}>
                        {run.isPending && run.variables === s.id ? "Running…" : "Run now"}
                      </Button>
                    )}
                    {!s.built_in && <Button size="sm" variant="outline" color="secondary" onClick={() => open(s)}>{s.status === "paused" ? "Fix and resume" : "Edit"}</Button>}
                    {!s.built_in && (
                      <AlertDialog>
                        <AlertDialogTrigger asChild><Button size="sm" variant="outline" color="destructive">Delete</Button></AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Delete {s.name}?</AlertDialogTitle>
                            <AlertDialogDescription>Jobs already published from it stay. Nothing new is fetched.</AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction color="destructive" onClick={() => runAction(remove.mutateAsync(s.id), "Deleted.")}>Delete</AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={form !== null} onOpenChange={(o) => !o && setForm(null)}>
        <DialogContent size="2xl" className="max-h-[90vh] overflow-y-auto">
          {form && (
            <>
              <DialogHeader>
                <DialogTitle>{form.id ? `Edit ${form.name}` : "Add a job source"}</DialogTitle>
                <DialogDescription>Test before saving: the preview shows what this source would find right now.</DialogDescription>
              </DialogHeader>

              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <Label className="mb-1.5 block">Name</Label>
                  <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Bayt — Dubai drivers" />
                  {errors.name && <p className="text-xs text-destructive mt-1">{errors.name[0]}</p>}
                </div>
                <div>
                  <Label className="mb-1.5 block">Kind</Label>
                  <Select value={form.kind} onValueChange={(v) => { setTest(null); setForm({ ...form, kind: v as SourceKind, config: {} }); }} disabled={Boolean(form.id)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="rss">RSS or Atom feed</SelectItem>
                      <SelectItem value="html">Web page (CSS selectors)</SelectItem>
                      <SelectItem value="telegram">Telegram channel</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {form.kind !== "telegram" && (
                  <div>
                    <Label className="mb-1.5 block">Fetch every (minutes)</Label>
                    <Input type="number" min={15} max={1440} value={form.interval_minutes} onChange={(e) => setForm({ ...form, interval_minutes: Number(e.target.value) })} />
                  </div>
                )}
                <div className="flex items-end gap-2 pb-2">
                  <Switch checked={form.active} onCheckedChange={(v) => setForm({ ...form, active: v })} />
                  <span className="text-sm">Active</span>
                </div>

                {FIELDS[form.kind].map((f) => (
                  <div key={f.key} className={f.key === "url" ? "md:col-span-2" : ""}>
                    <Label className="mb-1.5 block">{f.label}{f.required && " *"}</Label>
                    <Input value={form.config[f.key] ?? ""} placeholder={f.placeholder} onChange={(e) => setForm({ ...form, config: { ...form.config, [f.key]: e.target.value } })} />
                    <p className="text-xs text-default-500 mt-1">{f.hint}</p>
                    {errors[`config.${f.key}`] && <p className="text-xs text-destructive mt-1">{errors[`config.${f.key}`][0]}</p>}
                  </div>
                ))}
              </div>

              {test && (
                <div className="rounded-md border border-default-200 p-3 text-sm">
                  {test.ok ? (
                    <>
                      <div className="font-medium text-success mb-2">Found {test.found} listing{test.found === 1 ? "" : "s"}. The first {test.listings.length}:</div>
                      <ul className="space-y-2 max-h-64 overflow-y-auto">
                        {test.listings.map((l) => (
                          <li key={l.external_key}>
                            <div className="font-medium text-default-900">{l.title ?? "(no title)"}</div>
                            <div className="text-xs text-default-500">{[l.company, l.location].filter(Boolean).join(" · ") || "no company or location"} · {l.url ?? "no link"}</div>
                          </li>
                        ))}
                      </ul>
                    </>
                  ) : (
                    <div className="text-destructive">{test.error}</div>
                  )}
                </div>
              )}

              <DialogFooter className="gap-2">
                {form.kind !== "telegram" && <Button variant="outline" disabled={testing} onClick={runTest}>{testing ? "Testing…" : "Test"}</Button>}
                <Button disabled={save.isPending} onClick={submit}>{form.id ? "Save" : "Add source"}</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

function cleanConfig(config: Record<string, string>): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(config)) {
    const t = v.trim();
    if (t === "") continue;
    out[k] = k === "limit" ? Number(t) : t;
  }
  return out;
}

export default SourcesPage;
