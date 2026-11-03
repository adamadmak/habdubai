import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type { ReactNode } from "react";
import { Night } from "./Night";

/** Ge'ez numerals, for marking the sections of a page: ፩ ፪ ፫. */
export const GEEZ = ["፩", "፪", "፫", "፬", "፭", "፮", "፯", "፰", "፱", "፲"];

/** A section: a Ge'ez numeral, a title, an optional line under it, and a quiet link to the rest. */
export function Section({ title, lead, more, children, id, mark, className = "" }: {
  title: string;
  lead?: string;
  more?: { href: string; label: string };
  children: ReactNode;
  id?: string;
  mark?: number;
  className?: string;
}) {
  return (
    <section id={id} className={`reveal mx-auto mt-16 max-w-6xl px-4 md:mt-24 ${className}`}>
      <div className="mb-5 flex items-end justify-between gap-4 md:mb-6">
        <div className="flex min-w-0 items-start gap-3">
          {mark !== undefined && <span className="geez-mark mt-0.5 hidden sm:inline-grid" aria-hidden="true">{GEEZ[mark - 1]}</span>}
          <div className="min-w-0">
            <h2 className="headline text-[1.625rem] md:text-[2.125rem]">{title}</h2>
            {lead && <p className="mt-1.5 max-w-2xl text-sm text-muted md:text-[0.9375rem]">{lead}</p>}
          </div>
        </div>
        {more && (
          <Link href={more.href} className="press flex shrink-0 items-center gap-0.5 rounded-full border border-line bg-elevated py-1.5 pl-3.5 pr-2.5 text-sm font-semibold hover:border-fg/30">
            {more.label}
            <ChevronRight className="size-4" aria-hidden="true" />
          </Link>
        )}
      </div>
      {children}
    </section>
  );
}

/** The head of a section page: a night band with the page's name, under the floating header. */
export function PageHead({ title, lead, children, word }: { title: string; lead?: string; children?: ReactNode; word?: string }) {
  return (
    <Night low word={word}>
      <div className="mx-auto max-w-6xl px-4 pb-8 pt-10 md:pb-10 md:pt-16">
        <h1 className="display settle max-w-3xl text-[2.375rem] md:text-[3.5rem]">{title}</h1>
        {lead && <p className="settle settle-2 mt-4 max-w-2xl text-[1.0625rem] muted-on-night md:text-lg">{lead}</p>}
        {children && <div className="settle settle-3">{children}</div>}
      </div>
    </Night>
  );
}

export function Empty({ children }: { children: ReactNode }) {
  return (
    <div className="card relative overflow-hidden p-10 text-center text-muted">
      <div className="tibeb-thin absolute inset-x-0 top-0" aria-hidden="true" />
      {children}
    </div>
  );
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
