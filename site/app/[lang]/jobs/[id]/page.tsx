import type { Metadata } from "next";
import Link from "@/components/AppLink";
import { notFound } from "next/navigation";
import { ArrowUpRight, BadgeCheck, BedDouble, Bell, ChevronLeft, Stamp } from "lucide-react";
import { Notice } from "@/components/Bits";
import { JobCard } from "@/components/Cards";
import { KindBadge } from "@/components/Cover";
import { ShareButton } from "@/components/ShareButton";
import { api, apiOrNull, type Job, type Meta } from "@/lib/api";
import { formatDate, relative } from "@/lib/format";
import { dict, fill, isLocale, label, pick, pickLang } from "@/lib/i18n";
import { alternatesFor, siteUrl } from "@/lib/site";

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
  const name = job.image_only ? dict(lang).jobs.flyerTitle : job.title;
  return {
    title: job.company ? `${name} · ${job.company}` : name,
    description: pick(job.summary, lang).slice(0, 200),
    alternates: alternatesFor(lang, `/jobs/${job.id}`),
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
  const title = job.image_only ? d.jobs.flyerTitle : job.title;

  const place = [job.area, label(meta.labels.emirates[job.emirate], lang)].filter(Boolean).join(", ");
  const summary = pick(job.summary, lang);
  const source = job.source ?? "";
  const emirate = job.emirate !== "unknown" ? label(meta.labels.emirates[job.emirate], "en") : "";

  // Search engines read an open job as a posting: what, who, where and since when. You apply on the source's site.
  const jsonLd = job.open && !job.image_only
    ? {
        "@context": "https://schema.org",
        "@type": "JobPosting",
        title: job.title,
        description: summary || undefined,
        datePosted: job.published_at ?? undefined,
        hiringOrganization: job.company ? { "@type": "Organization", name: job.company } : undefined,
        jobLocation: { "@type": "Place", address: { "@type": "PostalAddress", addressRegion: emirate || undefined, addressCountry: "AE" } },
        directApply: Boolean(job.direct),
      }
    : null;

  return (
    <article className="mx-auto max-w-page px-4 pt-6 md:pt-10 lg:px-8">
      {jsonLd && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />}
      <Link href={`/${lang}/jobs`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {d.jobs.back}
      </Link>

      {/*
        A wide screen: the job on the left, the decision on the right, in view as the page scrolls.
        A phone: one column, with the action held at the foot of the screen. The column is a plain
        block there (the side column dissolves into it) because a sticky bar can only travel inside
        its parent, and the bar has to be reachable from the top of the page.
      */}
      <div className="lg:mt-6 lg:grid lg:grid-cols-[minmax(0,1fr)_23rem] lg:items-start lg:gap-12 xl:gap-16">
        <div className="min-w-0">
          <header className="mt-5 flex items-start gap-4 lg:mt-0">
            <KindBadge kind="trade" category={job.category} className="size-16 rounded-[1.25rem]" iconClass="size-7" />
            <div className="min-w-0">
              <h1 className="display text-[2rem] md:text-[2.75rem]">{title}</h1>
              <p className="mt-2 text-muted">
                {job.business ? (
                  <Link href={`/${lang}/directory/${job.business.slug}`} className="inline-flex items-center gap-1 font-semibold text-primary">
                    {job.business.name}{job.business.verified && <BadgeCheck className="size-4" aria-label={d.common.verified} />}
                  </Link>
                ) : job.company}
                {place && `${job.business || job.company ? " · " : ""}${place}`}
              </p>
              {job.direct && <p className="mt-1 text-sm font-bold text-success">{d.jobs.direct}</p>}
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

          {job.photos && job.photos.length > 0 && (
            <section className="mt-8 flex max-w-xl flex-col gap-3">
              {job.photos.map((photo, i) => (
                <a key={photo.url} href={photo.url} target="_blank" rel="noopener noreferrer" className="press block overflow-hidden rounded-2xl border border-line">
                  <img src={photo.preview} alt={title} width={photo.width} height={photo.height} loading={i === 0 ? "eager" : "lazy"} decoding="async" className="h-auto w-full bg-line" />
                </a>
              ))}
            </section>
          )}

          <section className="mt-8">
            <h2 className="eyebrow">{d.jobs.summary}</h2>
            <p className="prose-plain mt-2 max-w-[70ch] text-[1.0625rem] leading-relaxed" lang={summary ? pickLang(job.summary, lang) : lang}>{summary || (job.image_only ? d.jobs.flyerNote : d.jobs.noSummary)}</p>
          </section>

          {job.warning && job.warning.length > 0 && (
            <div className="mt-6"><Notice tone="danger" title={d.jobs.warningTitle}>{d.jobs.warningText}</Notice></div>
          )}
        </div>

        <div className="contents lg:sticky lg:top-[calc(var(--header-space)+1.5rem)] lg:flex lg:flex-col lg:gap-4">
          <div className="mt-6 lg:order-3 lg:mt-0"><Notice tone="warning" title={d.jobs.safetyTitle}>{d.jobs.safetyText}</Notice></div>

          {/* The decision point of the page: one clear action, and what it does. */}
          <div className="phone-action-bar sticky bottom-[calc(var(--tabbar-space)+0.75rem)] z-30 mt-8 md:static lg:order-1 lg:mt-0">
            <div className="material flex items-center gap-3 rounded-2xl border border-line p-3 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none lg:flex-col lg:items-stretch lg:rounded-[var(--radius-card)] lg:border lg:bg-elevated lg:p-4">
              {job.open && job.apply_url ? (
                // May shrink beside Share. On a phone the site name would cut off the word that matters, so it says only what the button does; the note under it names the site.
                <a href={job.apply_url} target="_blank" rel="noopener noreferrer nofollow" className="btn btn-primary min-w-0 flex-1 md:flex-none">
                  {job.apply_via ? (
                    <span className="truncate">{job.apply_via === "whatsapp" ? d.jobs.applyWhatsapp : d.jobs.applyTelegram}</span>
                  ) : (
                    <>
                      <span className="truncate sm:hidden">{d.jobs.applyGeneric}</span>
                      <span className="hidden truncate sm:inline">{source ? fill(d.jobs.apply, { source }) : d.jobs.applyGeneric}</span>
                    </>
                  )}
                  <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" />
                </a>
              ) : null}
              <ShareButton title={job.title} url={siteUrl(`${lang}/jobs/${job.id}`)} labels={{ share: d.common.share, copied: d.common.copied }} />
            </div>
          </div>
          {job.open && job.apply_url && <p className="mt-2 text-xs text-muted lg:order-2 lg:mt-0">{job.direct ? d.jobs.directNote : <>{d.jobs.applyNote}{source && ` ${fill(d.jobs.via, { source })}`}</>}</p>}

          {meta.bot.link && (
            <a href={`${meta.bot.link}?start=jobs`} target="_blank" rel="noopener noreferrer" className="night tile tile-link spot mt-10 flex items-center gap-4 p-5 md:p-6 lg:order-4 lg:mt-0">
              <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-accent text-[#191919]">
                <Bell className="size-6" aria-hidden="true" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="headline block text-lg md:text-xl">{d.jobs.alertsTitle}</span>
                <span className="mt-0.5 block text-sm muted-on-night">{d.jobs.alertsText}</span>
              </span>
              <ArrowUpRight className="tile-arrow size-5 shrink-0 text-accent" aria-hidden="true" />
            </a>
          )}
        </div>
      </div>

      {similar.length > 0 && (
        <section className="mt-12 lg:mt-16">
          <h2 className="headline text-[1.75rem]">{d.jobs.similar}</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {similar.map((j) => <JobCard key={j.id} job={j} lang={lang} meta={meta} compact />)}
          </div>
        </section>
      )}

      <p className="mt-8 text-xs text-faint">{job.published_at && fill(d.jobs.posted, { when: relative(job.published_at, lang) })}</p>
    </article>
  );
}
