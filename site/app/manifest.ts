import type { MetadataRoute } from "next";
import { DEFAULT_LOCALE } from "@/lib/locales";

/** Installable on a phone's home screen: the mark as its icon, the brand's paper and night as its colours. */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "HabUAE",
    short_name: "HabUAE",
    description: "Jobs, Habesha places, news and the goods board for Ethiopians and Eritreans in the UAE.",
    // The root: the middleware sends an installed app to the language the reader last chose.
    start_url: "/",
    display: "standalone",
    background_color: "#ffffff",
    theme_color: "#ffffff",
    lang: DEFAULT_LOCALE,
    icons: [
      { src: "/brand/icon-192.png", sizes: "192x192", type: "image/png", purpose: "any" },
      { src: "/brand/icon-512.png", sizes: "512x512", type: "image/png", purpose: "any" },
    ],
  };
}
