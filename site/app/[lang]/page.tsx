import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import QRCode from "qrcode";
import { ArrowRight, ArrowUpRight, Bell, CalendarDays, Newspaper, Plus, Send, ShieldCheck, Store } from "lucide-react";
import { GEEZ, Section } from "@/components/Bits";
import { BusinessCard, EventCard, firstLine, JobCard, ListingMock, NewsCard } from "@/components/Cards";
import { Clocks } from "@/components/Clocks";
import { KindBadge } from "@/components/Cover";
import { HomeSearch } from "@/components/HomeSearch";
import { Night } from "@/components/Dawn";
import { GoodsTeaser } from "@/components/goods/GoodsBits";
import { api, type GoodsBoard, type Home } from "@/lib/api";
import { dayAndMonth, ethiopianDate, number, relative, weekday } from "@/lib/format";
import { dict, isLocale, label, pick, pickLang } from "@/lib/i18n";
import { alternatesFor } from "@/lib/site";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  return isLocale(lang) ? { alternates: alternatesFor(lang) } : {};
}

export default async function HomePage({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  // The goods board's numbers are a bonus: if they cannot be read, the front page still renders.
  const [home, goods] = await Promise.all([api<Home>("/public/home"), api<GoodsBoard>("/public/goods").catch(() => null)]);
  const { meta, stats } = home;
  const job = home.jobs[0];
  const event = home.events[0];

  // The big blue tile, whether it links to the jobs or (before the first one) holds the alerts link.
  const jobsTile =
    "tile spot reveal col-span-2 flex min-h-[22rem] flex-col bg-[linear-gradient(145deg,#1b4ddb_0%,#2b5cff_45%,#3a2fb8_100%)] p-6 text-white md:p-8 lg:row-span-2";

  const statItems = [
    stats.members > 0 && { n: stats.members, label: d.home.stats.members },
    stats.jobs > 0 && { n: stats.jobs, label: d.home.stats.jobs },
    stats.businesses > 0 && { n: stats.businesses, label: d.home.stats.businesses },
  ].filter(Boolean) as { n: number; label: string }[];

  // The bot's address as a code to scan from a computer; phones get the button.
  const qr = meta.bot.link
    ? await QRCode.toString(meta.bot.link, { type: "svg", margin: 0, errorCorrectionLevel: "M", color: { dark: "#0a1433", light: "#ffffff" } }).catch(() => null)
    : null;

  return (
    <>
      {/* ---- The hero: the night over the Gulf, white frosted panels on it, the thread underneath. ---- */}
      <Night word="ሰላም">
        {/* grid-cols-1 on phones: one column that never grows past the screen, so a long word or label truncates instead of widening the hero. */}
        <div className="mx-auto grid max-w-page grid-cols-1 gap-10 px-4 lg:px-8 pb-10 pt-10 md:grid-cols-[1.12fr_0.88fr] md:items-center md:pb-14 md:pt-16 lg:gap-16">
          <div className="settle">
            <p className="glass-white inline-flex items-center gap-2 rounded-full py-1.5 pl-1.5 pr-3.5 text-[0.8125rem] font-semibold">
              <span className="tibeb-thin inline-block w-7 rounded-full" aria-hidden="true" />
              <span lang="am" className="text-gild">ሰላም</span>
              <span className="text-faint" aria-hidden="true">·</span>
              {d.home.hello}
            </p>
            <h1 className="display mt-5 text-[2.625rem] md:text-[3.75rem] lg:text-[4.25rem]">
              {d.home.heroTitle} <span className="gild">{d.home.heroAccent}</span>
            </h1>
            <p className="mt-5 max-w-xl text-[1.0625rem] muted-on-night md:text-lg">{d.home.lead}</p>
            <HomeSearch lang={lang} placeholder={d.home.searchPlaceholder} scopes={d.home.scopes} scopeLabel={d.home.searchIn} searchLabel={d.common.search} />
            {statItems.length > 0 && (
              <dl className="mt-8 flex flex-wrap gap-x-8 gap-y-4">
                {statItems.map((s) => (
                  <div key={s.label} className="flex flex-col-reverse">
                    <dt className="text-xs muted-on-night">{s.label}</dt>
                    <dd className="headline text-[1.75rem] tabular">{number(s.n, lang)}</dd>
                  </div>
                ))}
              </dl>
            )}
          </div>

          {/* The live board: the two clocks, the newest job, the next event. */}
          <div className="settle settle-2 flex flex-col gap-3" role="group" aria-label={d.home.live}>
            <Clocks lang={lang} labels={d.clock} />
            {job && (
              <Link href={`/${lang}/jobs/${job.id}`} className="glass-white lift press hidden items-center gap-3.5 rounded-[1.375rem] border-line p-3.5 md:flex">
                <KindBadge kind="trade" category={job.category} className="size-12 rounded-2xl" />
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.6875rem] font-semibold uppercase tracking-wider text-gild">{d.home.jobsTitle}</span>
                  <span className="block truncate font-semibold">{job.title}</span>
                  <span className="block truncate text-xs muted-on-night">
                    {[job.salary, label(meta.labels.emirates[job.emirate], lang), relative(job.published_at, lang)].filter(Boolean).join(" · ")}
                  </span>
                </span>
                <ArrowUpRight className="size-4 shrink-0 text-faint" aria-hidden="true" />
              </Link>
            )}
            {event?.event_date && (
              <Link href={`/${lang}/news/${event.id}`} className="glass-white lift press hidden items-center gap-3.5 rounded-[1.375rem] border-line p-3.5 md:flex">
                <span className="grid w-12 shrink-0 overflow-hidden rounded-xl bg-elevated text-center text-fg ring-1 ring-line">
                  <span className="bg-accent py-0.5 text-[0.625rem] font-bold text-night">{dayAndMonth(event.event_date, lang).month}</span>
                  <span className="headline py-0.5 text-lg tabular">{dayAndMonth(event.event_date, lang).day}</span>
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-[0.6875rem] font-semibold uppercase tracking-wider text-gild">{d.home.eventsTitle}</span>
                  <span className="block truncate font-semibold" lang={pickLang(event.text, lang)}>{firstLine(pick(event.text, lang), 80)}</span>
                  <span className="block truncate text-xs muted-on-night">{weekday(event.event_date, lang)} · {ethiopianDate(event.event_date, lang)}</span>
                </span>
                <ArrowUpRight className="size-4 shrink-0 text-faint" aria-hidden="true" />
              </Link>
            )}
          </div>
        </div>
      </Night>

      {/* ---- Bento: the four things this site is for, one tile each. ---- */}
      <section className="mx-auto mt-10 max-w-page px-4 lg:px-8 md:mt-14" aria-label={d.brand}>
        <div className="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-4">
          {/* Jobs. Before the first one is published: a welcome, and the alerts in the bot (a tile cannot be a link holding a link). */}
          {stats.jobs > 0 ? (
            <Link href={`/${lang}/jobs`} className={`${jobsTile} tile-link`}>
              <span className="weave" aria-hidden="true" />
              <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/14 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">
                <ShieldCheck className="size-3.5 text-accent" aria-hidden="true" /> {d.home.bento.jobsBadge}
              </span>
              <span className="mt-6 flex items-end gap-3">
                <span className="display text-[4.5rem] leading-[0.85] tabular md:text-[5.5rem]">{number(stats.jobs, lang)}</span>
                <span className="headline pb-1 text-xl">{d.home.stats.jobs}</span>
              </span>
              <span className="mt-3 max-w-md text-[0.9375rem] text-white/80">{d.home.bento.jobsText}</span>
              {home.jobs.length > 0 && (
                <span className="mt-6 flex flex-col gap-2">
                  {home.jobs.slice(0, 3).map((j, i) => (
                    <span key={j.id} className={`items-center gap-3 rounded-2xl bg-white/10 p-2.5 ring-1 ring-white/12 ${i === 2 ? "hidden lg:flex" : "flex"}`}>
                      <KindBadge kind="trade" category={j.category} className="size-9 rounded-xl ring-1 ring-white/20" iconClass="size-4" />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold">{j.title}</span>
                        <span className="block truncate text-xs text-white/65">{[j.company, label(meta.labels.emirates[j.emirate], lang)].filter(Boolean).join(" · ")}</span>
                      </span>
                      {j.salary && <span className="hidden shrink-0 rounded-full bg-white/14 px-2.5 py-1 text-xs font-semibold sm:inline">{j.salary}</span>}
                    </span>
                  ))}
                </span>
              )}
              <span className="mt-auto flex items-center gap-1.5 pt-6 font-semibold">
                {d.common.seeAll} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
              </span>
            </Link>
          ) : (
            <div className={jobsTile}>
              <span className="weave" aria-hidden="true" />
              <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/14 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">
                <ShieldCheck className="size-3.5 text-accent" aria-hidden="true" /> {d.home.bento.jobsBadge}
              </span>
              <span className="headline mt-6 block max-w-md text-[1.875rem] md:text-[2.25rem]">{d.home.bento.jobsWelcome}</span>
              <span className="mt-3 max-w-md text-[0.9375rem] text-white/80">{d.home.bento.jobsWelcomeText}</span>
              {meta.bot.link && (
                <a href={`${meta.bot.link}?start=jobs`} target="_blank" rel="noopener noreferrer" className="btn btn-gold relative z-10 mt-auto w-fit rounded-full">
                  <Bell className="size-4" aria-hidden="true" /> {d.home.bento.jobsAlerts}
                </a>
              )}
            </div>
          )}

          {/* Places */}
          <Link
            href={stats.businesses > 0 ? `/${lang}/directory` : `/${lang}/business`}
            className="tile tile-link spot reveal reveal-2 col-span-2 flex min-h-[15rem] flex-col bg-[linear-gradient(150deg,#4a2511_0%,#9c4a22_55%,#d9822b_100%)] p-6 text-white sm:col-span-1 lg:col-span-1"
          >
            <span className="grid w-fit grid-cols-3 gap-1.5">
              {home.categories.slice(0, 6).map((c) => (
                <KindBadge key={c.key} kind="place" category={c.key} className="size-9 rounded-xl ring-1 ring-white/25" iconClass="size-4" />
              ))}
            </span>
            {stats.businesses > 0 ? (
              <>
                <span className="mt-5 flex items-end gap-2">
                  <span className="display text-[3rem] leading-[0.85] tabular">{number(stats.businesses, lang)}</span>
                  <span className="headline pb-0.5 text-lg">{d.home.bento.placesTitle}</span>
                </span>
                <span className="mt-2 text-sm text-white/80">{d.home.bento.placesText}</span>
                <span className="mt-auto flex items-center gap-1.5 pt-4 text-sm font-semibold">
                  <Store className="size-4" aria-hidden="true" /> {d.nav.directory} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
                </span>
              </>
            ) : (
              <>
                <span className="headline mt-5 block text-[1.375rem]">{d.home.bento.placesFirst}</span>
                <span className="mt-2 text-sm text-white/80">{d.home.bento.placesFirstText}</span>
                <span className="mt-auto flex items-center gap-1.5 pt-4 text-sm font-semibold">
                  <Plus className="size-4" aria-hidden="true" /> {d.home.ownerCta} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
                </span>
              </>
            )}
          </Link>

          {/* Events */}
          <Link
            href={`/${lang}/news${event ? "?events=1" : ""}`}
            className="tile tile-link spot reveal reveal-3 col-span-2 flex min-h-[15rem] flex-col bg-[linear-gradient(150deg,#ffd666_0%,#f2b705_50%,#e8873b_100%)] p-6 text-[#2a1a00] sm:col-span-1 lg:col-span-1"
          >
            <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-black/8 px-3 py-1 text-xs font-semibold">
              {event ? <CalendarDays className="size-3.5" aria-hidden="true" /> : <Newspaper className="size-3.5" aria-hidden="true" />}
              {d.home.bento.eventsTitle}
            </span>
            {event?.event_date ? (
              <>
                <span className="mt-4 flex items-end gap-3">
                  <span className="display text-[3.5rem] leading-[0.8] tabular">{dayAndMonth(event.event_date, lang).day}</span>
                  <span className="pb-0.5">
                    <span className="block text-sm font-bold">{dayAndMonth(event.event_date, lang).month}</span>
                    <span className="block text-xs opacity-75">{weekday(event.event_date, lang)}</span>
                  </span>
                </span>
                <span className="mt-3 line-clamp-2 font-semibold leading-snug" lang={pickLang(event.text, lang)}>{firstLine(pick(event.text, lang), 90)}</span>
                <span className="mt-1 text-xs opacity-75">{ethiopianDate(event.event_date, lang)}</span>
              </>
            ) : (
              <span className="mt-4 text-sm font-medium opacity-85">{d.home.bento.eventsEmpty}</span>
            )}
            <span className="mt-auto flex items-center gap-1.5 pt-4 text-sm font-semibold">
              {d.nav.news} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
            </span>
          </Link>

          {/* Owners */}
          <Link
            href={`/${lang}/business`}
            className="tile tile-link reveal reveal-4 col-span-2 flex min-h-[15rem] items-center gap-6 overflow-hidden border border-line bg-elevated p-6 md:p-7"
          >
            <span className="relative z-10 min-w-0 flex-1">
              <span className="headline block text-[1.375rem] md:text-[1.625rem]">{d.home.ownerTitle}</span>
              <span className="mt-2 block text-sm text-muted">{d.home.bento.ownerText}</span>
              <span className="btn btn-primary mt-5 pointer-events-none">
                {d.home.ownerCta} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
              </span>
            </span>
            <ListingMock lang={lang} className="hidden shrink-0 rotate-[4deg] translate-y-6 sm:block" />
            <span className="tibeb-thin absolute inset-x-0 bottom-0" aria-hidden="true" />
          </Link>
        </div>
      </section>

      {home.jobs.length > 0 && (
        <Section mark={1} title={d.home.jobsTitle} lead={d.home.jobsLead} more={{ href: `/${lang}/jobs`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-3">
            {home.jobs.slice(0, 6).map((j) => <JobCard key={j.id} job={j} lang={lang} meta={meta} compact />)}
          </div>
        </Section>
      )}

      <Section mark={2} title={d.home.kindsTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-7">
          {home.categories.map((c) => (
            <Link
              key={c.key}
              href={`/${lang}/directory?category=${c.key}`}
              className="card lift flex items-center gap-3 p-3 lg:flex-col lg:items-start lg:gap-5 lg:p-4"
            >
              <KindBadge kind="place" category={c.key} className="size-11 rounded-2xl" />
              <span className="w-full min-w-0">
                <span className="line-clamp-2 text-sm font-semibold leading-snug" title={label(c.name, lang)}>{label(c.name, lang)}</span>
                {c.count > 0 && <span className="block text-xs text-muted tabular">{number(c.count, lang)}</span>}
              </span>
            </Link>
          ))}
        </div>
      </Section>

      {home.businesses.length > 0 && (
        <Section mark={3} title={d.home.placesTitle} more={{ href: `/${lang}/directory`, label: d.common.seeAll }}>
          <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-3 md:overflow-visible md:px-0 lg:grid-cols-4">
            {home.businesses.map((b) => <BusinessCard key={b.id} business={b} lang={lang} meta={meta} />)}
          </div>
        </Section>
      )}

      {/* ---- The goods board: spare kilos between the two homes. ---- */}
      <GoodsTeaser lang={lang} d={d} board={goods} mark={4} />

      {(home.events.length > 0 || home.announcements.length > 0) && (
        <div className="mx-auto grid max-w-page gap-x-8 md:grid-cols-[1fr_1.6fr]">
          {home.events.length > 0 && (
            <Section mark={5} title={d.home.eventsTitle} more={{ href: `/${lang}/news?events=1`, label: d.common.seeAll }} className="w-full md:px-0 md:pl-4">
              <div className="flex flex-col gap-3">
                {home.events.map((e) => <EventCard key={e.id} item={e} lang={lang} />)}
              </div>
            </Section>
          )}
          {home.announcements.length > 0 && (
            <Section mark={home.events.length > 0 ? 6 : 5} title={d.home.newsTitle} more={{ href: `/${lang}/news`, label: d.common.seeAll }} className="w-full md:px-0 md:pr-4">
              <div className="row-scroll md:mx-0 md:grid-flow-row md:grid-cols-2 md:overflow-visible md:px-0">
                {home.announcements.slice(0, 4).map((a) => <NewsCard key={a.id} item={a} lang={lang} />)}
              </div>
            </Section>
          )}
        </div>
      )}

      {/* ---- Join: everything else happens in the bot. ---- */}
      <section className="reveal mx-auto mt-20 max-w-page px-4 lg:px-8 md:mt-28">
        <div className="night tile p-6 md:p-10 lg:p-12">
          <div className="relative grid gap-10 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <h2 className="display max-w-2xl text-[2rem] md:text-[2.75rem]">{d.home.joinTitle}</h2>
              <p className="mt-4 max-w-2xl muted-on-night">{d.home.joinLead}</p>
              <ol className="mt-8 grid gap-3 sm:grid-cols-2">
                {d.home.joinItems.map((item, i) => (
                  <li key={item.title} className="glass flex gap-3.5 rounded-[1.25rem] p-4">
                    <span className="geez-mark border-accent/60 bg-accent/12 text-accent" aria-hidden="true">{GEEZ[i]}</span>
                    <span>
                      <span className="title block">{item.title}</span>
                      <span className="mt-1 block text-sm muted-on-night">{item.text}</span>
                    </span>
                  </li>
                ))}
              </ol>
              {meta.bot.link && (
                <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-gold mt-8 min-h-12 rounded-full px-6 text-base">
                  <Send className="size-4.5" aria-hidden="true" /> {d.home.joinCta}
                </a>
              )}
            </div>
            {qr && (
              <figure className="hidden flex-col items-center gap-3 lg:flex">
                <div className="rounded-[1.75rem] bg-white p-4 shadow-[0_20px_50px_-20px_rgb(0_0_0/0.7)] ring-4 ring-accent/40">
                  <div className="size-40 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: qr }} />
                </div>
                <figcaption className="max-w-[12rem] text-center text-xs muted-on-night">{d.home.scanToJoin}</figcaption>
              </figure>
            )}
          </div>
        </div>
      </section>

    </>
  );
}
