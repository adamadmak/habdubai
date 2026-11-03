import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CalendarPlus, ChevronLeft, MapPin } from "lucide-react";
import { DateLeaf, firstLine, NewsCard } from "@/components/Cards";
import { ShareButton } from "@/components/ShareButton";
import { apiOrNull, type Announcement } from "@/lib/api";
import { daysUntil, ethiopianDate, formatDate, weekday } from "@/lib/format";
import { dict, fill, isLocale, label, LOCALE_NAMES, LOCALES, pick, pickLang, type Locale } from "@/lib/i18n";
import { siteUrl } from "@/lib/site";

type Detail = { announcement: Announcement; more: Announcement[] };
type Params = { lang: string; id: string };

async function load(id: string): Promise<Detail | null> {
  return /^\d+$/.test(id) ? apiOrNull<Detail>(`/public/announcements/${id}`) : null;
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang, id } = await params;
  if (!isLocale(lang)) return {};
  const data = await load(id);
  if (!data) return {};
  const a = data.announcement;
  const text = pick(a.text, lang);
  return {
    title: firstLine(text, 70),
    description: text.slice(0, 200),
    alternates: { canonical: `/${lang}/news/${a.id}` },
    openGraph: a.photo ? { images: [{ url: a.photo }] } : undefined,
  };
}

export default async function NewsItem({ params, searchParams }: { params: Promise<Params>; searchParams: Promise<{ read?: string }> }) {
  const { lang, id } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const data = await load(id);
  if (!data) notFound();
  const { announcement: a, more } = data;

  // An announcement comes in Amharic, Tigrinya and English, and in Afaan Oromo once the API translates it;
  // read it in another without changing the site's. Only the languages it has are offered.
  const asked = (await searchParams).read;
  const reading: Locale = asked && LOCALES.includes(asked as Locale) ? (asked as Locale) : lang;
  const text = pick(a.text, reading);
  // The language the text is really in: English stands in for one not translated yet.
  const textLang = pickLang(a.text, reading);
  const languages = LOCALES.filter((l) => a.text[l]);
  const soon = a.event_date ? daysUntil(a.event_date) : null;

  // An event reads to search engines as one: its name, its day and where it is held.
  const jsonLd = a.event_date
    ? {
        "@context": "https://schema.org",
        "@type": "Event",
        name: firstLine(pick(a.text, lang), 110),
        startDate: a.event_date,
        eventAttendanceMode: "https://schema.org/OfflineEventAttendanceMode",
        location: a.event_place ? { "@type": "Place", name: a.event_place, address: a.event_place } : undefined,
        url: siteUrl(`${lang}/news/${a.id}`),
      }
    : null;

  return (
    <article className="mx-auto max-w-3xl px-4 pt-6 md:pt-10">
      {jsonLd && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />}
      <Link href={`/${lang}/news`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {d.news.back}
      </Link>

      <p className="eyebrow mt-6">{a.topic?.emoji} {label(a.topic?.name, lang)}</p>
      <h1 className="display mt-2 text-[2rem] md:text-[2.75rem]" lang={textLang}>{firstLine(text, 140)}</h1>
      <p className="mt-2 text-sm text-muted">{a.published_at && fill(d.news.posted, { date: formatDate(a.published_at, lang) })}</p>

      {a.event_date && (
        <div className="card mt-6 flex flex-wrap items-center gap-4 p-4">
          <DateLeaf date={a.event_date} lang={lang} size="lg" />
          <div className="min-w-0 flex-1">
            <p className="font-semibold">
              {soon === 0 ? d.news.days.today : soon === 1 ? d.news.days.tomorrow : weekday(a.event_date, lang)}, {formatDate(a.event_date, lang)}
            </p>
            <p className="text-sm text-muted">{ethiopianDate(a.event_date, lang)}</p>
            {a.event_place && (
              <p className="mt-1 flex items-center gap-1 text-sm text-muted">
                <MapPin className="size-4 shrink-0" aria-hidden="true" /> {a.event_place}
              </p>
            )}
          </div>
          {soon !== null && soon >= 0 && (
            <a href={`/api/calendar/${a.id}?lang=${lang}`} className="btn btn-quiet" download>
              <CalendarPlus className="size-4" aria-hidden="true" /> {d.news.addToCalendar}
            </a>
          )}
        </div>
      )}

      {/* The box holds its shape while the photo loads, so the text below never jumps; a poster shows whole inside it. */}
      {a.photo && (
        <div className="mt-6 aspect-[4/3] overflow-hidden rounded-2xl border border-line bg-surface">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={a.photo} alt="" decoding="async" className="size-full object-contain" />
        </div>
      )}

      {languages.length > 1 && (
        <div role="group" aria-label={d.news.readIn} className="mt-6 flex flex-wrap items-center gap-2 text-sm">
          <span className="text-muted">{d.news.readIn}</span>
          {languages.map((l) => (
            <Link key={l} href={l === lang ? `/${lang}/news/${a.id}` : `/${lang}/news/${a.id}?read=${l}`} aria-current={l === textLang ? "true" : undefined} className="chip py-1" lang={l} scroll={false}>
              {LOCALE_NAMES[l]}
            </Link>
          ))}
        </div>
      )}

      <div className="prose-plain mt-5 text-[1.0625rem] leading-relaxed" lang={textLang}>{text}</div>

      <div className="mt-8">
        <ShareButton title={firstLine(text, 80)} url={siteUrl(`${lang}/news/${a.id}`)} labels={{ share: d.common.share, copied: d.common.copied }} />
      </div>

      {more.length > 0 && (
        <section className="mt-12">
          <h2 className="headline text-[1.75rem]">{fill(d.news.more, { topic: label(a.topic?.name, lang) })}</h2>
          <div className="mt-4 grid gap-3 sm:grid-cols-3">
            {more.map((m) => <NewsCard key={m.id} item={m} lang={lang} />)}
          </div>
        </section>
      )}
    </article>
  );
}
