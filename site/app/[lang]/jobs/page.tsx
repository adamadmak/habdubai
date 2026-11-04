import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Bell } from "lucide-react";
import { Empty, PageHead, Pager } from "@/components/Bits";
import { countLabel, JobCard } from "@/components/Cards";
import { Chip, ChipRow, hrefWith, SearchBox } from "@/components/Filters";
import { api, query, type Job, type Meta, type Page } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { dict, fill, isLocale, label } from "@/lib/i18n";
import { alternatesFor } from "@/lib/site";

type JobsPage = Page<Job> & { counts: { categories: Record<string, number>; emirates: Record<string, number> } };
type Search = { q?: string; category?: string; emirate?: string; page?: string };

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.jobs.title, description: d.jobs.lead, alternates: alternatesFor(lang, `/jobs`) };
}

export default async function JobsIndex({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<Search> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const sp = await searchParams;
  const current = { q: sp.q?.slice(0, 80), category: sp.category, emirate: sp.emirate };
  const page = Math.max(1, Number(sp.page) || 1);
  // A search, a filter or a later page is asked for as this visitor, uncached, so
  // searching spends the visitor's own allowance at the API, not the whole site's.
  const searched = Object.values(current).some(Boolean) || page > 1;

  const [jobs, meta] = await Promise.all([
    api<JobsPage>(`/public/jobs${query({ ...current, page })}`, searched ? { client: await currentClient() } : {}),
    api<Meta>("/public/meta", { revalidate: 300 }),
  ]);

  const base = `/${lang}/jobs`;
  const categories = Object.entries(jobs.counts.categories).sort((a, b) => b[1] - a[1]);
  const emirates = Object.entries(jobs.counts.emirates).filter(([key]) => key !== "unknown").sort((a, b) => b[1] - a[1]);

  return (
    <>
      <PageHead title={d.jobs.title} lead={d.jobs.lead} word="ሥራ" />

      <div className="mx-auto mt-6 flex max-w-page flex-col gap-3 px-4 lg:px-8">
        <SearchBox action={base} q={current.q} placeholder={d.jobs.searchPlaceholder} keep={{ category: current.category, emirate: current.emirate }} labels={{ search: d.common.search, clear: d.common.clear }} />
        <ChipRow label={d.jobs.facts.kind}>
          <Chip href={hrefWith(base, current, { category: undefined })} active={!current.category}>{d.jobs.allKinds}</Chip>
          {categories.map(([key, n]) => (
            <Chip key={key} href={hrefWith(base, current, { category: key })} active={current.category === key}>
              {label(meta.labels.trades[key], lang)} <span className="count">{n}</span>
            </Chip>
          ))}
        </ChipRow>
        {emirates.length > 1 && (
          <ChipRow label={d.jobs.facts.where}>
            <Chip href={hrefWith(base, current, { emirate: undefined })} active={!current.emirate}>{d.jobs.allPlaces}</Chip>
            {emirates.map(([key, n]) => (
              <Chip key={key} href={hrefWith(base, current, { emirate: key })} active={current.emirate === key}>
                {label(meta.labels.emirates[key], lang)} <span className="count">{n}</span>
              </Chip>
            ))}
          </ChipRow>
        )}
      </div>

      <div className="mx-auto mt-6 max-w-page px-4 lg:px-8">
        <h2 className="sr-only">{d.common.results}</h2>
        <p className="mb-3 text-sm text-muted" aria-live="polite">{countLabel(jobs.meta.total, d.jobs.countOne, d.jobs.count, lang)}</p>
        {jobs.data.length === 0 ? (
          <Empty>{d.jobs.empty}</Empty>
        ) : (
          <div className="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            {jobs.data.map((job) => <JobCard key={job.id} job={job} lang={lang} meta={meta} />)}
          </div>
        )}
        <Pager
          page={jobs.meta.page}
          last={jobs.meta.last_page}
          href={(p) => `${hrefWith(base, current, {})}${Object.values(current).some(Boolean) ? "&" : "?"}page=${p}`}
          labels={{ previous: d.common.previous, next: d.common.next, pageOf: fill(d.common.pageOf, { page: jobs.meta.page, pages: jobs.meta.last_page }) }}
        />

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
      </div>
    </>
  );
}
