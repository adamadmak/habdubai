import Link from "next/link";
import { BadgeCheck, BedDouble, MapPin, Stamp } from "lucide-react";
import { dict, fill, label, pick, type Locale } from "@/lib/i18n";
import { dayAndMonth, ethiopianDate, formatDate, relative, weekday } from "@/lib/format";
import type { Announcement, Business, Job, Meta } from "@/lib/api";
import { BusinessIcon, TradeIcon } from "./CategoryIcon";

/** A job: the title leads, then who and where, then pay and the facts that matter most to us. */
export function JobCard({ job, lang, meta, compact = false }: { job: Job; lang: Locale; meta: Meta | null; compact?: boolean }) {
  const d = dict(lang);
  const place = [job.area, label(meta?.labels.emirates[job.emirate], lang)].filter(Boolean).join(", ");

  return (
    <Link href={`/${lang}/jobs/${job.id}`} className="card lift flex h-full flex-col gap-3 p-4">
      <div className="flex items-start gap-3">
        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
          <TradeIcon category={job.category} />
        </span>
        <div className="min-w-0">
          <h3 className="title line-clamp-2 text-[1.0625rem]">{job.title}</h3>
          <p className="mt-0.5 truncate text-sm text-muted">{[job.company, place].filter(Boolean).join(" · ")}</p>
        </div>
      </div>
      {!compact && job.summary[lang] && <p className="line-clamp-2 text-sm text-muted">{job.summary[lang]}</p>}
      <div className="mt-auto flex flex-wrap items-center gap-1.5 text-xs">
        <span className={`rounded-full px-2.5 py-1 font-semibold ${job.salary ? "bg-success-soft text-success" : "bg-surface text-muted"}`}>
          {job.salary ?? d.jobs.salaryNone}
        </span>
        {job.accommodation && (
          <span className="inline-flex items-center gap-1 rounded-full bg-surface px-2.5 py-1 font-medium">
            <BedDouble className="size-3.5" aria-hidden="true" /> {d.jobs.accommodation}
          </span>
        )}
        {job.visa && (
          <span className="inline-flex items-center gap-1 rounded-full bg-surface px-2.5 py-1 font-medium">
            <Stamp className="size-3.5" aria-hidden="true" /> {d.jobs.visa}
          </span>
        )}
        <span className="ml-auto text-faint">{relative(job.published_at, lang)}</span>
      </div>
    </Link>
  );
}

/** A business: its photo, or a tibeb tile with the kind's icon when there is none. */
export function BusinessCard({ business, lang, meta }: { business: Business; lang: Locale; meta: Meta | null }) {
  const d = dict(lang);
  const kind = label(meta?.labels.business_categories[business.category], lang);

  return (
    <Link href={`/${lang}/directory/${business.slug}`} className="card lift group flex h-full flex-col overflow-hidden">
      <div className="relative aspect-[16/10] overflow-hidden bg-primary-soft">
        {business.photo ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={business.photo} alt="" loading="lazy" decoding="async" className="size-full object-cover" />
        ) : (
          <div className="flex size-full flex-col">
            <div className="grid flex-1 place-items-center text-primary">
              <BusinessIcon category={business.category} className="size-10" />
            </div>
            <div className="tibeb-thin" aria-hidden="true" />
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-4">
        <h3 className="title flex items-center gap-1.5 text-[1.0625rem]">
          <span className="truncate">{business.name}</span>
          {business.verified && <BadgeCheck className="size-4.5 shrink-0 text-primary" aria-label={d.common.verified} />}
        </h3>
        <p className="text-sm text-muted">{kind}</p>
        <p className="mt-auto flex items-center gap-1 pt-1 text-sm text-muted">
          <MapPin className="size-3.5 shrink-0" aria-hidden="true" />
          <span className="truncate">{[business.area, pick(business.city?.name, lang)].filter(Boolean).join(", ")}</span>
        </p>
      </div>
    </Link>
  );
}

/** A calendar leaf: day and month, so an event reads at a glance. */
export function DateLeaf({ date, lang }: { date: string; lang: Locale }) {
  const { day, month } = dayAndMonth(date, lang);
  return (
    <div className="flex w-14 shrink-0 flex-col overflow-hidden rounded-xl border border-line bg-elevated text-center">
      <span className="bg-primary py-0.5 text-[0.6875rem] font-semibold text-primary-fg">{month}</span>
      <span className="py-1 text-xl font-bold tabular">{day}</span>
    </div>
  );
}

export function EventCard({ item, lang }: { item: Announcement; lang: Locale }) {
  const text = item.text[lang] || item.text.en;
  return (
    <Link href={`/${lang}/news/${item.id}`} className="card lift flex h-full gap-3.5 p-4">
      {item.event_date && <DateLeaf date={item.event_date} lang={lang} />}
      <div className="min-w-0">
        <p className="text-xs font-semibold text-muted">
          {item.topic?.emoji} {label(item.topic?.name, lang)}
        </p>
        <p className="mt-1 line-clamp-2 font-semibold leading-snug">{firstLine(text)}</p>
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

export function NewsCard({ item, lang }: { item: Announcement; lang: Locale }) {
  const d = dict(lang);
  const text = item.text[lang] || item.text.en;
  return (
    <Link href={`/${lang}/news/${item.id}`} className="card lift flex h-full flex-col overflow-hidden">
      {item.photo && (
        <div className="aspect-[16/9] overflow-hidden bg-surface">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={item.photo} alt="" loading="lazy" decoding="async" className="size-full object-cover" />
        </div>
      )}
      <div className="flex flex-1 flex-col gap-2 p-4">
        <p className="text-xs font-semibold text-muted">
          {item.topic?.emoji} {label(item.topic?.name, lang)}
        </p>
        <p className="line-clamp-3 font-medium leading-snug">{text}</p>
        <p className="mt-auto pt-1 text-xs text-faint">
          {item.event_date ? `${d.news.event} · ${formatDate(item.event_date, lang, "medium")}` : relative(item.published_at, lang)}
        </p>
      </div>
    </Link>
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
  return n === 1 ? one : fill(many, { n: new Intl.NumberFormat(lang === "en" ? "en-GB" : lang).format(n) });
}
