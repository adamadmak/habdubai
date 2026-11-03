"use client";
import { useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import PageTitle from "@/components/page-title";
import { StatusBadge, RoleBadge } from "@/components/member-badges";
import { useApiQuery, useApiMutation, formatDateTime, type Member, type AuditEntry, LANGUAGE_NAMES } from "@/lib/api";

const EMIRATES: Record<string, string> = { dubai: "Dubai", sharjah: "Sharjah", abu_dhabi: "Abu Dhabi", ajman: "Ajman", other: "Other emirate" };
const TRADES: Record<string, string> = {
  domestic: "Domestic work", driver: "Driver", security: "Security", cleaning: "Cleaning", hospitality: "Hospitality", salon: "Salon and beauty",
  retail: "Retail and sales", warehouse: "Warehouse and delivery", nursing: "Nursing and care", office: "Office and admin", construction: "Construction and technical", other: "Other",
};

const Row = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <div className="flex justify-between gap-4 py-2 border-b border-default-100 last:border-0 text-sm">
    <span className="text-default-500">{label}</span>
    <span className="text-default-900 text-right">{children}</span>
  </div>
);

const MemberPage = () => {
  const { id } = useParams<{ id: string }>();
  const key = ["member", id];
  const { data, isLoading, error } = useApiQuery<{ member: Member; audit: AuditEntry[] }>(key, `/admin/members/${id}`);
  const [banReason, setBanReason] = useState("");

  const patch = useApiMutation<{ member: Member }, Partial<Pick<Member, "role" | "needs_review" | "display_name">>>(
    (request, body) => request(`/admin/members/${id}`, "PATCH", body),
    [key, ["members"], ["dashboard"]]
  );
  const ban = useApiMutation<{ member: Member }, string>((request, reason) => request(`/admin/members/${id}/ban`, "POST", { reason }), [key, ["members"], ["dashboard"]]);
  const unban = useApiMutation<{ member: Member }>((request) => request(`/admin/members/${id}/unban`, "POST"), [key, ["members"], ["dashboard"]]);

  if (error) return <div className="text-destructive">{error.message}</div>;
  if (isLoading || !data) return <div className="text-default-600">Loading…</div>;

  const m = data.member;
  const act = (promise: Promise<unknown>, done: string) => promise.then(() => toast.success(done)).catch((e: Error) => toast.error(e.message));

  return (
    <div>
      <PageTitle title={m.display_name} description={m.telegram_username ? `@${m.telegram_username}` : `Telegram id ${m.telegram_user_id}`}>
        <Link href="/members"><Button variant="outline" size="sm">All members</Button></Link>
      </PageTitle>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle className="text-base">Profile</CardTitle></CardHeader>
          <CardContent>
            <Row label="Status"><StatusBadge status={m.status} /> {m.needs_review && <Badge color="warning" variant="soft" className="ml-1">needs review</Badge>}</Row>
            <Row label="Role"><RoleBadge role={m.role} /></Row>
            <Row label="Phone"><span className="font-mono">+{m.phone_country} ••• {m.phone_last4}</span></Row>
            <Row label="Emirate">{m.emirate ? EMIRATES[m.emirate] ?? m.emirate : "—"}</Row>
            <Row label="Trades">{m.trades.map((t) => TRADES[t] ?? t).join(", ") || "—"}</Row>
            <Row label="Language">{LANGUAGE_NAMES[m.language] ?? m.language}</Row>
            <Row label="Verified">{formatDateTime(m.verified_at)}</Row>
            <Row label="Agreed to the rules">{formatDateTime(m.agreed_rules_at)}</Row>
            {m.left_at && <Row label="Left">{formatDateTime(m.left_at)}</Row>}
            {m.banned_at && <Row label="Banned">{formatDateTime(m.banned_at)}{m.ban_reason ? ` · ${m.ban_reason}` : ""}</Row>}
            <Row label="Helper points">{m.helper_points}</Row>
            <Row label="Goods trips">{m.trips_completed}{m.rating_count > 0 ? ` · ${m.rating_avg} ★ (${m.rating_count})` : ""}</Row>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">Moderation</CardTitle></CardHeader>
            <CardContent className="space-y-5">
              <div>
                <Label className="mb-2 block">Role</Label>
                <Select value={m.role} onValueChange={(role) => act(patch.mutateAsync({ role: role as Member["role"] }), `Role set to ${role}.`)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {["member", "leader", "moderator", "admin"].map((r) => <SelectItem key={r} value={r} className="capitalize">{r}</SelectItem>)}
                  </SelectContent>
                </Select>
                <p className="mt-1 text-xs text-default-500">A leader posts announcements; a moderator handles reports; roles are set here, never earned.</p>
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label>Needs review</Label>
                  <p className="text-xs text-default-500">Set when the phone is outside the UAE and Ethiopia.</p>
                </div>
                <Switch checked={m.needs_review} onCheckedChange={(v) => act(patch.mutateAsync({ needs_review: v }), v ? "Marked for review." : "Review cleared.")} />
              </div>

              {m.status === "banned" ? (
                <Button color="success" variant="outline" className="w-full" disabled={unban.isPending} onClick={() => act(unban.mutateAsync(), "Ban lifted.")}>Lift the ban</Button>
              ) : (
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button color="destructive" variant="outline" className="w-full">Ban this member</Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle>Ban {m.display_name}?</AlertDialogTitle>
                      <AlertDialogDescription>
                        They are removed from the groups, every post is hidden, and the phone number cannot verify again. Only an admin can lift it.
                      </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div>
                      <Label htmlFor="ban-reason" className="mb-2 block">Reason (kept in the audit log)</Label>
                      <Input id="ban-reason" value={banReason} onChange={(e) => setBanReason(e.target.value)} placeholder="Fake job posts" />
                    </div>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancel</AlertDialogCancel>
                      <AlertDialogAction className="bg-destructive text-destructive-foreground" onClick={() => act(ban.mutateAsync(banReason), "Member banned.")}>Ban</AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <Card className="mt-4">
        <CardHeader><CardTitle className="text-base">History</CardTitle></CardHeader>
        <CardContent>
          {data.audit.length === 0 ? (
            <div className="text-sm text-default-500">No moderator action yet.</div>
          ) : (
            <ul className="space-y-2 text-sm">
              {data.audit.map((entry) => (
                <li key={entry.id} className="flex gap-3">
                  <span className="text-default-500 whitespace-nowrap">{formatDateTime(entry.created_at)}</span>
                  <span className="text-default-900">{entry.action}</span>
                  {entry.after && <code className="text-xs text-default-600">{JSON.stringify(entry.after)}</code>}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default MemberPage;
