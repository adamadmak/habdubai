/** This website's own public address, for metadata, the sitemap and share links. */
export function siteUrl(path = ""): string {
  const base = (process.env.SITE_URL || "http://localhost:3001").replace(/\/+$/, "");
  return path ? `${base}/${path.replace(/^\/+/, "")}` : base;
}
