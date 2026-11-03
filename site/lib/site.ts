/** The day the privacy notice and the terms were last changed (the date both pages show). Change it with their words. */
export const LEGAL_UPDATED = "2026-11-03";

/** This website's own public address, for metadata, the sitemap and share links. */
export function siteUrl(path = ""): string {
  const base = (process.env.SITE_URL || "http://localhost:3001").replace(/\/+$/, "");
  return path ? `${base}/${path.replace(/^\/+/, "")}` : base;
}
