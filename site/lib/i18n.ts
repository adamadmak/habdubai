import { am } from "@/i18n/am";
import { en, type Dictionary } from "@/i18n/en";
import { ti } from "@/i18n/ti";
import type { Locale } from "./locales";

export * from "./locales";
export type { Dictionary };

const DICTIONARIES: Record<Locale, Dictionary> = { am, ti, en };

export function dict(locale: Locale): Dictionary {
  return DICTIONARIES[locale] ?? en;
}

/** "{n} open jobs" with the values filled in. */
export function fill(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) => (key in values ? String(values[key]) : `{${key}}`));
}

/** A label from the API's three-language lists, falling back to English. */
export function pick(labels: Partial<Record<Locale, string>> | undefined | null, locale: Locale): string {
  if (!labels) return "";
  return labels[locale] || labels.en || "";
}

/** The bot's category labels start with an emoji; the website shows its own icons. */
export function stripEmoji(label: string): string {
  return label.replace(/^[\p{Extended_Pictographic}\u{FE0F}\u{200D}\s]+/u, "").trim();
}

export function label(labels: Partial<Record<Locale, string>> | undefined | null, locale: Locale): string {
  return stripEmoji(pick(labels, locale));
}
