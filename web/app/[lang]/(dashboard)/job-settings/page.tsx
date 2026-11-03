"use client";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import PageTitle from "@/components/page-title";
import { useApiQuery, useApiMutation, type JobSettingsPayload, ApiError } from "@/lib/api";

type Form = JobSettingsPayload["settings"] & { anthropic_api_key: string };

const LANGS = [
  ["am", "Amharic"],
  ["ti", "Tigrinya"],
  ["en", "English"],
] as const;

const JobSettingsPage = () => {
  const { data, isLoading, error } = useApiQuery<JobSettingsPayload>(["job-settings"], "/admin/job-settings");
  const [form, setForm] = useState<Form | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (data) setForm({ ...data.settings, anthropic_api_key: "" });
  }, [data]);

  const save = useApiMutation<JobSettingsPayload, Form>((request, body) => request("/admin/job-settings", "PUT", body), [["job-settings"], ["dashboard"]]);

  if (error) return <div className="text-destructive">{error.message}</div>;
  if (isLoading || !data || !form) return <div className="text-default-600">Loading…</div>;

  const languages = form.job_card_languages.split(",").filter(Boolean);
  const toggleLanguage = (code: string) => {
    const next = languages.includes(code) ? languages.filter((l) => l !== code) : [...languages, code];
    setForm({ ...form, job_card_languages: (next.length ? next : [code]).join(",") });
  };

  const submit = () => {
    setErrors({});
    save
      .mutateAsync(form)
      .then(() => {
        setForm({ ...form, anthropic_api_key: "" });
        toast.success("Job settings saved.");
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(e.message);
      });
  };

  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;

  return (
    <div>
      <PageTitle title="Job settings" description="How listings are classified, scored and published." />

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Publishing</CardTitle>
          </CardHeader>
          <CardContent className="space-y-5">
            <div>
              <Label className="mb-1.5 block">Jobs channel</Label>
              <Input value={form.job_channel_id} onChange={(e) => setForm({ ...form, job_channel_id: e.target.value })} placeholder="@habesha_dubai_jobs or -100…" />
              <p className="text-xs text-default-500 mt-1">The bot must be an admin of the channel. Empty: jobs are published to members' alerts only.</p>
              {err("job_channel_id")}
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Publish clean jobs automatically</Label>
                <p className="text-xs text-default-500">Off: every job waits in the review queue.</p>
              </div>
              <Switch checked={form.job_auto_publish} onCheckedChange={(v) => setForm({ ...form, job_auto_publish: v })} />
            </div>
            <div>
              <Label className="mb-2 block">Summaries on the channel card</Label>
              <div className="flex gap-4">
                {LANGS.map(([code, name]) => (
                  <label key={code} className="flex items-center gap-2 text-sm">
                    <Switch checked={languages.includes(code)} onCheckedChange={() => toggleLanguage(code)} />
                    {name}
                  </label>
                ))}
              </div>
              <p className="text-xs text-default-500 mt-1">The first one also sets the card's labels. Alerts always use each member's own language.</p>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label className="mb-1.5 block">Review at scam score</Label>
                <Input type="number" min={1} max={100} value={form.job_review_threshold} onChange={(e) => setForm({ ...form, job_review_threshold: Number(e.target.value) })} />
                {err("job_review_threshold")}
              </div>
              <div>
                <Label className="mb-1.5 block">Warn on card at</Label>
                <Input type="number" min={1} max={100} value={form.job_warning_threshold} onChange={(e) => setForm({ ...form, job_warning_threshold: Number(e.target.value) })} />
                {err("job_warning_threshold")}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center justify-between">
              Classifier
              {data.anthropic.configured ? <Badge color="success" variant="soft">Claude · key {data.anthropic.hint}</Badge> : <Badge color="warning" variant="soft">Keyword rules only</Badge>}
            </CardTitle>
            <CardDescription>
              With an Anthropic key, each new listing gets one Claude call: category, place, pay, scam signals, and summaries in Amharic, Tigrinya and English. Without one, keyword rules classify and the card shows English only.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            <div>
              <Label className="mb-1.5 block">Anthropic API key</Label>
              <Input type="password" autoComplete="off" value={form.anthropic_api_key} onChange={(e) => setForm({ ...form, anthropic_api_key: e.target.value })} placeholder={data.anthropic.configured ? "Leave blank to keep the current key" : "sk-ant-…"} />
              <p className="text-xs text-default-500 mt-1">Stored encrypted and never shown again. Blank keeps the current key.</p>
              {err("anthropic_api_key")}
            </div>
            <div>
              <Label className="mb-1.5 block">Model</Label>
              <Input value={form.anthropic_model} onChange={(e) => setForm({ ...form, anthropic_model: e.target.value })} />
              <p className="text-xs text-default-500 mt-1">claude-opus-5-5 by default. A cheaper model lowers the cost per listing; check the Amharic and Tigrinya summaries if you change it.</p>
              {err("anthropic_model")}
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Scam lists</CardTitle>
            <CardDescription>One per line. A listing that contains a phrase, or comes from a blocked company, always goes to review.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2">
            <div>
              <Label className="mb-1.5 block">Phrases</Label>
              <Textarea rows={8} value={form.job_scam_phrases} onChange={(e) => setForm({ ...form, job_scam_phrases: e.target.value })} placeholder={"pay for your visa\nsend the fee first"} />
            </div>
            <div>
              <Label className="mb-1.5 block">Blocked companies</Label>
              <Textarea rows={8} value={form.job_blocked_companies} onChange={(e) => setForm({ ...form, job_blocked_companies: e.target.value })} placeholder="Rejecting a job with Block company adds it here." />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="mt-4">
        <Button disabled={save.isPending} onClick={submit}>Save job settings</Button>
      </div>
    </div>
  );
};

export default JobSettingsPage;
