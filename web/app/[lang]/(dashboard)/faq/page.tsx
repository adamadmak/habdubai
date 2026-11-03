"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
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
import ConfirmButton from "@/components/confirm-button";
import { EmptyState, ErrorState, LoadingRows } from "@/components/states";
import { toastResult } from "@/lib/toast";
import { BookOpen, FileText } from "lucide-react";
import { useApiQuery, useApiMutation, ApiError, errorText, type FaqRow, type KnowledgeRow } from "@/lib/api";

type Payload = { faq: FaqRow[]; knowledge: KnowledgeRow[]; categories: Record<string, string> };
type FaqForm = Omit<FaqRow, "id" | "question_id"> & { id?: number };
type ArticleForm = Omit<KnowledgeRow, "id"> & { id?: number };

const EMPTY_FAQ: FaqForm = { category: "visa", q_am: "", q_ti: "", q_en: "", q_om: "", a_am: "", a_ti: "", a_en: "", a_om: "", source_url: "", active: true };
const EMPTY_ARTICLE: ArticleForm = { title: "", body: "", source_name: "", source_url: "", active: true };

const FaqPage = () => {
  const { data, isLoading, error, refetch } = useApiQuery<Payload>(["faq"], "/admin/faq");
  const [faq, setFaq] = useState<FaqForm | null>(null);
  const [article, setArticle] = useState<ArticleForm | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const saveFaq = useApiMutation<unknown, FaqForm>((req, f) => (f.id ? req(`/admin/faq/${f.id}`, "PUT", f) : req("/admin/faq", "POST", f)), [["faq"]]);
  const removeFaq = useApiMutation<unknown, number>((req, id) => req(`/admin/faq/${id}`, "DELETE"), [["faq"]]);
  const saveArticle = useApiMutation<unknown, ArticleForm>((req, a) => (a.id ? req(`/admin/knowledge/${a.id}`, "PUT", a) : req("/admin/knowledge", "POST", a)), [["faq"]]);
  const removeArticle = useApiMutation<unknown, number>((req, id) => req(`/admin/knowledge/${id}`, "DELETE"), [["faq"]]);

  const clean = <T extends Record<string, unknown>>(o: T): T =>
    Object.fromEntries(Object.entries(o).map(([k, v]) => [k, typeof v === "string" && v.trim() === "" ? null : v])) as T;

  const submit = <T,>(m: { mutateAsync: (v: T) => Promise<unknown> }, value: T, done: string, close: () => void) => {
    setErrors({});
    m.mutateAsync(value)
      .then(() => { toast.success(done); close(); })
      .catch((e: ApiError) => { setErrors(e.errors ?? {}); toast.error(errorText(e)); });
  };

  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;

  return (
    <div>
      <PageTitle title="FAQ and knowledge" description="What the bot's assistant answers from. It says it does not know rather than go beyond what is here, so check every word: a wrong fee or deadline can cost a member their visa." />
      {error && <ErrorState className="mb-4" error={error} onRetry={() => refetch()} title="The FAQ could not be loaded" />}

      <Tabs defaultValue="faq">
        <TabsList>
          <TabsTrigger value="faq">FAQ ({data?.faq.length ?? 0})</TabsTrigger>
          <TabsTrigger value="knowledge">Knowledge base ({data?.knowledge.length ?? 0})</TabsTrigger>
        </TabsList>

        <TabsContent value="faq" className="mt-4 space-y-3">
          <div className="flex justify-end"><Button onClick={() => { setErrors({}); setFaq({ ...EMPTY_FAQ }); }}>Add FAQ entry</Button></div>
          {isLoading && <LoadingRows />}
          {data?.faq.length === 0 && (
            <Card>
              <EmptyState icon={BookOpen} title="No FAQ entries yet">
                Write one here, or open a solved question on the Questions page and press “Add to the FAQ”: it is written in all four languages for you.
              </EmptyState>
            </Card>
          )}
          {data?.faq.map((f) => (
            <Card key={f.id} role="button" tabIndex={0} aria-label={`Edit: ${f.q_en}`} onKeyDown={(e) => { if (e.key === "Enter") (e.currentTarget as HTMLElement).click(); }} className="cursor-pointer transition-colors hover:border-primary/50" onClick={() => { setErrors({}); setFaq({ ...f, q_am: f.q_am ?? "", q_ti: f.q_ti ?? "", a_am: f.a_am ?? "", a_ti: f.a_ti ?? "", q_om: f.q_om ?? "", a_om: f.a_om ?? "", source_url: f.source_url ?? "" }); }}>
              <CardContent className="p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <div className="font-medium text-default-900">{f.q_en}</div>
                    {f.q_am && <div className="text-sm text-default-600">{f.q_am}</div>}
                    <div className="text-sm text-default-700 mt-1 line-clamp-2">{f.a_en}</div>
                  </div>
                  <div className="flex flex-col items-end gap-1 shrink-0">
                    <Badge color={f.active ? "success" : "warning"} variant="soft">{f.active ? "live" : "draft"}</Badge>
                    <span className="text-xs text-default-500">{data.categories[f.category] ?? f.category}</span>
                    {(() => {
                      const missing = (["am", "ti", "om"] as const).filter((l) => !f[`q_${l}`] || !f[`a_${l}`]).map((l) => ({ am: "Amharic", ti: "Tigrinya", om: "Oromo" })[l]);
                      return missing.length > 0 && <span className="text-xs text-warning">No {missing.join(", ")}</span>;
                    })()}
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </TabsContent>

        <TabsContent value="knowledge" className="mt-4 space-y-3">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">What belongs here</CardTitle>
              <CardDescription>
                Published rules you have checked yourself, in plain English: who pays for a visa, what to do about unpaid salary, overstay fines, how to reach the consulate. Give each one its source. The assistant answers in Amharic, Tigrinya, English or Afaan Oromoo from these.
              </CardDescription>
            </CardHeader>
          </Card>
          <div className="flex justify-end"><Button onClick={() => { setErrors({}); setArticle({ ...EMPTY_ARTICLE }); }}>Add article</Button></div>
          {data?.knowledge.length === 0 && (
            <Card>
              <EmptyState icon={FileText} title="No articles yet">
                Until there are some, the assistant can only answer from the FAQ. Start with the questions members ask most: visas, unpaid salary, overstay fines.
              </EmptyState>
            </Card>
          )}
          {data?.knowledge.map((a) => (
            <Card key={a.id} role="button" tabIndex={0} aria-label={`Edit: ${a.title}`} onKeyDown={(e) => { if (e.key === "Enter") (e.currentTarget as HTMLElement).click(); }} className="cursor-pointer transition-colors hover:border-primary/50" onClick={() => { setErrors({}); setArticle({ ...a, source_name: a.source_name ?? "", source_url: a.source_url ?? "" }); }}>
              <CardContent className="p-4 flex items-start justify-between gap-3">
                <div>
                  <div className="font-medium text-default-900">{a.title}</div>
                  <div className="text-sm text-default-700 mt-1 line-clamp-2">{a.body}</div>
                  {a.source_name && <div className="text-xs text-default-500 mt-1">Source: {a.source_name}</div>}
                </div>
                <Badge color={a.active ? "success" : "secondary"} variant="soft">{a.active ? "used" : "off"}</Badge>
              </CardContent>
            </Card>
          ))}
        </TabsContent>
      </Tabs>

      <Dialog open={faq !== null} onOpenChange={(o) => !o && setFaq(null)}>
        <DialogContent size="3xl" className="max-h-[90vh] overflow-y-auto">
          {faq && (
            <>
              <DialogHeader>
                <DialogTitle>{faq.id ? "Edit FAQ entry" : "Add FAQ entry"}</DialogTitle>
                <DialogDescription>English is required; Amharic, Tigrinya and Afaan Oromoo fall back to English when empty.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <Label className="mb-1.5 block">Topic</Label>
                  <Select value={faq.category} onValueChange={(v) => setFaq({ ...faq, category: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{Object.entries(data?.categories ?? {}).map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="mb-1.5 block">Source link</Label>
                  <Input value={faq.source_url ?? ""} onChange={(e) => setFaq({ ...faq, source_url: e.target.value })} placeholder="https://…" />
                  {err("source_url")}
                </div>
                {([["en", "English"], ["am", "Amharic"], ["ti", "Tigrinya"], ["om", "Afaan Oromoo"]] as const).map(([l, name]) => (
                  <div key={l} className="md:col-span-2 grid gap-2 md:grid-cols-2">
                    <div>
                      <Label className="mb-1.5 block">Question, {name}{l === "en" && " *"}</Label>
                      <Textarea rows={2} value={(faq as any)[`q_${l}`] ?? ""} onChange={(e) => setFaq({ ...faq, [`q_${l}`]: e.target.value })} className="text-base" />
                      {err(`q_${l}`)}
                    </div>
                    <div>
                      <Label className="mb-1.5 block">Answer, {name}{l === "en" && " *"}</Label>
                      <Textarea rows={3} value={(faq as any)[`a_${l}`] ?? ""} onChange={(e) => setFaq({ ...faq, [`a_${l}`]: e.target.value })} className="text-base" />
                      {err(`a_${l}`)}
                    </div>
                  </div>
                ))}
                <label className="flex items-center gap-2 text-sm"><Switch checked={faq.active} onCheckedChange={(v) => setFaq({ ...faq, active: v })} /> Live in /faq and for the assistant</label>
              </div>
              <DialogFooter className="gap-2">
                {faq.id && (
                  <ConfirmButton variant="outline" color="destructive" label="Delete" title="Delete this FAQ entry?"
                    description="The assistant stops using it at once. A question it came from goes back to being only solved."
                    confirmLabel="Delete" onConfirm={() => toastResult(removeFaq.mutateAsync(faq.id!), "Deleted.").then(() => setFaq(null))} />
                )}
                <Button disabled={saveFaq.isPending} onClick={() => submit(saveFaq, clean(faq), "Saved.", () => setFaq(null))}>Save</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={article !== null} onOpenChange={(o) => !o && setArticle(null)}>
        <DialogContent size="2xl" className="max-h-[90vh] overflow-y-auto">
          {article && (
            <>
              <DialogHeader>
                <DialogTitle>{article.id ? "Edit article" : "Add article"}</DialogTitle>
                <DialogDescription>Plain English. Only what the source says.</DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label className="mb-1.5 block">Title *</Label>
                  <Input value={article.title} onChange={(e) => setArticle({ ...article, title: e.target.value })} placeholder="Who pays for a work visa renewal" />
                  {err("title")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Text *</Label>
                  <Textarea rows={10} value={article.body} onChange={(e) => setArticle({ ...article, body: e.target.value })} />
                  {err("body")}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label className="mb-1.5 block">Source name</Label>
                    <Input value={article.source_name ?? ""} onChange={(e) => setArticle({ ...article, source_name: e.target.value })} placeholder="MoHRE" />
                  </div>
                  <div>
                    <Label className="mb-1.5 block">Source link</Label>
                    <Input value={article.source_url ?? ""} onChange={(e) => setArticle({ ...article, source_url: e.target.value })} placeholder="https://www.mohre.gov.ae/…" />
                    {err("source_url")}
                  </div>
                </div>
                <label className="flex items-center gap-2 text-sm"><Switch checked={article.active} onCheckedChange={(v) => setArticle({ ...article, active: v })} /> The assistant uses this</label>
              </div>
              <DialogFooter className="gap-2">
                {article.id && (
                  <ConfirmButton variant="outline" color="destructive" label="Delete" title="Delete this article?"
                    description="The assistant stops answering from it at once. To keep it for later, turn it off instead."
                    confirmLabel="Delete" onConfirm={() => toastResult(removeArticle.mutateAsync(article.id!), "Deleted.").then(() => setArticle(null))} />
                )}
                <Button disabled={saveArticle.isPending} onClick={() => submit(saveArticle, clean(article), "Saved.", () => setArticle(null))}>Save</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default FaqPage;
