"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import { useApiQuery, useApiMutation, formatDateTime, ApiError, type AnnouncementRow, type AnnouncementTopicRow } from "@/lib/api";

type Payload = { data: AnnouncementRow[]; counts: Record<string, number>; topics: AnnouncementTopicRow[] };
type TopicForm = Omit<AnnouncementTopicRow, "id" | "key"> & { id?: number };

const STATUS_COLOR = { pending: "warning", published: "success", rejected: "destructive", deleted: "secondary" } as const;

const AnnouncementsPage = () => {
  const [status, setStatus] = useState("pending");
  const { data, isLoading, error } = useApiQuery<Payload>(["announcements", status], `/admin/announcements?status=${status}`, { refetchInterval: 30_000 });
  const [compose, setCompose] = useState<{ topic_id: string; text: string; event_date: string; event_place: string } | null>(null);
  const [edit, setEdit] = useState<AnnouncementRow | null>(null);
  const [topic, setTopic] = useState<TopicForm | null>(null);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const inv = [["announcements"], ["dashboard"]];
  const create = useApiMutation<{ subscribers: number; translated_by: string }, NonNullable<typeof compose>>(
    (req, c) => req("/admin/announcements", "POST", { topic_id: Number(c.topic_id), text: c.text, event_date: c.event_date || null, event_place: c.event_place || null }),
    inv
  );
  const save = useApiMutation<unknown, AnnouncementRow>((req, a) => req(`/admin/announcements/${a.id}`, "PUT", { text_am: a.text_am, text_ti: a.text_ti, text_en: a.text_en, text_om: a.text_om || null, event_date: a.event_date, event_place: a.event_place }), inv);
  const act = useApiMutation<{ subscribers?: number }, { id: number; action: "approve" | "reject" | "delete"; note?: string }>(
    (req, { id, action, note }) => (action === "delete" ? req(`/admin/announcements/${id}`, "DELETE") : req(`/admin/announcements/${id}/${action}`, "POST", { note })),
    inv
  );
  const saveTopic = useApiMutation<unknown, TopicForm>((req, t) => (t.id ? req(`/admin/announcement-topics/${t.id}`, "PUT", t) : req("/admin/announcement-topics", "POST", t)), inv);

  const fail = (e: ApiError) => { setErrors(e.errors ?? {}); toast.error(e.message); };
  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;

  return (
    <div>
      <PageTitle title="Announcements" description="Posted in the channel and sent to each topic's subscribers in their language. Deaths and fundraisers wait here for approval.">
        <Button onClick={() => { setErrors({}); setCompose({ topic_id: String(data?.topics[0]?.id ?? ""), text: "", event_date: "", event_place: "" }); }}>New announcement</Button>
      </PageTitle>

      <Tabs defaultValue="list">
        <TabsList>
          <TabsTrigger value="list">Announcements</TabsTrigger>
          <TabsTrigger value="topics">Topics</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="mt-4">
          <div className="flex gap-2 mb-4">
            {(["pending", "published", "rejected", "deleted", "all"] as const).map((s) => (
              <button key={s} type="button" onClick={() => setStatus(s)}
                className={cn("px-3 py-1.5 rounded-md text-sm font-medium border capitalize", status === s ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary")}>
                {s === "pending" ? "Waiting for approval" : s}{data && s !== "all" && <span className="ml-1.5 opacity-75">{data.counts[s] ?? 0}</span>}
              </button>
            ))}
          </div>

          {error && <div className="text-destructive">{error.message}</div>}
          {isLoading && <div className="text-default-500">Loading…</div>}
          {data?.data.length === 0 && <Card><CardContent className="p-6 text-default-500">Nothing here.</CardContent></Card>}

          <div className="space-y-3">
            {data?.data.map((a) => (
              <Card key={a.id}>
                <CardContent className="p-4 space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="font-medium text-default-900">
                      {a.topic?.emoji} {a.topic?.name}
                      <Badge color={STATUS_COLOR[a.status]} variant="soft" className="ml-2 capitalize">{a.status === "pending" ? "waiting" : a.status}</Badge>
                      {a.pinned_until && <Badge color="info" variant="soft" className="ml-1">pinned</Badge>}
                      {a.has_photo && <Badge color="secondary" variant="soft" className="ml-1">photo</Badge>}
                    </div>
                    <div className="text-sm text-default-500">{a.poster?.name ?? "—"} · {formatDateTime(a.published_at ?? a.created_at)}</div>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
                    {(["am", "ti", "en", "om"] as const).map((l) => (
                      <div key={l}>
                        <div className="text-xs text-default-500 mb-1">{l === "am" ? "Amharic" : l === "ti" ? "Tigrinya" : l === "om" ? "Afaan Oromoo" : "English"}{a.original_language === l && " (original)"}</div>
                        <p className="whitespace-pre-wrap text-default-800">{a[`text_${l}`]}</p>
                      </div>
                    ))}
                  </div>
                  {a.event_date && <div className="text-sm">📅 {a.event_date}{a.event_place && ` · 📍 ${a.event_place}`}</div>}
                  {a.status === "published" && <div className="text-xs text-default-500">Sent to {a.dm_count} members{a.on_channel ? " and posted in the channel" : ""}.</div>}
                  {a.review_note && <div className="text-sm text-warning">{a.review_note}</div>}

                  <div className="flex flex-wrap gap-2 items-center">
                    {a.status === "pending" && (
                      <>
                        <Button size="sm" color="success" disabled={act.isPending} onClick={() => act.mutateAsync({ id: a.id, action: "approve" }).then((r) => toast.success(`Posted; ${r.subscribers ?? 0} members will get it.`)).catch((e: Error) => toast.error(e.message))}>Approve and post</Button>
                        <Input className="w-64 h-9" placeholder="Why not (sent to the poster)" value={notes[a.id] ?? ""} onChange={(e) => setNotes({ ...notes, [a.id]: e.target.value })} />
                        <Button size="sm" variant="outline" color="destructive" disabled={act.isPending} onClick={() => act.mutateAsync({ id: a.id, action: "reject", note: notes[a.id] }).then(() => toast.success("Rejected; the poster is told."))}>Reject</Button>
                      </>
                    )}
                    {a.status === "published" && (
                      <>
                        <Button size="sm" variant="outline" onClick={() => { setErrors({}); setEdit({ ...a }); }}>Edit wording</Button>
                        <Button size="sm" variant="outline" color="destructive" disabled={act.isPending} onClick={() => window.confirm("Delete it from the channel?") && act.mutateAsync({ id: a.id, action: "delete" }).then(() => toast.success("Deleted."))}>Delete</Button>
                      </>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="topics" className="mt-4 space-y-3">
          <div className="flex justify-end">
            <Button variant="outline" onClick={() => { setErrors({}); setTopic({ name_en: "", name_am: "", name_ti: "", name_om: "", emoji: "📣", needs_approval: false, default_on: false, pin: false, sort: 100, active: true }); }}>Add topic</Button>
          </div>
          {data?.topics.map((t) => (
            <Card key={t.id} className="cursor-pointer hover:border-primary" onClick={() => { setErrors({}); setTopic({ ...t }); }}>
              <CardContent className="p-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                  <div className="font-medium text-default-900">{t.emoji} {t.name_en} {!t.active && <Badge color="secondary" variant="soft">off</Badge>}</div>
                  <div className="text-sm text-default-600">{t.name_am} · {t.name_ti}</div>
                </div>
                <div className="flex gap-1">
                  {t.needs_approval && <Badge color="warning" variant="soft">needs approval</Badge>}
                  {t.default_on && <Badge color="info" variant="soft">on for new members</Badge>}
                  {t.pin && <Badge color="success" variant="soft">pinned 48 h</Badge>}
                </div>
              </CardContent>
            </Card>
          ))}
        </TabsContent>
      </Tabs>

      <Dialog open={compose !== null} onOpenChange={(o) => !o && setCompose(null)}>
        <DialogContent size="2xl">
          {compose && (
            <>
              <DialogHeader>
                <DialogTitle>New announcement</DialogTitle>
                <DialogDescription>Write it in any one language. With an Anthropic key it is translated into the other two; otherwise the same text goes out in all three, and you can edit the wording after.</DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label className="mb-1.5 block">Topic</Label>
                  <Select value={compose.topic_id} onValueChange={(v) => setCompose({ ...compose, topic_id: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{data?.topics.filter((t) => t.active).map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.emoji} {t.name_en}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="mb-1.5 block">Text</Label>
                  <Textarea rows={6} value={compose.text} onChange={(e) => setCompose({ ...compose, text: e.target.value })} className="text-base" />
                  {err("text")}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label className="mb-1.5 block">Event date (optional)</Label>
                    <Input type="date" value={compose.event_date} onChange={(e) => setCompose({ ...compose, event_date: e.target.value })} />
                    {err("event_date")}
                  </div>
                  <div>
                    <Label className="mb-1.5 block">Place (optional)</Label>
                    <Input value={compose.event_place} onChange={(e) => setCompose({ ...compose, event_place: e.target.value })} />
                  </div>
                </div>
              </div>
              <DialogFooter>
                <Button disabled={create.isPending} onClick={() => create.mutateAsync(compose).then((r) => { toast.success(`Posted${r.translated_by === "claude" ? " in three languages" : ""}; ${r.subscribers} members will get it.`); setCompose(null); }).catch(fail)}>
                  {create.isPending ? "Posting…" : "Post now"}
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={edit !== null} onOpenChange={(o) => !o && setEdit(null)}>
        <DialogContent size="3xl">
          {edit && (
            <>
              <DialogHeader>
                <DialogTitle>Edit wording</DialogTitle>
                <DialogDescription>The channel post is updated; messages already sent to members stay as they were.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 md:grid-cols-2">
                {(["am", "ti", "en", "om"] as const).map((l) => (
                  <div key={l}>
                    <Label className="mb-1.5 block">{l === "am" ? "Amharic" : l === "ti" ? "Tigrinya" : l === "om" ? "Afaan Oromoo" : "English"}</Label>
                    <Textarea rows={8} value={edit[`text_${l}`] ?? ""} onChange={(e) => setEdit({ ...edit, [`text_${l}`]: e.target.value })} className="text-base" />
                    {err(`text_${l}`)}
                  </div>
                ))}
              </div>
              <DialogFooter>
                <Button disabled={save.isPending} onClick={() => save.mutateAsync(edit).then(() => { toast.success("Saved; the channel post is updated."); setEdit(null); }).catch(fail)}>Save</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={topic !== null} onOpenChange={(o) => !o && setTopic(null)}>
        <DialogContent size="lg">
          {topic && (
            <>
              <DialogHeader>
                <DialogTitle>{topic.id ? `Edit ${topic.name_en}` : "Add topic"}</DialogTitle>
                <DialogDescription>Members turn topics on and off with /topics. Leaders post only in topics granted to them on their member page.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="md:col-span-2 grid grid-cols-[5rem_1fr] gap-2">
                  <div>
                    <Label className="mb-1.5 block">Icon</Label>
                    <Input value={topic.emoji} onChange={(e) => setTopic({ ...topic, emoji: e.target.value })} />
                  </div>
                  <div>
                    <Label className="mb-1.5 block">English name *</Label>
                    <Input value={topic.name_en} onChange={(e) => setTopic({ ...topic, name_en: e.target.value })} />
                    {err("name_en")}
                  </div>
                </div>
                <div>
                  <Label className="mb-1.5 block">Amharic *</Label>
                  <Input value={topic.name_am} onChange={(e) => setTopic({ ...topic, name_am: e.target.value })} />
                  {err("name_am")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Tigrinya *</Label>
                  <Input value={topic.name_ti} onChange={(e) => setTopic({ ...topic, name_ti: e.target.value })} />
                  {err("name_ti")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Afaan Oromoo</Label>
                  <Input value={topic.name_om ?? ""} onChange={(e) => setTopic({ ...topic, name_om: e.target.value })} placeholder="English when empty" />
                  {err("name_om")}
                </div>
                <label className="flex items-center gap-2 text-sm"><Switch checked={topic.needs_approval} onCheckedChange={(v) => setTopic({ ...topic, needs_approval: v })} /> A moderator approves posts</label>
                <label className="flex items-center gap-2 text-sm"><Switch checked={topic.default_on} onCheckedChange={(v) => setTopic({ ...topic, default_on: v })} /> On for members by default</label>
                <label className="flex items-center gap-2 text-sm"><Switch checked={topic.pin} onCheckedChange={(v) => setTopic({ ...topic, pin: v })} /> Pin in the channel for 48 hours</label>
                <label className="flex items-center gap-2 text-sm"><Switch checked={topic.active} onCheckedChange={(v) => setTopic({ ...topic, active: v })} /> Active</label>
              </div>
              <DialogFooter>
                <Button disabled={saveTopic.isPending} onClick={() => saveTopic.mutateAsync(topic).then(() => { toast.success("Topic saved."); setTopic(null); }).catch(fail)}>Save</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default AnnouncementsPage;
