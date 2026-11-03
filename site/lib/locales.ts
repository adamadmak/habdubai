/** Language codes and helpers with no dictionaries attached, so the middleware stays small. */
export const LOCALES = ["am", "ti", "en"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";
export const LOCALE_COOKIE = "hd_lang";

export const LOCALE_NAMES: Record<Locale, string> = { am: "አማርኛ", ti: "ትግርኛ", en: "English" };
export const LOCALE_SHORT: Record<Locale, string> = { am: "አማ", ti: "ትግ", en: "EN" };

export function isLocale(value: unknown): value is Locale {
  return typeof value === "string" && (LOCALES as readonly string[]).includes(value);
}

/** The same page in another language. */
export function swapLocale(pathname: string, locale: Locale): string {
  const parts = pathname.split("/");
  if (isLocale(parts[1])) {
    parts[1] = locale;
    return parts.join("/") || `/${locale}`;
  }
  return `/${locale}${pathname === "/" ? "" : pathname}`;
}

/** Best language from Accept-Language: Amharic or Tigrinya when asked for, otherwise English. */
export function fromAcceptLanguage(header: string | null): Locale {
  if (!header) return DEFAULT_LOCALE;
  const ranked = header
    .split(",")
    .map((part) => {
      const [tag, q] = part.trim().split(";q=");
      return { tag: tag.toLowerCase(), q: q ? Number(q) : 1 };
    })
    .sort((a, b) => b.q - a.q);
  for (const { tag } of ranked) {
    const base = tag.split("-")[0];
    if (isLocale(base)) return base;
  }
  return DEFAULT_LOCALE;
}
