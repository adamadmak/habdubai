"use client";
import { useEffect, useState } from "react";
import { runAction } from "@/lib/toast";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { useApiQuery, useApiMutation, type AnnouncementTopicRow } from "@/lib/api";

/** Which announcement topics a leader may post in. */
const LeaderTopics = ({ memberId }: { memberId: number }) => {
  const topics = useApiQuery<{ topics: AnnouncementTopicRow[] }>(["announcements", "topics-only"], "/admin/announcements?status=all");
  const granted = useApiQuery<{ topic_ids: number[] }>(["leader-topics", memberId], `/admin/members/${memberId}/leader-topics`);
  const [picked, setPicked] = useState<number[]>([]);

  useEffect(() => {
    if (granted.data) setPicked(granted.data.topic_ids);
  }, [granted.data]);

  const save = useApiMutation<unknown, number[]>((req, ids) => req(`/admin/members/${memberId}/leader-topics`, "PUT", { topic_ids: ids }), [["leader-topics", memberId]]);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Topics this leader may post in</CardTitle>
        <CardDescription>A church organiser gets Church and Community events, for example. Deaths and fundraisers still wait for a moderator.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-2">
        {topics.isLoading && <div className="text-sm text-default-500">Loading topics…</div>}
        {topics.data && topics.data.topics.filter((t) => t.active).length === 0 && <div className="text-sm text-default-500">No active topics yet. Add them on the Announcements page, under Topics.</div>}
        {topics.data?.topics.filter((t) => t.active).map((t) => (
          <label key={t.id} className="flex items-center justify-between gap-2 text-sm">
            <span>{t.emoji} {t.name_en}</span>
            <Switch checked={picked.includes(t.id)} onCheckedChange={(on) => setPicked(on ? [...picked, t.id] : picked.filter((id) => id !== t.id))} />
          </label>
        ))}
        <Button size="sm" className="w-full mt-2" disabled={save.isPending} onClick={() => runAction(save.mutateAsync(picked), "Topics saved.")}>Save topics</Button>
      </CardContent>
    </Card>
  );
};

export default LeaderTopics;
