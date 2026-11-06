"use client";
import { Suspense, useEffect, useState } from "react";
import { Briefcase } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { JobStatusBadge, ScamScore } from "@/components/job-badges";
import { cn } from "@/lib/utils";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { useApiQuery, formatDateTime, type JobsPage, type JobStatus, CATEGORY_NAMES, JOB_EMIRATE_NAMES } from "@/lib/api";

const TABS: { value: JobStatus | "all"; label: string }[] = [
  { value: "review", label: "Needs review" },
  { value: "published", label: "Published" },
  { value: "rejected", label: "Rejected" },
  { value: "duplicate", label: "Duplicates" },
  { value: "closed", label: "Closed" },
  { value: "all", label: "All" },
];

const JobsPageInner = () => {
  const router = useRouter();
  const params = useSearchParams();
  const status = (params.get("status") ?? "review") as JobStatus | "all";
  const search = params.get("search") ?? "";
  const page = Number(params.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);
  useEffect(() => setSearchInput(search), [search]);

  const query = new URLSearchParams({ status, page: String(page) });
  if (search) query.set("search", search);

  const { data, isLoading, error, refetch } = useApiQuery<JobsPage>(["jobs", query.toString()], `/admin/jobs?${query.toString()}`, { refetchInterval: 30_000, placeholderData: (previous) => previous });

  const update = (changes: Record<string, string>) => {
    const next = new URLSearchParams(params.toString());
    Object.entries(changes).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
    if (!("page" in changes)) next.delete("page");
    router.push(`/jobs?${next.toString()}`);
  };

  const total = data ? Object.values(data.counts).reduce((s, n) => s + (n ?? 0), 0) : 0;

  return (
    <div>
      <PageTitle title="Jobs" description="Every listing the pipeline has seen. Anything risky or unclassified waits here for a moderator." />

      <div className="flex flex-wrap items-center gap-2 mb-4">
        {TABS.map((tab) => {
          const count = tab.value === "all" ? total : data?.counts[tab.value] ?? 0;
          return (
            <button
              key={tab.value}
              type="button"
              aria-pressed={status === tab.value}
              onClick={() => update({ status: tab.value })}
              className={cn(
                "px-3 py-1.5 rounded-md text-sm font-medium border transition-colors",
                status === tab.value ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary"
              )}
            >
              {tab.label}
              {data && <span className="ml-1.5 opacity-75">{count}</span>}
            </button>
          );
        })}
        <form
          className="flex gap-2 ml-auto"
          role="search"
          onSubmit={(e) => {
            e.preventDefault();
            update({ search: searchInput.trim() });
          }}
        >
          <Input value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Title or company" className="w-56" aria-label="Search jobs" />
          <Button type="submit" variant="outline">Search</Button>
        </form>
      </div>

      <Card>
        <CardContent className="p-0">
          {error && <ErrorState className="m-4" error={error} onRetry={() => refetch()} title="Jobs could not be loaded" />}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Job</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Work</TableHead>
                <TableHead>Where</TableHead>
                <TableHead>Salary</TableHead>
                <TableHead>Risk</TableHead>
                <TableHead>Source</TableHead>
                <TableHead>Seen</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableMessage colSpan={8}><LoadingRows className="p-4" /></TableMessage>}
              {data?.data.length === 0 && (
                <TableMessage colSpan={8}>
                  <EmptyState icon={Briefcase} title={search ? "No jobs match that search" : status === "review" ? "Nothing waiting for review" : "No jobs here"} compact>
                    {search
                      ? "Search looks at the title and the company name."
                      : status === "review"
                        ? "Risky or unclassified listings land here. New ones arrive as the sources run."
                        : "Listings appear here as the job sources run. Add or fix a source on Job sources."}
                  </EmptyState>
                </TableMessage>
              )}
              {data?.data.map((job) => (
                <TableRow key={job.id} className="cursor-pointer" onClick={() => router.push(`/jobs/${job.id}`)}>
                  <TableCell className="max-w-xs">
                    {job.photo && <img src={job.photo} alt="" loading="lazy" className="mb-1 h-16 w-16 rounded object-cover object-top" />}
                    <Link href={`/jobs/${job.id}`} className="font-medium text-default-900 hover:text-primary" onClick={(e) => e.stopPropagation()}>{job.title}</Link>
                    <div className="text-xs text-default-500 truncate">{job.company ?? "—"}</div>
                    {job.status === "review" && job.review_reason && <div className="text-xs text-warning mt-1 line-clamp-2">{job.review_reason}</div>}
                  </TableCell>
                  <TableCell>
                    <JobStatusBadge status={job.status} />
                    {job.reports_count > 0 && <Badge color="destructive" variant="outline" className="ml-1">{job.reports_count} report{job.reports_count === 1 ? "" : "s"}</Badge>}
                  </TableCell>
                  <TableCell className="text-sm">{CATEGORY_NAMES[job.category] ?? job.category}</TableCell>
                  <TableCell className="text-sm">{JOB_EMIRATE_NAMES[job.emirate] ?? job.emirate}</TableCell>
                  <TableCell className="text-sm whitespace-nowrap">{job.salary ?? "—"}</TableCell>
                  <TableCell><ScamScore score={job.scam_score} /></TableCell>
                  <TableCell className="text-sm">{job.source ?? "—"}</TableCell>
                  <TableCell className="text-sm text-default-600 whitespace-nowrap">{formatDateTime(job.created_at)}</TableCell>
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

const JobsPageWrapper = () => (
  <Suspense>
    <JobsPageInner />
  </Suspense>
);

export default JobsPageWrapper;
