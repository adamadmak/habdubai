import type { Locale } from "./locales";

/*
 * Labels from the API's three-language lists. On their own, with no
 * dictionaries attached, so a client component (the goods form's live
 * preview) can use them without shipping all three.
 */

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
