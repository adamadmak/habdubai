import "../globals.css";
import type { Metadata, Viewport } from "next";
import { Fraunces, Noto_Sans_Ethiopic, Noto_Serif_Ethiopic } from "next/font/google";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { Motion } from "@/components/Motion";
import { TabBar } from "@/components/NavLinks";
import { api, type Meta } from "@/lib/api";
import { dict, isLocale, LOCALES } from "@/lib/i18n";
import { siteUrl } from "@/lib/site";

/** Ge'ez script everywhere, whatever the device ships with. Latin text uses the system font. */
const ethiopic = Noto_Sans_Ethiopic({ subsets: ["ethiopic"], variable: "--font-ethiopic", display: "swap" });
/** Headings: Fraunces for Latin, Noto Serif Ethiopic for Ge'ez, so a heading reads like print in both scripts. */
const fraunces = Fraunces({ subsets: ["latin"], variable: "--font-fraunces", display: "swap", axes: ["SOFT", "WONK", "opsz"] });
const ethiopicSerif = Noto_Serif_Ethiopic({ subsets: ["ethiopic"], variable: "--font-ethiopic-serif", display: "swap", weight: ["600", "700"] });

/*
 * No generateStaticParams: nothing under /[lang] is pre-rendered at build time,
 * so a build never depends on the API being up. Pages render on the first
 * request and are cached as long as their API answers are (a minute; lib/api.ts).
 */

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  // The night of the header and the hero, in both schemes.
  themeColor: "#0a1433",
};

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);

  return {
    metadataBase: new URL(siteUrl()),
    title: { default: `${d.brand} · ${d.home.title}`, template: `%s · ${d.brand}` },
    description: d.home.lead,
    applicationName: d.brand,
    alternates: { languages: Object.fromEntries(LOCALES.map((l) => [l, `/${l}`])) },
    openGraph: { siteName: d.brand, type: "website", locale: lang },
    formatDetection: { telephone: false },
  };
}

export default async function LangLayout({ children, params }: { children: ReactNode; params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();

  const d = dict(lang);
  // The links in the header and footer; the pages still render if the API is briefly away.
  const meta = await api<Meta>("/public/meta", { revalidate: 300 }).catch(() => null);

  return (
    <html lang={lang} className={`${ethiopic.variable} ${fraunces.variable} ${ethiopicSerif.variable}`}>
      <body className="antialiased">
        <a href="#main" className="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
          {d.skip}
        </a>
        <Header lang={lang} d={d} meta={meta} />
        <main id="main">{children}</main>
        <Footer lang={lang} d={d} meta={meta} />
        <TabBar lang={lang} labels={d.tabs} />
        <Motion />
      </body>
    </html>
  );
}
