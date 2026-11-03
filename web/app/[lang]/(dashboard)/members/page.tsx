"use client";
import { Suspense, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { StatusBadge, RoleBadge } from "@/components/member-badges";
import { useApiQuery, formatDateTime, type MembersPage, LANGUAGE_NAMES } from "@/lib/api";

const STATUSES = [
  ["", "All statuses"],
  ["active", "Active"],
  ["onboarding", "Still joining"],
  ["needs_review", "Needs review"],
  ["banned", "Banned"],
  ["left", "Left"],
];

const MembersPageInner = () => {
  const router = useRouter();
  const params = useSearchParams();
  const status = params.get("status") ?? "";
  const role = params.get("role") ?? "";
  const search = params.get("search") ?? "";
  const page = Number(params.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);

  const query = new URLSearchParams();
  if (status) query.set("status", status);
  if (role) query.set("role", role);
  if (search) query.set("search", search);
  query.set("page", String(page));

  const { data, isLoading, error } = useApiQuery<MembersPage>(["members", query.toString()], `/admin/members?${query.toString()}`);

  const update = (changes: Record<string, string>) => {
    const next = new URLSearchParams(params.toString());
    Object.entries(changes).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
    if (!("page" in changes)) next.delete("page");
    router.push(`/members?${next.toString()}`);
  };

  return (
    <div>
      <PageTitle title="Members" description="Everyone who has shared a verified phone number with the bot." />

      <Card>
        <CardContent className="p-4 flex flex-wrap gap-3 items-center">
          <form
            className="flex gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              update({ search: searchInput.trim() });
            }}
          >
            <Input value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Name, @username, last 4 digits, Telegram id" className="w-72" />
            <Button type="submit" variant="outline">Search</Button>
          </form>
          <Select value={status || "all"} onValueChange={(v) => update({ status: v === "all" ? "" : v })}>
            <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
            <SelectContent>
              {STATUSES.map(([value, label]) => <SelectItem key={value || "all"} value={value || "all"}>{label}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={role || "all"} onValueChange={(v) => update({ role: v === "all" ? "" : v })}>
            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All roles</SelectItem>
              {(data?.choices.roles ?? ["member", "leader", "moderator", "admin"]).map((r) => <SelectItem key={r} value={r} className="capitalize">{r}</SelectItem>)}
            </SelectContent>
          </Select>
          {data && <span className="text-sm text-default-500 ml-auto">{data.meta.total} member{data.meta.total === 1 ? "" : "s"}</span>}
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardContent className="p-0">
          {error && <div className="p-4 text-destructive">{error.message}</div>}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Member</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Emirate</TableHead>
                <TableHead>Trades</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead>Language</TableHead>
                <TableHead>Verified</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableRow><TableCell colSpan={8} className="text-default-500">Loading…</TableCell></TableRow>}
              {data?.data.length === 0 && <TableRow><TableCell colSpan={8} className="text-default-500">No members match.</TableCell></TableRow>}
              {data?.data.map((m) => (
                <TableRow key={m.id} className="cursor-pointer" onClick={() => router.push(`/members/${m.id}`)}>
                  <TableCell>
                    <Link href={`/members/${m.id}`} className="font-medium text-default-900 hover:text-primary" onClick={(e) => e.stopPropagation()}>{m.display_name}</Link>
                    <div className="text-xs text-default-500">{m.telegram_username ? `@${m.telegram_username}` : `id ${m.telegram_user_id}`}</div>
                  </TableCell>
                  <TableCell><StatusBadge status={m.status} /> {m.needs_review && <Badge color="warning" variant="soft" className="ml-1">review</Badge>}</TableCell>
                  <TableCell><RoleBadge role={m.role} /></TableCell>
                  <TableCell>{m.emirate ? data.choices.emirates[m.emirate]?.en ?? m.emirate : "—"}</TableCell>
                  <TableCell className="text-sm">{m.trades.map((t) => data.choices.trades[t]?.en ?? t).join(", ") || "—"}</TableCell>
                  <TableCell className="font-mono text-sm">+{m.phone_country} …{m.phone_last4}</TableCell>
                  <TableCell>{LANGUAGE_NAMES[m.language] ?? m.language}</TableCell>
                  <TableCell className="text-sm text-default-600">{formatDateTime(m.verified_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between p-4 border-t border-default-200">
              <span className="text-sm text-default-500">Page {data.meta.page} of {data.meta.last_page}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => update({ page: String(page - 1) })}>Previous</Button>
                <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => update({ page: String(page + 1) })}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

const MembersPage = () => (
  <Suspense>
    <MembersPageInner />
  </Suspense>
);

export default MembersPage;
