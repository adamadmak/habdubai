"use client";
import { useState } from "react";
import Link from "next/link";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { useApiQuery, useApiMutation, formatDateTime } from "@/lib/api";

type QueueItem = {
  chat_id: number;
  message_id: number;
  group: string;
  excerpt: string;
  reports: number;
  reporters: string[];
  hidden: boolean;
  reported: { id: number; name: string; role: string; muted_until: string | null; banned: boolean; strikes_90d: number } | null;
  first_reported_at: string;
};

type Strike = { id: number; member: { id: number; name: string } | null; kind: string; reason: string; until: string | null; by_admin: boolean; created_at: string };

type Action = "dismiss" | "delete" | "warn" | "mute1" | "mute7" | "ban";

const ACTIONS: { action: Action; label: string; color?: "destructive" | "warning" | "success" }[] = [
  { action: "dismiss", label: "Keep it" },
  { action: "delete", label: "Delete message" },
  { action: "warn", label: "Delete and warn", color: "warning" },
  { action: "mute1", label: "Mute 1 day", color: "warning" },
  { action: "mute7", label: "Mute 7 days", color: "warning" },
  { action: "ban", label: "Ban", color: "destructive" },
];

const KIND_COLOR: Record<string, "secondary" | "warning" | "destructive"> = { removed: "secondary", warning: "warning", mute: "destructive" };

const ModerationPage = () => {
  const { data, isLoading, error } = useApiQuery<{ queue: QueueItem[]; strikes: Strike[] }>(["moderation"], "/admin/moderation", { refetchInterval: 20_000 });
  const [reasons, setReasons] = useState<Record<string, string>>({});

  const resolve = useApiMutation<unknown, { item: QueueItem; action: Action }>(
    (req, { item, action }) => req("/admin/moderation/resolve", "POST", { chat_id: item.chat_id, message_id: item.message_id, action, reason: reasons[`${item.chat_id}:${item.message_id}`] || null }),
    [["moderation"], ["dashboard"], ["members"]]
  );

  const run = (item: QueueItem, action: Action) => {
    if (action === "ban" && !window.confirm(`Ban ${item.reported?.name ?? "this member"}? They are removed from both groups and the bot stops serving them.`)) return;
    resolve.mutateAsync({ item, action }).then(() => toast.success("Done; the reports are closed.")).catch((e: Error) => toast.error(e.message));
  };

  return (
    <div>
      <PageTitle title="Moderation" description="Messages members reported in the groups with /report. Three reports take a message down until you decide. Automatic removals, warnings and mutes are listed below." />

      {error && <div className="text-destructive">{error.message}</div>}
      {isLoading && <div className="text-default-500">Loading…</div>}
      {data?.queue.length === 0 && <Card><CardContent className="p-6 text-default-500">Nothing reported. The groups are quiet.</CardContent></Card>}

      <div className="space-y-3">
        {data?.queue.map((item) => {
          const key = `${item.chat_id}:${item.message_id}`;
          return (
            <Card key={key}>
              <CardContent className="p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="text-sm">
                    <Badge color="destructive" variant="soft">{item.reports} report{item.reports === 1 ? "" : "s"}</Badge>
                    {item.hidden && <Badge color="warning" variant="soft" className="ml-1">taken down</Badge>}
                    <span className="ml-2 text-default-600">{item.group} · {formatDateTime(item.first_reported_at)}</span>
                  </div>
                  {item.reported && (
                    <div className="text-sm">
                      <Link className="text-primary font-medium" href={`/members/${item.reported.id}`}>{item.reported.name}</Link>
                      <span className="text-default-500"> · {item.reported.strikes_90d} strike{item.reported.strikes_90d === 1 ? "" : "s"} in 90 days</span>
                      {item.reported.muted_until && new Date(item.reported.muted_until) > new Date() && <Badge color="warning" variant="soft" className="ml-1">muted</Badge>}
                      {item.reported.banned && <Badge color="destructive" variant="soft" className="ml-1">banned</Badge>}
                    </div>
                  )}
                </div>
                <blockquote className="border-l-4 border-default-300 pl-3 whitespace-pre-wrap text-default-800">{item.excerpt}</blockquote>
                <div className="text-xs text-default-500">Reported by {item.reporters.join(", ")}</div>
                <Input placeholder="Reason (sent to the member with a warning or mute)" value={reasons[key] ?? ""} onChange={(e) => setReasons({ ...reasons, [key]: e.target.value })} />
                <div className="flex flex-wrap gap-2">
                  {ACTIONS.map((a) => (
                    <Button key={a.action} size="sm" variant="outline" color={a.color} disabled={resolve.isPending || (!item.reported && ["warn", "mute1", "mute7", "ban"].includes(a.action))} onClick={() => run(item, a.action)}>
                      {a.label}
                    </Button>
                  ))}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle className="text-base">Recent removals, warnings and mutes</CardTitle>
          <CardDescription>Three automatic removals in a day mute a member for 24 hours. Three warnings in 90 days mute them for 7 days.</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>When</TableHead>
                <TableHead>Member</TableHead>
                <TableHead>What</TableHead>
                <TableHead>Why</TableHead>
                <TableHead>Until</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.strikes.length === 0 && <TableRow><TableCell colSpan={5} className="text-default-500">Nothing yet.</TableCell></TableRow>}
              {data?.strikes.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="text-sm whitespace-nowrap">{formatDateTime(s.created_at)}</TableCell>
                  <TableCell className="text-sm">{s.member ? <Link className="text-primary" href={`/members/${s.member.id}`}>{s.member.name}</Link> : "—"}</TableCell>
                  <TableCell><Badge color={KIND_COLOR[s.kind] ?? "secondary"} variant="soft">{s.kind === "removed" ? "auto-removed" : s.kind}</Badge>{s.by_admin && <span className="text-xs text-default-500 ml-1">by a moderator</span>}</TableCell>
                  <TableCell className="text-sm max-w-md">{s.reason.startsWith("mod_removed_") ? ({ mod_removed_link: "Link or phone number from a new member", mod_removed_phrase: "Scam phrase", mod_removed_item: "Banned goods item" } as Record<string, string>)[s.reason] ?? s.reason : s.reason}</TableCell>
                  <TableCell className="text-sm whitespace-nowrap">{s.until ? formatDateTime(s.until) : "—"}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
};

export default ModerationPage;
