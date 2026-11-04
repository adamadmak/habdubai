import { DEFAULT_LOCALE, LOCALES, type Locale } from "./locales";

/** The day the privacy notice and the terms were last changed (the date both pages show). Change it with their words. */
export const LEGAL_UPDATED = "2026-11-03";

/** This website's own public address, for metadata, the sitemap and share links. */
export function siteUrl(path = ""): string {
  const base = (process.env.SITE_URL || "http://localhost:3001").replace(/\/+$/, "");
  return path ? `${base}/${path.replace(/^\/+/, "")}` : base;
}

/** A page's own address and the same page in each language, so search engines send each reader to their own. */
export function alternatesFor(lang: Locale, path = ""): { canonical: string; languages: Record<string, string> } {
  return {
    canonical: `/${lang}${path}`,
    languages: { ...Object.fromEntries(LOCALES.map((l) => [l, `/${l}${path}`])), "x-default": `/${DEFAULT_LOCALE}${path}` },
  };
}
