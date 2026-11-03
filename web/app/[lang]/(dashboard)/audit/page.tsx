"use client";
import { useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import Link from "next/link";
import { ScrollText } from "lucide-react";
import AuditChange from "@/components/audit-change";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { useApiQuery, formatDateTime, type AuditEntry } from "@/lib/api";

/** Where a logged subject lives in the admin, when it has a page. */
const SUBJECT_PAGE: Record<string, (id: number) => string> = {
  member: (id) => `/members/${id}`,
  job: (id) => `/jobs/${id}`,
};

const AuditPage = () => {
  const [page, setPage] = useState(1);
  const { data, isLoading, error, refetch } = useApiQuery<{ data: AuditEntry[]; meta: { total: number; page: number; last_page: number } }>(["audit", page], `/admin/audit?page=${page}`, { placeholderData: (previous) => previous });

  return (
    <div>
      <PageTitle title="Audit log" description="Every admin and moderator action, and what it changed." />
      <Card>
        <CardContent className="p-0">
          {error && <ErrorState className="m-4" error={error} onRetry={() => refetch()} title="The audit log could not be loaded" />}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>When</TableHead>
                <TableHead>Who</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead className="!text-left">Change</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableMessage colSpan={5}><LoadingRows className="p-4" /></TableMessage>}
              {data?.data.length === 0 && (
                <TableMessage colSpan={5}>
                  <EmptyState icon={ScrollText} compact title="Nothing logged yet">Every sign-in, setting change and moderator decision is written here.</EmptyState>
                </TableMessage>
              )}
              {data?.data.map((e) => (
                <TableRow key={e.id}>
                  <TableCell className="whitespace-nowrap text-sm">{formatDateTime(e.created_at)}</TableCell>
                  <TableCell className="text-sm">{e.actor?.name ?? (e.actor_id ? `#${e.actor_id}` : "bot")}</TableCell>
                  <TableCell className="text-sm text-default-900 whitespace-nowrap">{e.action.replace(/[._]/g, " ")}</TableCell>
                  <TableCell className="text-sm whitespace-nowrap">
                    {e.subject_type
                      ? e.subject_id && SUBJECT_PAGE[e.subject_type]
                        ? <Link className="text-primary hover:underline" href={SUBJECT_PAGE[e.subject_type](e.subject_id)}>{e.subject_type.replace(/_/g, " ")} #{e.subject_id}</Link>
                        : `${e.subject_type.replace(/_/g, " ")}${e.subject_id ? ` #${e.subject_id}` : ""}`
                      : "—"}
                  </TableCell>
                  <TableCell className="text-xs text-default-600 min-w-[16rem] max-w-md !text-left">
                    {(e.before || e.after) ? <AuditChange before={e.before} after={e.after} /> : "—"}
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
