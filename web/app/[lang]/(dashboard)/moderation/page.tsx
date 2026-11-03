"use client";
import { useState } from "react";
import Link from "next/link";
import { Flag } from "lucide-react";
import ConfirmButton from "@/components/confirm-button";
import { EmptyState, ErrorState, LoadingRows } from "@/components/states";
import { toastResult } from "@/lib/toast";
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

const ACTIONS: { action: Action; label: string; color?: "destructive" | "warning" | "success"; confirm?: string }[] = [
  { action: "dismiss", label: "Keep it" },
  { action: "delete", label: "Delete message", confirm: "The message is deleted from the group. Nothing is sent to the member." },
  { action: "warn", label: "Delete and warn", color: "warning", confirm: "The message is deleted and the member gets a warning with your reason. Three warnings in 90 days mute them for 7 days." },
  { action: "mute1", label: "Mute 1 day", color: "warning", confirm: "The message is deleted and the member cannot write in the groups for 24 hours. They are told why." },
  { action: "mute7", label: "Mute 7 days", color: "warning", confirm: "The message is deleted and the member cannot write in the groups for 7 days. They are told why." },
  { action: "ban", label: "Ban", color: "destructive", confirm: "The message is deleted, the member is removed from both groups and the bot stops serving them. Only an admin can lift a ban." },
];

const KIND_COLOR: Record<string, "secondary" | "warning" | "destructive"> = { removed: "secondary", warning: "warning", mute: "destructive" };

const ModerationPage = () => {
  const { data, isLoading, error, refetch } = useApiQuery<{ queue: QueueItem[]; strikes: Strike[] }>(["moderation"], "/admin/moderation", { refetchInterval: 20_000 });
  const [reasons, setReasons] = useState<Record<string, string>>({});

  const resolve = useApiMutation<unknown, { item: QueueItem; action: Action }>(
    (req, { item, action }) => req("/admin/moderation/resolve", "POST", { chat_id: item.chat_id, message_id: item.message_id, action, reason: reasons[`${item.chat_id}:${item.message_id}`] || null }),
    [["moderation"], ["dashboard"], ["members"]]
  );

  const run = (item: QueueItem, action: Action) => toastResult(resolve.mutateAsync({ item, action }), "Done; the reports are closed.");

  return (
    <div>
      <PageTitle title="Moderation" description="Messages members reported in the groups with /report. Three reports take a message down until you decide. Automatic removals, warnings and mutes are listed below." />

      {error && <ErrorState className="mb-4" error={error} onRetry={() => refetch()} title="The moderation queue could not be loaded" />}
      {isLoading && <LoadingRows />}
      {data?.queue.length === 0 && (
        <Card>
          <EmptyState icon={Flag} title="Nothing reported. The groups are quiet.">
            Members report a message by replying to it with /report. It shows up here, and three reports take it down until you decide.
          </EmptyState>
        </Card>
      )}

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
                <Input aria-label="Reason" placeholder="Reason (sent to the member with a warning or mute)" value={reasons[key] ?? ""} onChange={(e) => setReasons({ ...reasons, [key]: e.target.value })} />
                <div className="flex flex-wrap gap-2">
                  {ACTIONS.map((a) => {
                    const disabled = resolve.isPending || (!item.reported && ["warn", "mute1", "mute7", "ban"].includes(a.action));
                    return a.confirm ? (
                      <ConfirmButton key={a.action} size="sm" variant="outline" color={a.color ?? "secondary"} disabled={disabled} label={a.label}
                        title={`${a.label}${item.reported && a.action !== "delete" ? ` for ${item.reported.name}` : ""}?`}
                        description={a.confirm}
                        confirmLabel={a.label}
                        tone={a.color === "warning" ? "warning" : "destructive"}
                        onConfirm={() => run(item, a.action)} />
                    ) : (
                      <Button key={a.action} size="sm" variant="outline" color="secondary" disabled={disabled} onClick={() => run(item, a.action).catch(() => undefined)}>
                        {a.label}
                      </Button>
                    );
                  })}
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
              {data?.strikes.length === 0 && <TableRow><TableCell colSpan={5} className="text-default-500">Nothing yet. Automatic removals and moderator warnings are listed here.</TableCell></TableRow>}
              {data?.strikes.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="text-sm whitespace-nowrap">{formatDateTime(s.created_at)}</TableCell>
                  <TableCell className="text-sm">{s.member ? <Link className="text-primary" href={`/members/${s.member.id}`}>{s.member.name}</Link> : "—"}</TableCell>
                  <TableCell><Badge color={KIND_COLOR[s.kind] ?? "secondary"} variant="soft">{s.kind === "removed" ? "auto-removed" : s.kind}</Badge>{s.by_admin && <span className="text-xs text-default-500 ml-1">by a moderator</span>}</TableCell>
                  <TableCell className="text-sm max-w-md">{(s.reason ?? "").startsWith("mod_removed_") ? ({ mod_removed_link: "Link or phone number from a new member", mod_removed_phrase: "Scam phrase", mod_removed_item: "Banned goods item" } as Record<string, string>)[s.reason] ?? s.reason : s.reason}</TableCell>
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
