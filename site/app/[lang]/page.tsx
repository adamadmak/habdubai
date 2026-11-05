import type { CSSProperties } from "react";
import type { Metadata } from "next";
import Link from "@/components/AppLink";
import { notFound } from "next/navigation";
import { ArrowRight, ArrowUpRight, Briefcase, ChevronRight, Handshake, Luggage, MessageCircle, Send, ShieldCheck, Store, Users, type LucideIcon } from "lucide-react";
import { Section, Steps } from "@/components/Bits";
import { BusinessCard, EventCard, firstLine, JobCard } from "@/components/Cards";
import { BusinessIcon } from "@/components/CategoryIcon";
import { Clocks } from "@/components/Clocks";
import { KindBadge, placeTint } from "@/components/Cover";
import { HomeSearch } from "@/components/HomeSearch";
import { NewsMarquee } from "@/components/NewsMarquee";
import { Night } from "@/components/Dawn";
import { GoodsTeaser } from "@/components/goods/GoodsBits";
import { api, type GoodsBoard, type Home } from "@/lib/api";
import { dayAndMonth, ethiopianDate, number, relative, weekday } from "@/lib/format";
import { dict, isLocale, label, pick, pickLang } from "@/lib/i18n";
import { alternatesFor } from "@/lib/site";

// Built on the first visit and kept for a minute, instead of being rebuilt for every visitor. No build ever needs the API: the list is empty.
export function generateStaticParams() {
  return [];
}

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  return isLocale(lang) ? { alternates: alternatesFor(lang) } : {};
}

const tint = (color: string) => ({ "--tint": color }) as CSSProperties;

export default async function HomePage({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  // The goods board's numbers are a bonus: if they cannot be read, the front page still renders.
  const [home, goods] = await Promise.all([api<Home>("/public/home"), api<GoodsBoard>("/public/goods").catch(() => null)]);
  const { meta, stats } = home;
  const job = home.jobs[0];
  const event = home.events[0];
  const trips = goods?.stats.trips ?? 0;
  const deals = goods?.stats.deals ?? 0;

  // Only real numbers: a figure that is still zero is left out, and with none left the strip is too.
  const statItems = ([
    { n: stats.members, label: stats.members === 1 ? d.home.stats.memberOne : d.home.stats.members, icon: Users, color: "#d40511" },
    { n: stats.jobs, label: stats.jobs === 1 ? d.home.stats.jobOne : d.home.stats.jobs, icon: Briefcase, color: "#266ef3" },
    { n: stats.businesses, label: stats.businesses === 1 ? d.home.stats.businessOne : d.home.stats.businesses, icon: Store, color: "#c2570a" },
    { n: trips, label: trips === 1 ? d.goods.stats.tripOne : d.goods.stats.trips, icon: Luggage, color: "#15803d" },
    { n: deals, label: deals === 1 ? d.goods.stats.dealOne : d.goods.stats.deals, icon: Handshake, color: "#b98900" },
  ] satisfies { n: number; label: string; icon: LucideIcon; color: string }[]).filter((s) => s.n > 0);

  // The four things this site is for.
  const audiences: { href: string; icon: LucideIcon; color: string; kicker: string; title: string; text: string; cta: string }[] = [
    { href: `/${lang}/jobs`, icon: Briefcase, color: "#d40511", kicker: d.nav.jobs, title: d.home.jobsTitle, text: d.home.bento.jobsText, cta: d.common.seeAll },
    { href: `/${lang}/directory`, icon: Store, color: "#c2570a", kicker: d.nav.directory, title: d.home.placesTitle, text: d.home.bento.placesText, cta: d.common.seeAll },
    { href: `/${lang}/goods`, icon: Luggage, color: "#266ef3", kicker: d.nav.goods, title: d.goods.title, text: d.goods.teaserLead, cta: d.goods.seeBoard },
    { href: `/${lang}/business`, icon: Handshake, color: "#15803d", kicker: d.footer.owners, title: d.home.ownerTitle, text: d.home.bento.ownerText, cta: d.home.ownerCta },
  ];

  return (
    <>
      {/* ---- The hero: the heading, the two ways in and the search, beside the live board. ---- */}
      <Night>
        {/* grid-cols-1 on phones: one column that never grows past the screen, so a long word or label truncates instead of widening the hero. */}
        <div className="mx-auto grid max-w-page grid-cols-1 gap-10 px-4 pb-8 pt-8 md:grid-cols-[1.15fr_0.85fr] md:items-center md:pb-16 md:pt-20 lg:gap-16 lg:px-8">
          <div className="settle min-w-0">
            <p className="kicker">
              <span lang="am">ሰላም</span>
              <span aria-hidden="true">·</span>
              {d.home.hello}
            </p>
            <h1 className="display mt-5 text-[2.5rem] md:text-[4.25rem] lg:text-[5.5rem]">
              {d.home.heroTitle} <span className="gild">{d.home.heroAccent}</span>
            </h1>
            <p className="mt-6 max-w-xl text-[1.0625rem] muted-on-night md:text-lg">{d.home.lead}</p>
            <div className="mt-7 flex flex-wrap gap-3">
              {meta.bot.link && (
                <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-gold min-h-12 px-6 text-base">
                  <Send className="size-4.5" aria-hidden="true" /> {d.home.joinCta}
                </a>
              )}
              <Link href={`/${lang}/directory`} className="btn btn-glass min-h-12 px-6 text-base">
                {d.nav.directory} <ChevronRight className="size-4.5" aria-hidden="true" />
              </Link>
            </div>
            <HomeSearch lang={lang} placeholder={d.home.searchPlaceholder} scopes={d.home.scopes} scopeLabel={d.home.searchIn} searchLabel={d.common.search} />
          </div>

          {/* The live board: the two clocks, the newest job, the next event. */}
          <div className="settle settle-2 flex min-w-0 flex-col gap-3" role="group" aria-label={d.home.live}>
            {/* A phone's first screen is for finding things. */}
            <div className="hidden md:block"><Clocks lang={lang} labels={d.clock} /></div>
            {job && (
              <Link href={`/${lang}/jobs/${job.id}`} className="glass-white lift press hidden items-center gap-3.5 rounded-[1.5rem] p-3.5 md:flex">
                <KindBadge kind="trade" category={job.category} className="size-12 rounded-2xl" />
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.6875rem] font-extrabold uppercase tracking-wide text-gild">{d.home.jobsTitle}</span>
                  <span className="block truncate font-bold">{job.title}</span>
                  <span className="block truncate text-xs muted-on-night">
                    {[job.salary, label(meta.labels.emirates[job.emirate], lang), relative(job.published_at, lang)].filter(Boolean).join(" · ")}
                  </span>
                </span>
                <ArrowUpRight className="size-4 shrink-0 text-faint" aria-hidden="true" />
              </Link>
            )}
            {event?.event_date && (
              <Link href={`/${lang}/news/${event.id}`} className="glass-white lift press hidden items-center gap-3.5 rounded-[1.5rem] p-3.5 md:flex">
                <span className="grid w-12 shrink-0 overflow-hidden rounded-xl bg-elevated text-center text-fg ring-1 ring-line">
                  <span className="bg-primary py-0.5 text-[0.625rem] font-bold text-white">{dayAndMonth(event.event_date, lang).month}</span>
                  <span className="headline py-0.5 text-lg tabular">{dayAndMonth(event.event_date, lang).day}</span>
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.6875rem] font-extrabold uppercase tracking-wide text-gild">{d.home.eventsTitle}</span>
                  <span className="block truncate font-bold" lang={pickLang(event.text, lang)}>{firstLine(pick(event.text, lang), 80)}</span>
                  <span className="block truncate text-xs muted-on-night">{weekday(event.event_date, lang)} · {ethiopianDate(event.event_date, lang)}</span>
                </span>
                <ArrowUpRight className="size-4 shrink-0 text-faint" aria-hidden="true" />
              </Link>
            )}
          </div>
        </div>
      </Night>

      {/* ---- The community in numbers, the ones that are not zero. ---- */}
      {statItems.length > 0 && (
        <section className="mx-auto mt-8 max-w-page px-4 lg:px-8 md:mt-12" aria-label={d.brand}>
          <dl className="card flex flex-wrap divide-line p-2 md:p-3">
            {statItems.map((s) => {
              const Icon = s.icon;
              return (
                <div key={s.label} style={tint(s.color)} className="flex min-w-[9.5rem] flex-1 items-center gap-3 p-3">
                  <span aria-hidden="true" className="tint-soft grid size-11 shrink-0 place-items-center rounded-full">
                    <Icon className="size-5" />
                  </span>
                  <div className="flex min-w-0 flex-col-reverse">
                    <dt className="truncate text-[0.6875rem] font-bold uppercase tracking-wide text-faint">{s.label}</dt>
                    <dd className="headline text-[1.375rem] tabular">{number(s.n, lang)}</dd>
                  </div>
                </div>
              );
            })}
          </dl>
        </section>
      )}

      {/* ---- Who this is for: four doors. ---- */}
      <Section kicker={d.home.forYou.kicker} title={d.home.forYou.title} lead={d.home.forYou.lead}>
        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
          {audiences.map((a) => {
            const Icon = a.icon;
            return (
              <Link key={a.href} href={a.href} style={tint(a.color)} className="card lift group flex overflow-hidden sm:flex-col">
                <span aria-hidden="true" className="tint-soft relative hidden h-32 sm:block md:h-36">
                  <Icon className="absolute -right-3 -top-3 size-32 opacity-15" strokeWidth={1.25} />
                  <span className="tint-solid absolute -bottom-6 left-5 grid size-12 place-items-center rounded-full ring-4 ring-[var(--color-elevated)]">
                    <Icon className="size-5" />
                  </span>
                </span>
                {/* A phone: one compact row each, so all four are on screen at once. */}
                <span aria-hidden="true" className="tint-solid m-4 mr-0 grid size-11 shrink-0 place-items-center rounded-full sm:hidden">
                  <Icon className="size-5" />
                </span>
                <span className="flex min-w-0 flex-1 flex-col p-4 sm:px-5 sm:pb-5 sm:pt-9">
                  <span className="tint-ink text-xs font-extrabold uppercase tracking-wide">{a.kicker}</span>
                  <span className="headline mt-1 text-[1.125rem] sm:mt-1.5 sm:text-[1.3125rem]">{a.title}</span>
                  <span className="mt-1 line-clamp-2 text-sm text-muted sm:mt-2 sm:line-clamp-none">{a.text}</span>
                  <span className="tint-ink mt-auto hidden items-center gap-1 pt-4 text-sm font-bold sm:flex">
                    {a.cta} <ChevronRight className="tile-arrow size-4" aria-hidden="true" />
                  </span>
                </span>
              </Link>
            );
          })}
        </div>
      </Section>

      {/* ---- Kinds of place, each a pale tile in its own colour. ---- */}
      <Section tone="cream" kicker={d.nav.directory} title={d.home.kindsTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
        <div className="row-scroll row-scroll-tiles sm:mx-0 sm:grid-flow-row sm:grid-cols-3 sm:gap-3 sm:overflow-visible sm:px-0 lg:grid-cols-5 xl:grid-cols-7">
          {home.categories.map((c) => (
            <Link key={c.key} href={`/${lang}/directory?category=${c.key}`} style={placeTint(c.key)} className="kind-tile lift min-h-[8.5rem] gap-4 p-4">
              <span className="kind-tile-icon grid size-11 place-items-center rounded-full bg-elevated shadow-sm">
                <BusinessIcon category={c.key} className="size-5" />
              </span>
              <span className="mt-auto block min-w-0">
                <span className="line-clamp-2 text-sm font-bold leading-snug" title={label(c.name, lang)}>{label(c.name, lang)}</span>
                {c.count > 0 && <span className="block text-xs text-muted tabular">{number(c.count, lang)}</span>}
              </span>
            </Link>
          ))}
        </div>
      </Section>

      {home.jobs.length > 0 && (
        <Section kicker={d.nav.jobs} title={d.home.jobsTitle} lead={d.home.jobsLead} more={{ href: `/${lang}/jobs`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-3">
            {home.jobs.slice(0, 6).map((j) => <JobCard key={j.id} job={j} lang={lang} meta={meta} compact />)}
          </div>
        </Section>
      )}

      {home.businesses.length > 0 && (
        <Section kicker={d.nav.directory} title={d.home.placesTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-3 md:overflow-visible md:px-0 lg:grid-cols-4">
            {home.businesses.map((b) => <BusinessCard key={b.id} business={b} lang={lang} meta={meta} />)}
          </div>
        </Section>
      )}

      {/* ---- The goods board: spare kilos between the two homes. ---- */}
      <GoodsTeaser lang={lang} d={d} board={goods} tone="iced" />

      {home.events.length > 0 && (
        <Section title={d.home.eventsTitle} more={{ href: `/${lang}/news?events=1`, label: d.common.seeAll }}>
          <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            {home.events.map((e) => <EventCard key={e.id} item={e} lang={lang} />)}
          </div>
        </Section>
      )}

      {/* ---- News runs the full width of the page, so it never sits in half of it. ---- */}
      {home.announcements.length > 0 && (
        <>
          <Section title={d.home.newsTitle} more={{ href: `/${lang}/news`, label: d.common.seeAll }}>{null}</Section>
          <NewsMarquee items={home.announcements} lang={lang} />
        </>
      )}

      {/* ---- How joining works, in three steps. ---- */}
      <Section tone="mint" kicker={d.home.how.kicker} title={d.home.how.title}>
        <Steps items={d.home.how.items} stepLabel={d.common.step} icons={[Send, ShieldCheck, MessageCircle]} />
      </Section>

      {/* ---- Join: everything else happens in the bot. Runs edge to edge into the footer. ---- */}
      <section className="band-red -mb-20 mt-16 md:-mb-28 md:mt-24">
        <div className="reveal mx-auto grid max-w-page gap-10 px-4 py-16 md:py-24 lg:grid-cols-[1fr_auto] lg:items-center lg:px-8">
          <div>
            <h2 className="display max-w-3xl text-[2.25rem] md:text-[3.5rem]">{d.home.joinTitle}</h2>
            <p className="mt-4 max-w-2xl muted-on-night md:text-lg">{d.home.joinLead}</p>
            {meta.bot.link && (
              <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-white mt-7 min-h-12 px-6 text-base">
                <Send className="size-4.5" aria-hidden="true" /> {d.home.joinCta} <ArrowRight className="size-4.5" aria-hidden="true" />
              </a>
            )}
          </div>
          {meta.bot.link && (
            <figure className="hidden flex-col items-center gap-3 lg:flex">
              <div className="rounded-[1.5rem] bg-white p-4 shadow-[0_24px_60px_-24px_rgb(0_0_0/0.6)]">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src="/api/qr" alt="" width={160} height={160} loading="lazy" className="size-40" />
              </div>
              <figcaption className="max-w-[12rem] text-center text-xs muted-on-night">{d.home.scanToJoin}</figcaption>
            </figure>
          )}
        </div>
      </section>
    </>
  );
}
