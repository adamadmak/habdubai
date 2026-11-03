"use client";
import React from "react";
import { AlertTriangle, Inbox, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { errorText } from "@/lib/api";

/** Placeholder rows while a page or list loads. */
export const LoadingRows = ({ rows = 4, className }: { rows?: number; className?: string }) => (
  <div className={cn("space-y-3", className)} role="status" aria-live="polite">
    <span className="sr-only">Loading…</span>
    {Array.from({ length: rows }, (_, i) => (
      <Skeleton key={i} className="h-12 w-full rounded-lg bg-default-100" />
    ))}
  </div>
);

/** A whole page's first load. */
export const PageLoading = () => (
  <div role="status" aria-live="polite" className="space-y-6">
    <span className="sr-only">Loading…</span>
    <div className="space-y-2">
      <Skeleton className="h-8 w-64 rounded-lg bg-default-100" />
      <Skeleton className="h-4 w-96 max-w-full rounded bg-default-100" />
    </div>
    <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-24 rounded-xl bg-default-100" />)}
    </div>
    <Skeleton className="h-64 rounded-xl bg-default-100" />
  </div>
);

/** A failed load, with the reason in plain words and a way to try again. */
export const ErrorState = ({ error, onRetry, title = "This could not be loaded", className }: { error: unknown; onRetry?: () => void; title?: string; className?: string }) => (
  <div role="alert" className={cn("flex flex-wrap items-start gap-3 rounded-xl border border-destructive/25 bg-destructive/[0.04] p-4", className)}>
    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" aria-hidden="true" />
    <div className="min-w-0 flex-1">
      <div className="font-medium text-default-900">{title}</div>
      <div className="text-sm text-default-600">{errorText(error)}</div>
    </div>
    {onRetry && (
      <Button size="sm" variant="outline" color="secondary" onClick={onRetry}>
        <RefreshCw className="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Try again
      </Button>
    )}
  </div>
);

/** Nothing to show, and what to do about it. */
export const EmptyState = ({
  title, children, icon: Icon = Inbox, action, className, compact = false,
}: { title: string; children?: React.ReactNode; icon?: React.ElementType; action?: React.ReactNode; className?: string; compact?: boolean }) => (
  <div className={cn("flex flex-col items-center text-center", compact ? "py-8 px-4" : "py-14 px-6", className)}>
    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-50 text-primary ring-1 ring-primary-100">
      <Icon className="h-5 w-5" aria-hidden="true" />
    </div>
    <div className="font-semibold text-default-900">{title}</div>
    {children && <div className="mt-1 max-w-md text-sm leading-relaxed text-default-600">{children}</div>}
    {action && <div className="mt-4">{action}</div>}
  </div>
);

/** A table body row that spans every column, for loading, empty and error states inside a table. */
export const TableMessage = ({ colSpan, children }: { colSpan: number; children: React.ReactNode }) => (
  <tr>
    <td colSpan={colSpan} className="p-0">{children}</td>
  </tr>
);
