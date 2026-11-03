import type { Locale } from "./locales";

/*
 * Labels from the API's language lists. On their own, with no dictionaries
 * attached, so a client component (the goods form's live preview) can use
 * them without shipping every language.
 *
 * The API answers {am, ti, en} and is gaining `om`: until a list carries it
 * (and for any text nobody translated), the English one stands in, so nothing
 * renders empty.
 */
type Texts = Partial<Record<Locale, string | null>> | undefined | null;

/** A label or text in the reader's language, falling back to English. */
export function pick(labels: Texts, locale: Locale): string {
  if (!labels) return "";
  return labels[locale] || labels.en || "";
}

/** The language pick() actually answers in, for the `lang` attribute of what it returned. */
export function pickLang(labels: Texts, locale: Locale): Locale {
  return labels?.[locale] ? locale : "en";
}

/** The bot's category labels start with an emoji; the website shows its own icons. */
export function stripEmoji(label: string): string {
  return label.replace(/^[\p{Extended_Pictographic}\u{FE0F}\u{200D}\s]+/u, "").trim();
}

export function label(labels: Texts, locale: Locale): string {
  return stripEmoji(pick(labels, locale));
}
