import Link from "next/link";
import { ChevronLeft, ChevronRight, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Dawn } from "./Dawn";

/** Ge'ez numerals, for marking the sections of a page: ፩ ፪ ፫. */
export const GEEZ = ["፩", "፪", "፫", "፬", "፭", "፮", "፯", "፰", "፱", "፲"];

/**
 * A section: a small label, a heavy title, an optional line under it, and a quiet
 * link to the rest. With a `tone` it runs edge to edge as one of the page's bands.
 */
export function Section({ title, lead, more, children, id, mark, kicker, tone, className = "" }: {
  title: string;
  lead?: string;
  more?: { href: string; label: string };
  children: ReactNode;
  id?: string;
  mark?: number;
  kicker?: string;
  tone?: "cream" | "iced" | "mint" | "grey";
  className?: string;
}) {
  const inner = (
    <>
      <div className="mb-6 flex items-end justify-between gap-4 md:mb-9">
        <div className="flex min-w-0 items-start gap-3">
          {mark !== undefined && !kicker && <span className="geez-mark mt-0.5 hidden sm:inline-grid" aria-hidden="true">{GEEZ[mark - 1]}</span>}
          <div className="min-w-0">
            {kicker && <p className="kicker mb-3">{kicker}</p>}
            <h2 className="headline text-[2rem] md:text-[2.75rem] lg:text-[3.25rem]">{title}</h2>
            {lead && <p className="mt-2.5 max-w-2xl text-muted md:text-[1.0625rem]">{lead}</p>}
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
    </>
  );

  if (tone) {
    return (
      <section id={id} className={`band-${tone} mt-16 py-16 md:mt-24 md:py-24 ${className}`}>
        <div className="reveal mx-auto max-w-page px-4 lg:px-8">{inner}</div>
      </section>
    );
  }

  return (
    <section id={id} className={`reveal mx-auto mt-16 max-w-page px-4 lg:px-8 md:mt-24 ${className}`}>
      {inner}
    </section>
  );
}

/** Numbered steps, each a white card with its number in a small label and a soft red corner. */
export function Steps({ items, stepLabel, icons }: {
  items: readonly { title: string; text: string }[];
  /** The word for a step, with {n} where its number goes. */
  stepLabel: string;
  icons?: readonly LucideIcon[];
}) {
  return (
    <ol className={`grid gap-4 ${items.length === 3 ? "md:grid-cols-3" : "sm:grid-cols-2 lg:grid-cols-4"}`}>
      {items.map((step, i) => {
        const Icon = icons?.[i];
        return (
          <li key={step.title} className="card relative min-h-[11rem] overflow-hidden p-6 pr-20">
            <p className="text-xs font-extrabold uppercase tracking-wide text-primary">{stepLabel.replace("{n}", String(i + 1).padStart(2, "0"))}</p>
            <h3 className="headline mt-2 text-[1.3125rem]">{step.title}</h3>
            <p className="mt-2 text-sm text-muted">{step.text}</p>
            <span aria-hidden="true" className="absolute -bottom-6 -right-6 grid size-24 place-items-center rounded-full bg-primary-soft text-primary">
              {Icon ? <Icon className="size-7 -translate-x-2 -translate-y-2" /> : <span className="-translate-x-2 -translate-y-2 text-2xl font-black">{i + 1}</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

/** The head of a section page: a cream band with the page's name, under the floating header. */
export function PageHead({ title, lead, children, word }: { title: string; lead?: string; children?: ReactNode; word?: string }) {
  return (
    <Dawn low word={word}>
      <div className="mx-auto max-w-page px-4 lg:px-8 pb-10 pt-12 md:pb-14 md:pt-20">
        <h1 className="display settle max-w-4xl text-[2.625rem] md:text-[4rem] lg:text-[4.5rem]">{title}</h1>
        {lead && <p className="settle settle-2 mt-4 max-w-2xl text-[1.0625rem] muted-on-night md:text-lg">{lead}</p>}
        {children && <div className="settle settle-3">{children}</div>}
      </div>
    </Dawn>
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
