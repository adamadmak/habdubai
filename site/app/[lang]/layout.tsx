import "../globals.css";
import type { Metadata, Viewport } from "next";
import { Noto_Sans_Ethiopic, Urbanist } from "next/font/google";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { FallbackCopyProvider } from "@/components/FallbackCopy";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { Motion } from "@/components/Motion";
import { TabBar } from "@/components/NavLinks";
import { PageViews } from "@/components/PageViews";
import { TelegramEntry } from "@/components/TelegramEntry";
import { api, type Meta } from "@/lib/api";
import { dict, isLocale, LOCALES } from "@/lib/i18n";
import { siteUrl } from "@/lib/site";

/** One heavy geometric family for everything Latin, headings and text alike. */
const urbanist = Urbanist({ subsets: ["latin"], variable: "--font-urbanist", display: "swap" });
/** Ge'ez script everywhere, whatever the device ships with. Variable, so it carries the heavy headings too. */
const ethiopic = Noto_Sans_Ethiopic({ subsets: ["ethiopic"], variable: "--font-ethiopic", display: "swap" });

/**
 * Reads the theme the reader chose (ThemeToggle) and sets it on <html>; nothing stored means "follow the device".
 * Inside Telegram (see lib/telegram-app.ts) nothing stored means "follow Telegram": light or dark from the background it passes.
 */
const THEME_SCRIPT =
  "try{var d=document.documentElement,t=localStorage.getItem('theme'),h=location.hash;if(t==='light'||t==='dark')d.dataset.theme=t;" +
  "var a=window.TelegramWebviewProxy||(window.external&&'notify' in window.external)||window.parent!==window;" +
  "if((a&&h.indexOf('tgWebAppData=')>-1)||sessionStorage.getItem('in-telegram')==='1'){d.dataset.telegram='1';" +
  "var m=decodeURIComponent(h).match(/\"bg_color\":\"#([0-9a-f]{6})\"/i);" +
  "if(m){var n=parseInt(m[1],16);sessionStorage.setItem('telegram-theme',(n>>16)*.299+(n>>8&255)*.587+(n&255)*.114<128?'dark':'light')}" +
  "var g=sessionStorage.getItem('telegram-theme');if(g&&!d.dataset.theme)d.dataset.theme=g}}catch(e){}";

/*
 * No generateStaticParams: nothing under /[lang] is pre-rendered at build time,
 * so a build never depends on the API being up. Pages render on the first
 * request and are cached as long as their API answers are (a minute; lib/api.ts).
 */

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#121212" },
  ],
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
    <html lang={lang} className={`${urbanist.variable} ${ethiopic.variable}`} suppressHydrationWarning>
      <body className="antialiased">
        {/* The reader's theme, applied before the first paint so a dark choice never flashes light. */}
        <script dangerouslySetInnerHTML={{ __html: THEME_SCRIPT }} />
        <a href="#main" className="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
          {d.skip}
        </a>
        <Header lang={lang} d={d} meta={meta} />
        <FallbackCopyProvider
          copy={{
            lang,
            notFoundTitle: d.common.notFoundTitle,
            notFoundText: d.common.notFoundText,
            goHome: d.common.goHome,
            errorTitle: d.common.errorTitle,
            errorText: d.common.errorText,
            retry: d.common.retry,
          }}
        >
          <main id="main">{children}</main>
        </FallbackCopyProvider>
        <Footer lang={lang} d={d} meta={meta} />
        <TabBar lang={lang} labels={d.tabs} label={d.navLabel} />
        <Motion />
        <PageViews />
        <TelegramEntry lang={lang} />
      </body>
    </html>
  );
}
