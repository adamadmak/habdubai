import type { Locale } from "./i18n";

/** Intl locale tags: Amharic and Tigrinya with their country so month names are right. */
const TAG: Record<Locale, string> = { am: "am-ET", ti: "ti-ER", en: "en-GB" };
const TZ = "Asia/Dubai";

/** A date-only value (2026-11-20) at noon in Dubai, so no timezone moves it a day. */
function toDate(value: string | Date): Date {
  if (value instanceof Date) return value;
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T12:00:00+04:00`) : new Date(value);
}

export function formatDate(value: string | Date | null | undefined, locale: Locale, style: "long" | "medium" = "long"): string {
  if (!value) return "";
  return new Intl.DateTimeFormat(TAG[locale], { dateStyle: style, timeZone: TZ }).format(toDate(value));
}

/** The same day in the Ethiopian calendar, which most of our readers also live by: "11 ኅዳር 2019 ዓ.ም.". */
export function ethiopianDate(value: string | Date | null | undefined, locale: Locale): string {
  if (!value) return "";
  const tag = locale === "ti" ? "ti-ER-u-ca-ethiopic" : locale === "am" ? "am-ET-u-ca-ethiopic" : "en-u-ca-ethiopic";
  const text = new Intl.DateTimeFormat(tag, { day: "numeric", month: "long", year: "numeric", timeZone: TZ }).format(toDate(value));
  return locale === "en" ? text.replace(/\s*AM$/, " (Ethiopian calendar)") : text.replace(/\s*AM$/, " ዓ.ም.");
}

export function weekday(value: string | Date, locale: Locale): string {
  return new Intl.DateTimeFormat(TAG[locale], { weekday: "long", timeZone: TZ }).format(toDate(value));
}

export function dayAndMonth(value: string | Date, locale: Locale): { day: string; month: string } {
  const date = toDate(value);
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

/** Days from today (Dubai) to a date: 0 today, 1 tomorrow. */
export function daysUntil(value: string, now: Date = new Date()): number {
  const today = new Intl.DateTimeFormat("en-CA", { timeZone: TZ }).format(now);
  return Math.round((toDate(value).getTime() - toDate(today).getTime()) / 86400000);
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
