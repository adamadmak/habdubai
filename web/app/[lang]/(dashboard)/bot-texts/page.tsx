"use client";
import { useMemo, useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import { useApiQuery, useApiMutation, type BotTextRow, LANGUAGE_NAMES } from "@/lib/api";

const BotTextsPage = () => {
  const { data, isLoading, error } = useApiQuery<{ data: BotTextRow[]; languages: string[] }>(["bot-texts"], "/admin/bot-texts");
  const [selected, setSelected] = useState<string | null>(null);
  const [filter, setFilter] = useState("");

  const rows = useMemo(() => (data?.data ?? []).filter((r) => r.key.includes(filter.toLowerCase())), [data, filter]);
  const current = data?.data.find((r) => r.key === (selected ?? data?.data[0]?.key));

  if (error) return <div className="text-destructive">{error.message}</div>;

  return (
    <div>
      <PageTitle title="Bot texts" description="Every message the bot sends, in Amharic, Tigrinya and English. An edit here changes the bot at once." />

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-1">
          <CardHeader className="pb-2">
            <Input value={filter} onChange={(e) => setFilter(e.target.value)} placeholder="Filter by key" />
          </CardHeader>
          <CardContent className="p-0 max-h-[70vh] overflow-y-auto">
            {isLoading && <div className="p-4 text-default-500">Loading…</div>}
            <ul>
              {rows.map((row) => {
                const edited = Object.keys(row.override).length > 0;
                return (
                  <li key={row.key}>
                    <button
                      type="button"
                      onClick={() => setSelected(row.key)}
                      className={cn("w-full text-left px-4 py-2.5 flex items-center justify-between gap-2 hover:bg-default-50 border-l-2", current?.key === row.key ? "border-primary bg-default-50" : "border-transparent")}
                    >
                      <span className="font-mono text-sm text-default-800">{row.key}</span>
                      <span className="flex gap-1">
                        {row.is_button && <Badge color="secondary" variant="soft">button</Badge>}
                        {edited && <Badge color="info" variant="soft">edited</Badge>}
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          {current ? <Editor key={current.key} row={current} languages={data?.languages ?? ["am", "ti", "en", "om"]} /> : <CardContent className="p-6 text-default-500">Pick a text on the left.</CardContent>}
        </Card>
      </div>
    </div>
  );
};

const Editor = ({ row, languages }: { row: BotTextRow; languages: string[] }) => {
  const [drafts, setDrafts] = useState<Record<string, string>>(() =>
    Object.fromEntries(languages.map((l) => [l, row.override[l] ?? row.default[l] ?? ""]))
  );

  const save = useApiMutation<{ ok: boolean; override: string | null }, { language: string; text: string }>(
    (request, body) => request(`/admin/bot-texts/${row.key}`, "PUT", body),
    [["bot-texts"]]
  );

  const submit = (language: string, text: string) =>
    save
      .mutateAsync({ language, text })
      .then((result) => toast.success(result.override === null ? `${LANGUAGE_NAMES[language]} back to the default.` : `${LANGUAGE_NAMES[language]} saved.`))
      .catch((e: Error) => toast.error(e.message));

  return (
    <>
      <CardHeader>
        <CardTitle className="text-base font-mono">{row.key}</CardTitle>
        {row.placeholders.length > 0 && (
          <p className="text-sm text-default-600">
            Keep these placeholders: {row.placeholders.map((p) => <code key={p} className="mx-1 px-1 rounded bg-default-100">{`{${p}}`}</code>)}
          </p>
        )}
      </CardHeader>
      <CardContent>
        <Tabs defaultValue={languages[0]}>
          <TabsList>
            {languages.map((l) => (
              <TabsTrigger key={l} value={l}>
                {LANGUAGE_NAMES[l] ?? l}{row.override[l] !== undefined && " •"}
              </TabsTrigger>
            ))}
          </TabsList>
          {languages.map((l) => (
            <TabsContent key={l} value={l} className="mt-4 space-y-3">
              <Textarea rows={row.is_button ? 2 : 8} value={drafts[l]} onChange={(e) => setDrafts({ ...drafts, [l]: e.target.value })} className="font-normal text-base leading-7" />
              <div className="flex flex-wrap gap-2 items-center">
                <Button size="sm" disabled={save.isPending || drafts[l] === (row.override[l] ?? row.default[l])} onClick={() => submit(l, drafts[l])}>Save</Button>
                <Button size="sm" variant="outline" disabled={save.isPending || row.override[l] === undefined} onClick={() => { setDrafts({ ...drafts, [l]: row.default[l] }); submit(l, ""); }}>Reset to default</Button>
                {row.override[l] !== undefined && <span className="text-xs text-default-500">Edited; the default is kept underneath.</span>}
              </div>
              {row.override[l] !== undefined && (
                <details className="text-sm text-default-600">
                  <summary className="cursor-pointer">Default text</summary>
                  <pre className="mt-2 whitespace-pre-wrap font-sans p-3 rounded bg-default-50">{row.default[l]}</pre>
                </details>
              )}
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </>
  );
};

export default BotTextsPage;
