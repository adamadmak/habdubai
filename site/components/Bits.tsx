import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type { ReactNode } from "react";

/** A section: a title, an optional line under it, and a quiet link to the rest. */
export function Section({ title, lead, more, children, id }: { title: string; lead?: string; more?: { href: string; label: string }; children: ReactNode; id?: string }) {
  return (
    <section id={id} className="mx-auto mt-14 max-w-6xl px-4">
      <div className="mb-4 flex items-end justify-between gap-4">
        <div>
          <h2 className="title text-[1.375rem] md:text-2xl">{title}</h2>
          {lead && <p className="mt-1 text-sm text-muted">{lead}</p>}
        </div>
        {more && (
          <Link href={more.href} className="press flex shrink-0 items-center gap-0.5 text-sm font-semibold text-primary">
            {more.label}
            <ChevronRight className="size-4" aria-hidden="true" />
          </Link>
        )}
      </div>
      {children}
    </section>
  );
}

export function PageHead({ title, lead, children }: { title: string; lead?: string; children?: ReactNode }) {
  return (
    <div className="mx-auto max-w-6xl px-4 pt-8 md:pt-12">
      <h1 className="display text-[2rem] md:text-[2.75rem]">{title}</h1>
      {lead && <p className="mt-3 max-w-2xl text-[1.0625rem] text-muted">{lead}</p>}
      {children}
    </div>
  );
}

export function Empty({ children }: { children: ReactNode }) {
  return <div className="card p-8 text-center text-muted">{children}</div>;
}

/** Previous and next pages, as plain links that keep the filters. */
export function Pager({ page, last, href, labels }: { page: number; last: number; href: (page: number) => string; labels: { previous: string; next: string; pageOf: string } }) {
  if (last <= 1) return null;
  return (
    <nav aria-label={labels.pageOf} className="mt-8 flex items-center justify-between gap-4">
      {page > 1 ? (
        <Link href={href(page - 1)} className="btn btn-line" rel="prev">
          <ChevronLeft className="size-4" aria-hidden="true" /> {labels.previous}
        </Link>
      ) : <span />}
      <span className="text-sm text-muted tabular">{labels.pageOf}</span>
      {page < last ? (
        <Link href={href(page + 1)} className="btn btn-line" rel="next">
          {labels.next} <ChevronRight className="size-4" aria-hidden="true" />
        </Link>
      ) : <span />}
    </nav>
  );
}

export function Notice({ tone = "info", title, children }: { tone?: "info" | "warning" | "danger" | "success"; title?: string; children: ReactNode }) {
  const tones = {
    info: "bg-primary-soft text-fg",
    warning: "bg-warning-soft text-fg",
    danger: "bg-danger-soft text-fg",
    success: "bg-success-soft text-fg",
  } as const;
  const bar = { info: "bg-primary", warning: "bg-warning", danger: "bg-danger", success: "bg-success" } as const;

  return (
    <div className={`relative overflow-hidden rounded-2xl p-4 pl-5 ${tones[tone]}`}>
      <span className={`absolute inset-y-0 left-0 w-1.5 ${bar[tone]}`} aria-hidden="true" />
      {title && <p className="mb-1 font-semibold">{title}</p>}
      <div className="text-sm">{children}</div>
    </div>
  );
}
