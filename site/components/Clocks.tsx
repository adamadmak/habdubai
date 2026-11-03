"use client";
import { useEffect, useState } from "react";
// fill on its own and the rest as types only: nothing here pulls the dictionaries into the browser.
import { fill } from "@/lib/fill";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import { ethiopianDate, formatDate } from "@/lib/format";

// Oromo reads a 24-hour time as English does; en-GB keeps browsers without `om` from using the reader's own locale.
const TAG: Record<Locale, string> = { am: "am-ET", ti: "ti-ER", om: "en-GB", en: "en-GB" };

function hm(date: Date, timeZone: string, locale: Locale): string {
  return new Intl.DateTimeFormat(TAG[locale], { hour: "2-digit", minute: "2-digit", hourCycle: "h23", timeZone }).format(date);
}

/**
 * The Ethiopian clock counts the hours from six in the morning, so 9:15 in
 * Addis Ababa is "3:15 in the morning". Hours run 1–12 in each half-day.
 */
export function habeshaTime(date: Date, periods: string[], format: string): string {
  const parts = new Intl.DateTimeFormat("en-GB", { hour: "numeric", minute: "2-digit", hourCycle: "h23", timeZone: "Africa/Addis_Ababa" }).formatToParts(date);
  const hour = Number(parts.find((p) => p.type === "hour")?.value ?? 0);
  const minute = parts.find((p) => p.type === "minute")?.value ?? "00";
  const shifted = (hour + 18) % 12; // 07:00 → 1, 12:00 → 6, 18:00 → 12
  const period = periods[Math.floor(hour / 6)] ?? "";
  return fill(format, { time: `${shifted === 0 ? 12 : shifted}:${minute}`, period });
}

/**
 * Dubai, and home: the two times every reader keeps in their head, plus the
 * Ethiopian clock and calendar. Live, so it is the one thing on the page that
 * keeps moving. The server sends placeholders; the time is the reader's own.
 */
export function Clocks({ lang, labels }: { lang: Locale; labels: Dictionary["clock"] }) {
  const [now, setNow] = useState<Date | null>(null);

  useEffect(() => {
    setNow(new Date());
    const timer = window.setInterval(() => setNow(new Date()), 10_000);
    return () => window.clearInterval(timer);
  }, []);

  const blank = "––:––";

  return (
    <div className="glass-white rounded-[1.5rem] p-4 md:p-5">
      <div className="grid grid-cols-2 gap-4">
        <div>
          <p className="flex items-center gap-1.5 text-xs font-semibold muted-on-night">
            <span className="live-dot size-1.5 rounded-full bg-green-500" aria-hidden="true" />
            {labels.dubai}
          </p>
          <p className="headline mt-1 text-[2rem] tabular md:text-[2.25rem]" suppressHydrationWarning>{now ? hm(now, "Asia/Dubai", lang) : blank}</p>
        </div>
        <div>
          <p className="truncate text-xs font-semibold muted-on-night">{labels.home}</p>
          <p className="headline mt-1 text-[2rem] tabular text-gild md:text-[2.25rem]" suppressHydrationWarning>{now ? hm(now, "Africa/Addis_Ababa", lang) : blank}</p>
        </div>
      </div>
      <div className="mt-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-t border-fg/10 pt-3 text-[0.8125rem]">
        <p suppressHydrationWarning>
          <span className="muted-on-night">{labels.habesha} · </span>
          <span lang={lang} className="font-semibold">{now ? habeshaTime(now, labels.periods, labels.format) : "…"}</span>
        </p>
        <p className="muted-on-night" lang={lang} suppressHydrationWarning>
          {now ? `${labels.today}: ${lang === "en" ? formatDate(now, lang, "medium") : ethiopianDate(now, lang)}` : ""}
        </p>
      </div>
      {lang === "en" && now && <p className="mt-1 text-[0.75rem] muted-on-night" suppressHydrationWarning>{ethiopianDate(now, lang)}</p>}
    </div>
  );
}
