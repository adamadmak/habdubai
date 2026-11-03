import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight, Bell, Building2, MessagesSquare, Package, ShieldCheck } from "lucide-react";
import { Section } from "@/components/Bits";
import { BusinessCard, EventCard, JobCard, NewsCard } from "@/components/Cards";
import { BusinessIcon } from "@/components/CategoryIcon";
import { HomeSearch } from "@/components/HomeSearch";
import { Tibeb } from "@/components/Logo";
import { api, type Home } from "@/lib/api";
import { number } from "@/lib/format";
import { dict, isLocale, label } from "@/lib/i18n";

const JOIN_ICONS = [Bell, MessagesSquare, Package, ShieldCheck];

export default async function HomePage({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const home = await api<Home>("/public/home");
  const { meta, stats } = home;

  const statLine = [
    stats.members > 0 && `${number(stats.members, lang)} ${d.home.stats.members}`,
    stats.jobs > 0 && `${number(stats.jobs, lang)} ${d.home.stats.jobs}`,
    stats.businesses > 0 && `${number(stats.businesses, lang)} ${d.home.stats.businesses}`,
  ].filter(Boolean) as string[];

  const now = [home.jobs[0] && "job", home.events[0] && "event", home.businesses[0] && "business"].filter(Boolean);

  return (
    <>
      {/* The hero is the community's own content, not a picture of it. */}
      <section className="mx-auto grid max-w-6xl gap-10 px-4 pt-10 md:grid-cols-[1.1fr_1fr] md:items-center md:pt-16">
        <div className="settle">
          <p className="eyebrow flex items-center gap-2">
            <span className="tibeb-thin inline-block w-8 rounded-sm" aria-hidden="true" />
            {d.home.hello}
          </p>
          <h1 className="display mt-4 text-[2.25rem] md:text-[3.375rem]">{d.home.title}</h1>
          <p className="mt-4 max-w-xl text-[1.0625rem] text-muted">{d.home.lead}</p>
          <HomeSearch lang={lang} placeholder={d.home.searchPlaceholder} scopes={d.home.scopes} searchLabel={d.common.search} />
          {statLine.length > 0 && <p className="mt-5 text-sm text-muted tabular">{statLine.join("   ·   ")}</p>}
        </div>

        {now.length > 0 && (
          <div className="settle settle-2 hidden flex-col gap-3 md:flex" aria-label={d.home.jobsTitle}>
            {home.jobs[0] && <JobCard job={home.jobs[0]} lang={lang} meta={meta} />}
            {home.events[0] && <EventCard item={home.events[0]} lang={lang} />}
            {home.businesses[0] && (
              <div className="max-w-sm self-end">
                <BusinessCard business={home.businesses[0]} lang={lang} meta={meta} />
              </div>
            )}
          </div>
        )}
      </section>

      <Tibeb className="mt-12" />

      {home.jobs.length > 0 && (
        <Section title={d.home.jobsTitle} lead={d.home.jobsLead} more={{ href: `/${lang}/jobs`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-4">
            {home.jobs.slice(0, 8).map((job) => <JobCard key={job.id} job={job} lang={lang} meta={meta} compact />)}
          </div>
        </Section>
      )}

      <Section title={d.home.kindsTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-7">
          {home.categories.map((c) => (
            <Link
              key={c.key}
              href={`/${lang}/directory?category=${c.key}`}
              className="card lift flex items-center gap-3 p-3 lg:flex-col lg:items-start lg:gap-4 lg:p-4"
            >
              <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                <BusinessIcon category={c.key} />
              </span>
              <span className="w-full min-w-0">
                <span className="line-clamp-2 text-sm font-semibold leading-snug" title={label(c.name, lang)}>{label(c.name, lang)}</span>
                <span className="block text-xs text-muted tabular">{number(c.count, lang)}</span>
              </span>
            </Link>
          ))}
        </div>
      </Section>

      {home.businesses.length > 0 && (
        <Section title={d.home.placesTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-3 md:overflow-visible md:px-0 lg:grid-cols-4">
            {home.businesses.map((b) => <BusinessCard key={b.id} business={b} lang={lang} meta={meta} />)}
          </div>
        </Section>
      )}

      {(home.events.length > 0 || home.announcements.length > 0) && (
        <div className="mx-auto grid max-w-6xl gap-x-8 md:grid-cols-[1fr_1.6fr]">
          {home.events.length > 0 && (
            <Section title={d.home.eventsTitle} more={{ href: `/${lang}/news?events=1`, label: d.common.seeAll }}>
              <div className="flex flex-col gap-3">
                {home.events.map((e) => <EventCard key={e.id} item={e} lang={lang} />)}
              </div>
            </Section>
          )}
          {home.announcements.length > 0 && (
            <Section title={d.home.newsTitle} more={{ href: `/${lang}/news`, label: d.common.seeAll }}>
              <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-2 md:overflow-visible md:px-0">
                {home.announcements.slice(0, 4).map((a) => <NewsCard key={a.id} item={a} lang={lang} />)}
              </div>
            </Section>
          )}
        </div>
      )}

      {/* For the owners: plain, generous, one action. */}
      <section className="mx-auto mt-16 max-w-6xl px-4">
        <div className="card relative overflow-hidden bg-primary-soft p-6 md:flex md:items-center md:justify-between md:gap-10 md:p-10">
          <div className="max-w-xl">
            <Building2 className="size-8 text-primary" aria-hidden="true" />
            <h2 className="title mt-4 text-2xl md:text-[1.75rem]">{d.home.ownerTitle}</h2>
            <p className="mt-2 text-muted">{d.home.ownerText}</p>
          </div>
          <Link href={`/${lang}/business`} className="btn btn-primary mt-6 shrink-0 md:mt-0">
            {d.home.ownerCta} <ArrowRight className="size-4" aria-hidden="true" />
          </Link>
          <div className="tibeb-thin absolute inset-x-0 bottom-0" aria-hidden="true" />
        </div>
      </section>

      <Section title={d.home.joinTitle} lead={d.home.joinLead}>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {d.home.joinItems.map((item, i) => {
            const Icon = JOIN_ICONS[i] ?? Bell;
            return (
              <div key={item.title} className="card p-5">
                <Icon className="size-6 text-primary" aria-hidden="true" strokeWidth={1.8} />
                <h3 className="title mt-3 text-[1.0625rem]">{item.title}</h3>
                <p className="mt-1.5 text-sm text-muted">{item.text}</p>
              </div>
            );
          })}
        </div>
        {meta.bot.link && (
          <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-primary mt-6">
            {d.home.joinCta} <ArrowRight className="size-4" aria-hidden="true" />
          </a>
        )}
      </Section>
    </>
  );
}
