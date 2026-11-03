import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight, BedDouble, Bell, ChevronLeft, Stamp } from "lucide-react";
import { Notice } from "@/components/Bits";
import { JobCard } from "@/components/Cards";
import { KindBadge } from "@/components/Cover";
import { ShareButton } from "@/components/ShareButton";
import { api, apiOrNull, type Job, type Meta } from "@/lib/api";
import { formatDate, relative } from "@/lib/format";
import { dict, fill, isLocale, label, pick, pickLang } from "@/lib/i18n";
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
    description: pick(job.summary, lang).slice(0, 200),
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
  const summary = pick(job.summary, lang);
  const source = job.source ?? "";
  const emirate = job.emirate !== "unknown" ? label(meta.labels.emirates[job.emirate], "en") : "";

  // Search engines read an open job as a posting: what, who, where and since when. You apply on the source's site.
  const jsonLd = job.open
    ? {
        "@context": "https://schema.org",
        "@type": "JobPosting",
        title: job.title,
        description: summary || undefined,
        datePosted: job.published_at ?? undefined,
        hiringOrganization: job.company ? { "@type": "Organization", name: job.company } : undefined,
        jobLocation: { "@type": "Place", address: { "@type": "PostalAddress", addressRegion: emirate || undefined, addressCountry: "AE" } },
        directApply: false,
      }
    : null;

  return (
    <article className="mx-auto max-w-3xl px-4 pt-6 md:pt-10">
      {jsonLd && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />}
      <Link href={`/${lang}/jobs`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {d.jobs.back}
      </Link>

      <header className="mt-5 flex items-start gap-4">
        <KindBadge kind="trade" category={job.category} className="size-16 rounded-[1.25rem]" iconClass="size-7" />
        <div className="min-w-0">
          <h1 className="display text-[2rem] md:text-[2.75rem]">{job.title}</h1>
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
          <div key={term} className="bg-elevated p-4">
            <dt className="text-xs text-muted">{term}</dt>
            <dd className={`mt-1 font-semibold ${term === d.jobs.facts.salary && job.salary ? "headline text-lg text-success" : ""}`}>{value}</dd>
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
        <p className="prose-plain mt-2 text-[1.0625rem] leading-relaxed" lang={summary ? pickLang(job.summary, lang) : lang}>{summary || d.jobs.noSummary}</p>
      </section>

      {job.warning && job.warning.length > 0 && (
        <div className="mt-6"><Notice tone="danger" title={d.jobs.warningTitle}>{d.jobs.warningText}</Notice></div>
      )}

      <div className="mt-6"><Notice tone="warning" title={d.jobs.safetyTitle}>{d.jobs.safetyText}</Notice></div>

      {/* The decision point of the page: one clear action, and what it does. */}
      <div className="sticky bottom-[calc(var(--tabbar-space)+0.75rem)] z-30 mt-8 md:static">
        <div className="material flex items-center gap-3 rounded-2xl border border-line p-3 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none">
          {job.open && job.apply_url ? (
            // May shrink beside Share: a long site name, or a language that words it longer, ends in an ellipsis instead of widening the page.
            <a href={job.apply_url} target="_blank" rel="noopener noreferrer nofollow" className="btn btn-primary min-w-0 flex-1 md:flex-none">
              <span className="truncate">{source ? fill(d.jobs.apply, { source }) : d.jobs.applyGeneric}</span>
              <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" />
            </a>
          ) : null}
          <ShareButton title={job.title} url={siteUrl(`${lang}/jobs/${job.id}`)} labels={{ share: d.common.share, copied: d.common.copied }} />
        </div>
        {job.open && job.apply_url && <p className="mt-2 hidden text-xs text-muted md:block">{d.jobs.applyNote}{source && ` ${fill(d.jobs.via, { source })}`}</p>}
      </div>

      {meta.bot.link && (
        <a href={`${meta.bot.link}?start=jobs`} target="_blank" rel="noopener noreferrer" className="night tile tile-link spot mt-10 flex items-center gap-4 p-5 md:p-6">
          <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-accent text-[#10142b]">
            <Bell className="size-6" aria-hidden="true" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="headline block text-lg md:text-xl">{d.jobs.alertsTitle}</span>
            <span className="mt-0.5 block text-sm muted-on-night">{d.jobs.alertsText}</span>
          </span>
          <ArrowUpRight className="tile-arrow size-5 shrink-0 text-accent" aria-hidden="true" />
        </a>
      )}

      {similar.length > 0 && (
        <section className="mt-12">
          <h2 className="headline text-[1.75rem]">{d.jobs.similar}</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {similar.map((j) => <JobCard key={j.id} job={j} lang={lang} meta={meta} compact />)}
          </div>
        </section>
      )}

      <p className="mt-8 text-xs text-faint">{job.published_at && fill(d.jobs.posted, { when: relative(job.published_at, lang) })}</p>
    </article>
  );
}
