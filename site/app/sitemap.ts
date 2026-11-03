import type { MetadataRoute } from "next";
import { api, type Business, type Job, type Page } from "@/lib/api";
import { LOCALES } from "@/lib/locales";
import { siteUrl } from "@/lib/site";

export const revalidate = 3600;

async function all<T>(path: string, maxPages: number): Promise<T[]> {
  const items: T[] = [];
  for (let page = 1; page <= maxPages; page++) {
    const result = await api<Page<T>>(`${path}${path.includes("?") ? "&" : "?"}page=${page}`, { revalidate: 3600 }).catch(() => null);
    if (!result) break;
    items.push(...result.data);
    if (page >= result.meta.last_page) break;
  }
  return items;
}

/** Every page in all three languages, each pointing at its two siblings. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [businesses, jobs] = await Promise.all([all<Business>("/public/businesses", 40), all<Job>("/public/jobs", 10)]);

  const paths: { path: string; changeFrequency: "daily" | "weekly"; priority: number; modified?: string }[] = [
    { path: "", changeFrequency: "daily", priority: 1 },
    { path: "/jobs", changeFrequency: "daily", priority: 0.9 },
    { path: "/directory", changeFrequency: "daily", priority: 0.9 },
    { path: "/news", changeFrequency: "daily", priority: 0.8 },
    { path: "/business", changeFrequency: "weekly", priority: 0.6 },
    { path: "/about", changeFrequency: "weekly", priority: 0.4 },
    ...businesses.map((b) => ({ path: `/directory/${b.slug}`, changeFrequency: "weekly" as const, priority: 0.7 })),
    ...jobs.map((j) => ({ path: `/jobs/${j.id}`, changeFrequency: "weekly" as const, priority: 0.5, modified: j.published_at ?? undefined })),
  ];

  return paths.flatMap(({ path, changeFrequency, priority, modified }) =>
    LOCALES.map((lang) => ({
      url: siteUrl(`${lang}${path}`),
      lastModified: modified ? new Date(modified) : undefined,
      changeFrequency,
      priority,
      alternates: { languages: Object.fromEntries(LOCALES.map((l) => [l, siteUrl(`${l}${path}`)])) },
    })),
  );
}
