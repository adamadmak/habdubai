import type { MetadataRoute } from "next";
import { DEFAULT_LOCALE } from "@/lib/locales";

/** Installable on a phone's home screen: the mark as its icon, the brand's paper and night as its colours. */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Habesha Dubai",
    short_name: "Habesha Dubai",
    description: "Jobs, Habesha places, news and the goods board for Ethiopians and Eritreans in the UAE.",
    // The root: the middleware sends an installed app to the language the reader last chose.
    start_url: "/",
    display: "standalone",
    background_color: "#fbf8f2",
    theme_color: "#fbf8f2",
    lang: DEFAULT_LOCALE,
    icons: [{ src: "/icon.svg", sizes: "any", type: "image/svg+xml", purpose: "any" }],
  };
}
