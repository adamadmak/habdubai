"use client";
import { useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { useApiQuery, formatDateTime, type AuditEntry } from "@/lib/api";

const AuditPage = () => {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useApiQuery<{ data: AuditEntry[]; meta: { total: number; page: number; last_page: number } }>(["audit", page], `/admin/audit?page=${page}`);

  return (
    <div>
      <PageTitle title="Audit log" description="Every admin and moderator action, and what it changed." />
      <Card>
        <CardContent className="p-0">
          {error && <div className="p-4 text-destructive">{error.message}</div>}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>When</TableHead>
                <TableHead>Who</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead>Change</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableRow><TableCell colSpan={5} className="text-default-500">Loading…</TableCell></TableRow>}
              {data?.data.length === 0 && <TableRow><TableCell colSpan={5} className="text-default-500">Nothing yet.</TableCell></TableRow>}
              {data?.data.map((e) => (
                <TableRow key={e.id}>
                  <TableCell className="whitespace-nowrap text-sm">{formatDateTime(e.created_at)}</TableCell>
                  <TableCell className="text-sm">{e.actor?.name ?? (e.actor_id ? `#${e.actor_id}` : "bot")}</TableCell>
                  <TableCell className="font-mono text-sm">{e.action}</TableCell>
                  <TableCell className="text-sm">{e.subject_type ? `${e.subject_type}${e.subject_id ? ` #${e.subject_id}` : ""}` : "—"}</TableCell>
                  <TableCell className="text-xs text-default-600 max-w-md">
                    {e.before && <div><span className="text-default-400">before</span> <code>{JSON.stringify(e.before)}</code></div>}
                    {e.after && <div><span className="text-default-400">after</span> <code>{JSON.stringify(e.after)}</code></div>}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between p-4 border-t border-default-200">
              <span className="text-sm text-default-500">Page {data.meta.page} of {data.meta.last_page}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
                <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default AuditPage;
