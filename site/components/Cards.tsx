import Link from "next/link";
import { BadgeCheck, BedDouble, MapPin, MessageCircle, Navigation, Phone, Stamp } from "lucide-react";
import { dict, fill, label, pick, pickLang, type Locale } from "@/lib/i18n";
import { ethiopianDate, formatDate, number, relative, weekday } from "@/lib/format";
import type { Announcement, Business, Job, Meta } from "@/lib/api";
import { photoSrcSet } from "@/lib/photos";
import { Cover, KindBadge } from "./Cover";
import { DateLeaf } from "./DateLeaf";

/** A job: the title leads, then who and where; the pay is set large, and the two facts that matter most sit beside it. */
export function JobCard({ job, lang, meta, compact = false }: { job: Job; lang: Locale; meta: Meta | null; compact?: boolean }) {
  const d = dict(lang);
  const place = [job.area, label(meta?.labels.emirates[job.emirate], lang)].filter(Boolean).join(", ");
  const summary = pick(job.summary, lang);

  return (
    <Link href={`/${lang}/jobs/${job.id}`} className="card lift flex h-full flex-col p-4 md:p-5">
      <div className="flex items-start gap-3.5">
        <KindBadge kind="trade" category={job.category} />
        <div className="min-w-0 flex-1">
          <h3 className="title line-clamp-2 text-[1.0625rem]">{job.title}</h3>
          <p className="mt-0.5 truncate text-sm text-muted">{[job.company, place].filter(Boolean).join(" · ")}</p>
        </div>
      </div>
      {!compact && summary && <p className="mt-3 line-clamp-2 text-sm text-muted" lang={pickLang(job.summary, lang)}>{summary}</p>}
      <div className="mt-auto flex items-end justify-between gap-3 pt-4">
        <div className="min-w-0 border-t border-dashed border-line pt-3">
          {job.salary ? (
            <p className="headline truncate text-[1.1875rem] text-success">{job.salary}</p>
          ) : (
            <p className="pt-1 text-sm text-faint">{d.jobs.salaryNone}</p>
          )}
          <p className="mt-0.5 text-xs text-faint">{relative(job.published_at, lang)}</p>
        </div>
        <div className="flex shrink-0 gap-1.5 pb-0.5">
          {job.accommodation && (
            <span title={d.jobs.accommodation} className="grid size-8 place-items-center rounded-full bg-success-soft text-success">
              <BedDouble className="size-4" aria-hidden="true" />
              <span className="sr-only">{d.jobs.accommodation}</span>
            </span>
          )}
          {job.visa && (
            <span title={d.jobs.visa} className="grid size-8 place-items-center rounded-full bg-success-soft text-success">
              <Stamp className="size-4" aria-hidden="true" />
              <span className="sr-only">{d.jobs.visa}</span>
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}

/** A place: its photo, or a cover in its kind's colours with its initials. */
export function BusinessCard({ business, lang, meta }: { business: Business; lang: Locale; meta: Meta | null }) {
  const d = dict(lang);
  const kind = label(meta?.labels.business_categories[business.category], lang);

  return (
    <Link href={`/${lang}/directory/${business.slug}`} className="card lift group flex h-full flex-col overflow-hidden">
      <div className="relative aspect-[16/10] overflow-hidden">
        {business.photo ? (
          // The 480px copy for a card, the original on wide or dense screens. Cards run one, two, three or four across.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={business.photo}
            srcSet={photoSrcSet(business.photo)}
            sizes="(min-width: 1280px) 280px, (min-width: 1024px) 370px, (min-width: 640px) 50vw, 100vw"
            alt=""
            loading="lazy"
            decoding="async"
            className="size-full object-cover"
          />
        ) : (
          <Cover category={business.category} name={business.name} seed={business.id} className="size-full" />
        )}
        {business.verified && (
          <span className="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 rounded-full bg-white/92 px-2 py-1 text-[0.6875rem] font-semibold text-[#143aa8] shadow-sm">
            <BadgeCheck className="size-3.5" aria-hidden="true" /> {d.common.verified}
          </span>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1 p-4">
        <p className="text-xs font-semibold text-accent-ink">{kind}</p>
        <h3 className="title truncate text-[1.0625rem]">{business.name}</h3>
        {business.description && <p className="line-clamp-2 text-sm text-muted">{business.description}</p>}
        <p className="mt-auto flex items-center gap-1 pt-2 text-sm text-muted">
          <MapPin className="size-3.5 shrink-0" aria-hidden="true" />
          <span className="truncate">{[business.area, pick(business.city?.name, lang)].filter(Boolean).join(", ")}</span>
        </p>
      </div>
    </Link>
  );
}

export { DateLeaf };

export function EventCard({ item, lang }: { item: Announcement; lang: Locale }) {
  const text = pick(item.text, lang);
  const textLang = pickLang(item.text, lang);
  return (
    <Link href={`/${lang}/news/${item.id}`} className="card lift flex h-full gap-4 p-4">
      {item.event_date && <DateLeaf date={item.event_date} lang={lang} />}
      <div className="min-w-0">
        <p className="text-xs font-semibold text-accent-ink">
          {item.topic?.emoji} {label(item.topic?.name, lang)}
        </p>
        <p className="mt-1 line-clamp-2 font-semibold leading-snug" lang={textLang}>{firstLine(text)}</p>
        {item.event_date && (
          <p className="mt-1.5 text-xs text-muted">
            {weekday(item.event_date, lang)} · {ethiopianDate(item.event_date, lang)}
          </p>
        )}
        {item.event_place && (
          <p className="mt-1 flex items-center gap-1 text-xs text-muted">
            <MapPin className="size-3.5 shrink-0" aria-hidden="true" />
            <span className="truncate">{item.event_place}</span>
          </p>
        )}
      </div>
    </Link>
  );
}

/** An announcement. Without a photo, its topic's emoji stands in, on paper, so the text stays the point. */
export function NewsCard({ item, lang }: { item: Announcement; lang: Locale }) {
  const d = dict(lang);
  const text = pick(item.text, lang);
  const textLang = pickLang(item.text, lang);
  return (
    <Link href={`/${lang}/news/${item.id}`} className="card lift flex h-full flex-col overflow-hidden">
      {item.photo ? (
        <div className="aspect-[16/9] overflow-hidden bg-surface">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={item.photo} alt="" loading="lazy" decoding="async" className="size-full object-cover" />
        </div>
      ) : (
        <div className="tibeb-thin" aria-hidden="true" />
      )}
      <div className="flex flex-1 flex-col gap-2.5 p-4 md:p-5">
        <p className="flex items-center gap-2 text-xs font-semibold text-muted">
          {item.topic?.emoji && <span className="grid size-7 place-items-center rounded-full bg-accent-soft text-sm" aria-hidden="true">{item.topic.emoji}</span>}
          {label(item.topic?.name, lang)}
        </p>
        <p className="line-clamp-3 font-medium leading-snug" lang={textLang}>{text}</p>
        <p className="mt-auto pt-1 text-xs text-faint">
          {item.event_date ? `${d.news.event} · ${formatDate(item.event_date, lang, "medium")}` : relative(item.published_at, lang)}
        </p>
      </div>
    </Link>
  );
}

/** What an owner's listing will look like: a sample card, for the owners' pages. Decoration only. */
export function ListingMock({ lang, className = "" }: { lang: Locale; className?: string }) {
  const d = dict(lang);
  const sample = d.home.bento;
  return (
    <div aria-hidden="true" className={`w-64 overflow-hidden rounded-[1.375rem] bg-white text-[#10142b] shadow-[0_24px_50px_-20px_rgb(6_12_36/0.55)] ring-1 ring-black/5 ${className}`}>
      <Cover category="cafe" name={sample.ownerSample} seed={2} className="aspect-[16/10]" />
      <div className="p-3.5">
        <p className="text-[0.6875rem] font-semibold text-[#855f00]">{sample.ownerSampleKind}</p>
        <p className="mt-0.5 flex items-center gap-1 font-semibold">
          {sample.ownerSample} <BadgeCheck className="size-4 text-[#1b4ddb]" />
        </p>
        <p className="mt-0.5 flex items-center gap-1 text-xs text-[#586078]"><MapPin className="size-3" /> {sample.ownerSamplePlace}</p>
        <div className="mt-3 flex gap-1.5">
          <span className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-[#1b4ddb] py-1.5 text-[0.6875rem] font-semibold text-white"><Phone className="size-3" /> {d.common.call}</span>
          <span className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-[#f3eee3] py-1.5 text-[0.6875rem] font-semibold"><MessageCircle className="size-3" /> {d.common.whatsapp}</span>
          <span className="grid place-items-center rounded-lg bg-[#f3eee3] px-2 py-1.5"><Navigation className="size-3" /></span>
        </div>
      </div>
    </div>
  );
}

/** The first sentence or line of an announcement, as its title. */
export function firstLine(text: string, max = 110): string {
  const line = text.split(/\n/)[0].trim();
  const sentence = line.split(/(?<=[.!?።])\s/)[0];
  const pickText = sentence.length >= 20 ? sentence : line;
  return pickText.length > max ? `${pickText.slice(0, max - 1).trimEnd()}…` : pickText;
}

export function countLabel(n: number, one: string, many: string, lang: Locale): string {
  return n === 1 ? one : fill(many, { n: number(n, lang) });
}
