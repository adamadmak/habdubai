import type { Locale } from "./locales";

/** Intl locale tags: Amharic and Tigrinya with their country so month names are right. */
const TAG: Record<Locale, string> = { am: "am-ET", ti: "ti-ER", en: "en-GB" };
const TZ = "Asia/Dubai";

/** A date-only value (2026-11-20) at noon in Dubai, so no timezone moves it a day. */
function toDate(value: string | Date): Date {
  if (value instanceof Date) return value;
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T12:00:00+04:00`) : new Date(value);
}

/*
 * Tigrinya names, copied from the server's own ICU data. Many browsers ship
 * none for Tigrinya and print "M11" instead, so the functions a browser also
 * runs (the goods form's live preview) use this table on both sides: a page
 * drawn on the server reads the same once it is live in the browser.
 */
const TI = {
  monthsShort: ["ጥሪ", "ለካ", "መጋ", "ሚያ", "ግን", "ሰነ", "ሓም", "ነሓ", "መስ", "ጥቅ", "ሕዳ", "ታሕ"],
  weekdaysShort: ["ሰን", "ሰኑ", "ሰሉ", "ረቡ", "ሓሙ", "ዓር", "ቀዳ"],
  weekdays: ["ሰንበት", "ሰኑይ", "ሰሉስ", "ረቡዕ", "ሓሙስ", "ዓርቢ", "ቀዳም"],
  ethiopicMonths: ["መስከረም", "ጥቅምቲ", "ሕዳር", "ታሕሳስ", "ጥሪ", "ለካቲት", "መጋቢት", "ሚያዚያ", "ጉንበት", "ሰነ", "ሓምለ", "ነሓሰ", "ጷጉሜ"],
};

/** The day in Dubai as numbers: year, month (1–12), day, and weekday (0 is Sunday). */
function dubaiParts(date: Date): { year: number; month: number; day: number; weekday: number } {
  const parts = new Intl.DateTimeFormat("en-US", { timeZone: TZ, year: "numeric", month: "numeric", day: "numeric", weekday: "short" }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? "";
  return {
    year: Number(get("year")),
    month: Number(get("month")),
    day: Number(get("day")),
    weekday: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(get("weekday")),
  };
}

/** The Ethiopian (Amete Mihret) date of a Gregorian one, through the Julian day number. */
function toEthiopic(year: number, month: number, day: number): { year: number; month: number; day: number } {
  const a = Math.floor((14 - month) / 12);
  const y = year + 4800 - a;
  const m = month + 12 * a - 3;
  const jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;
  const offset = jdn - 1723856;
  const r = offset % 1461;
  const n = (r % 365) + 365 * Math.floor(r / 1460);
  return { year: 4 * Math.floor(offset / 1461) + Math.floor(r / 365) - Math.floor(r / 1460), month: Math.floor(n / 30) + 1, day: (n % 30) + 1 };
}

export function formatDate(value: string | Date | null | undefined, locale: Locale, style: "long" | "medium" = "long"): string {
  if (!value) return "";
  return new Intl.DateTimeFormat(TAG[locale], { dateStyle: style, timeZone: TZ }).format(toDate(value));
}

/** The same day in the Ethiopian calendar, which most of our readers also live by: "11 ኅዳር 2019 ዓ.ም.". */
export function ethiopianDate(value: string | Date | null | undefined, locale: Locale): string {
  if (!value) return "";
  if (locale === "ti") {
    const p = dubaiParts(toDate(value));
    const e = toEthiopic(p.year, p.month, p.day);
    return `${TI.ethiopicMonths[e.month - 1]} ${e.day}, ${e.year} ዓ.ም.`;
  }
  const tag = locale === "am" ? "am-ET-u-ca-ethiopic" : "en-u-ca-ethiopic";
  const text = new Intl.DateTimeFormat(tag, { day: "numeric", month: "long", year: "numeric", timeZone: TZ }).format(toDate(value));
  return locale === "en" ? text.replace(/\s*AM$/, " (Ethiopian calendar)") : text.replace(/\s*AM$/, " ዓ.ም.");
}

export function weekday(value: string | Date, locale: Locale): string {
  if (locale === "ti") return TI.weekdays[dubaiParts(toDate(value)).weekday] ?? "";
  return new Intl.DateTimeFormat(TAG[locale], { weekday: "long", timeZone: TZ }).format(toDate(value));
}

export function dayAndMonth(value: string | Date, locale: Locale): { day: string; month: string } {
  const date = toDate(value);
  if (locale === "ti") {
    const p = dubaiParts(date);
    return { day: String(p.day), month: TI.monthsShort[p.month - 1] ?? "" };
  }
  return {
    day: new Intl.DateTimeFormat(TAG[locale], { day: "numeric", timeZone: TZ }).format(date),
    month: new Intl.DateTimeFormat(TAG[locale], { month: "short", timeZone: TZ }).format(date),
  };
}

/** "2 hours ago", "yesterday". */
export function relative(value: string | null | undefined, locale: Locale, now: number = Date.now()): string {
  if (!value) return "";
  const seconds = Math.round((new Date(value).getTime() - now) / 1000);
  const abs = Math.abs(seconds);
  const rtf = new Intl.RelativeTimeFormat(TAG[locale], { numeric: "auto" });
  if (abs < 60) return rtf.format(0, "second");
  if (abs < 3600) return rtf.format(Math.round(seconds / 60), "minute");
  if (abs < 86400) return rtf.format(Math.round(seconds / 3600), "hour");
  if (abs < 86400 * 30) return rtf.format(Math.round(seconds / 86400), "day");
  return formatDate(value, locale, "medium");
}

export function number(value: number, locale: Locale): string {
  return new Intl.NumberFormat(TAG[locale]).format(value);
}

/** "March 2025", from "2025-03" or a full date: since when a member has been verified. */
export function monthYear(value: string | null | undefined, locale: Locale): string {
  if (!value) return "";
  const date = toDate(/^\d{4}-\d{2}$/.test(value) ? `${value}-15` : value);
  if (Number.isNaN(date.getTime())) return "";
  if (locale === "ti") {
    const p = dubaiParts(date);
    return `${TI.monthsShort[p.month - 1]} ${p.year}`;
  }
  return new Intl.DateTimeFormat(TAG[locale], { month: "short", year: "numeric", timeZone: TZ }).format(date);
}

/** "Wed 20 Nov": a flight date, short enough for a card. */
export function shortDate(value: string, locale: Locale): string {
  if (locale === "ti") {
    const p = dubaiParts(toDate(value));
    return `${TI.weekdaysShort[p.weekday]}፣ ${p.day} ${TI.monthsShort[p.month - 1]}`;
  }
  return new Intl.DateTimeFormat(TAG[locale], { weekday: "short", day: "numeric", month: "short", timeZone: TZ }).format(toDate(value));
}

/** Today in Dubai as 2026-11-03, the form a date input takes. */
export function todayInDubai(now: Date = new Date()): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ }).format(now);
}

/** A date-only value moved by whole days: addDays("2026-11-03", 120). */
export function addDays(value: string, days: number): string {
  const date = new Date(`${value}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

/** Days from today (Dubai) to a date: 0 today, 1 tomorrow. */
export function daysUntil(value: string, now: Date = new Date()): number {
  return Math.round((toDate(value).getTime() - toDate(todayInDubai(now)).getTime()) / 86400000);
}

/** A phone number for tel: and wa.me links. */
export function digits(phone: string): string {
  return phone.replace(/[^\d]/g, "");
}

export function host(url: string): string {
  try {
    return new URL(url).host.replace(/^www\./, "");
  } catch {
    return url;
  }
}
