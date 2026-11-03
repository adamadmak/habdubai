import type { MetadataRoute } from "next";
import { siteUrl } from "@/lib/site";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/api/", "/*/business/login", "/*/business/new", "/*/business/claim/"] }],
    sitemap: siteUrl("sitemap.xml"),
  };
}
