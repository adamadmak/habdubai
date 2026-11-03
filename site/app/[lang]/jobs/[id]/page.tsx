import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight, BedDouble, Bell, ChevronLeft, Stamp } from "lucide-react";
import { Notice } from "@/components/Bits";
import { JobCard } from "@/components/Cards";
import { TradeIcon } from "@/components/CategoryIcon";
import { ShareButton } from "@/components/ShareButton";
import { api, apiOrNull, type Job, type Meta } from "@/lib/api";
import { formatDate, relative } from "@/lib/format";
import { dict, fill, isLocale, label } from "@/lib/i18n";
import { siteUrl } from "@/lib/site";

type Detail = { job: Job; similar: Job[] };
type Params = { lang: string; id: string };

async function load(id: string): Promise<Detail | null> {
  return /^\d+$/.test(id) ? apiOrNull<Detail>(`/public/jobs/${id}`) : null;
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang, id } = await params;
  if (!isLocale(lang)) return {};
  const data = await load(id);
  if (!data) return {};
  const { job } = data;
  return {
    title: job.company ? `${job.title} · ${job.company}` : job.title,
    description: (job.summary[lang] || job.summary.en || "").slice(0, 200),
    alternates: { canonical: `/${lang}/jobs/${job.id}` },
    robots: job.open ? undefined : { index: false },
  };
}

export default async function JobPage({ params }: { params: Promise<Params> }) {
  const { lang, id } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const [data, meta] = await Promise.all([load(id), api<Meta>("/public/meta", { revalidate: 300 })]);
  if (!data) notFound();
  const { job, similar } = data;

  const place = [job.area, label(meta.labels.emirates[job.emirate], lang)].filter(Boolean).join(", ");
  const summary = job.summary[lang] || job.summary.en;
  const source = job.source ?? "";

  return (
    <article className="mx-auto max-w-3xl px-4 pt-6 md:pt-10">
      <Link href={`/${lang}/jobs`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {d.jobs.back}
      </Link>

      <header className="mt-5 flex items-start gap-4">
        <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary-soft text-primary">
          <TradeIcon category={job.category} className="size-7" />
        </span>
        <div className="min-w-0">
          <h1 className="display text-[1.75rem] md:text-[2.25rem]">{job.title}</h1>
          <p className="mt-2 text-muted">{[job.company, place].filter(Boolean).join(" · ")}</p>
        </div>
      </header>

      <dl className="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-4">
        {[
          [d.jobs.facts.kind, label(meta.labels.trades[job.category], lang)],
          [d.jobs.facts.where, place || "—"],
          [d.jobs.facts.salary, job.salary ?? d.jobs.salaryNone],
          [fill(d.jobs.posted, { when: "" }).trim(), job.published_at ? formatDate(job.published_at, lang, "medium") : "—"],
        ].map(([term, value]) => (
          <div key={term} className="bg-elevated p-3.5">
            <dt className="text-xs text-muted">{term}</dt>
            <dd className="mt-0.5 font-semibold">{value}</dd>
          </div>
        ))}
      </dl>

      {(job.accommodation || job.visa) && (
        <div className="mt-3 flex flex-wrap gap-2 text-sm">
          {job.accommodation && <span className="inline-flex items-center gap-1.5 rounded-full bg-success-soft px-3 py-1.5 font-medium text-success"><BedDouble className="size-4" aria-hidden="true" />{d.jobs.accommodation}</span>}
          {job.visa && <span className="inline-flex items-center gap-1.5 rounded-full bg-success-soft px-3 py-1.5 font-medium text-success"><Stamp className="size-4" aria-hidden="true" />{d.jobs.visa}</span>}
        </div>
      )}

      {!job.open && <div className="mt-6"><Notice tone="warning">{d.jobs.closed}</Notice></div>}

      <section className="mt-8">
        <h2 className="eyebrow">{d.jobs.summary}</h2>
        <p className="prose-plain mt-2 text-[1.0625rem] leading-relaxed" lang={job.summary[lang] ? lang : "en"}>{summary || d.jobs.noSummary}</p>
      </section>

      {job.warning && job.warning.length > 0 && (
        <div className="mt-6"><Notice tone="danger" title={d.jobs.warningTitle}>{d.jobs.warningText}</Notice></div>
      )}

      <div className="mt-6"><Notice tone="warning" title={d.jobs.safetyTitle}>{d.jobs.safetyText}</Notice></div>

      {/* The decision point of the page: one clear action, and what it does. */}
      <div className="sticky bottom-[calc(var(--tabbar-h)+env(safe-area-inset-bottom)+0.75rem)] z-30 mt-8 md:static">
        <div className="material flex items-center gap-3 rounded-2xl border border-line p-3 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none">
          {job.open && job.apply_url ? (
            <a href={job.apply_url} target="_blank" rel="noopener noreferrer nofollow" className="btn btn-primary flex-1 md:flex-none">
              {source ? fill(d.jobs.apply, { source }) : d.jobs.applyGeneric}
              <ArrowUpRight className="size-4" aria-hidden="true" />
            </a>
          ) : null}
          <ShareButton title={job.title} url={siteUrl(`${lang}/jobs/${job.id}`)} labels={{ share: d.common.share, copied: d.common.copied }} />
        </div>
        {job.open && job.apply_url && <p className="mt-2 hidden text-xs text-muted md:block">{d.jobs.applyNote}{source && ` ${fill(d.jobs.via, { source })}`}</p>}
      </div>

      {meta.bot.link && (
        <a href={`${meta.bot.link}?start=jobs`} target="_blank" rel="noopener noreferrer" className="card lift mt-10 flex items-center gap-4 p-5">
          <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary text-primary-fg">
            <Bell className="size-6" aria-hidden="true" />
          </span>
          <span>
            <span className="title block text-lg">{d.jobs.alertsTitle}</span>
            <span className="mt-0.5 block text-sm text-muted">{d.jobs.alertsText}</span>
          </span>
        </a>
      )}

      {similar.length > 0 && (
        <section className="mt-12">
          <h2 className="title text-xl">{d.jobs.similar}</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {similar.map((j) => <JobCard key={j.id} job={j} lang={lang} meta={meta} compact />)}
          </div>
        </section>
      )}

      <p className="mt-8 text-xs text-faint">{job.published_at && fill(d.jobs.posted, { when: relative(job.published_at, lang) })}</p>
    </article>
  );
}
