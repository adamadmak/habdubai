import Link from "next/link";
import { Send } from "lucide-react";
import type { Dictionary } from "@/lib/i18n";
import { isGeez, type Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { Mark, Tibeb } from "./Logo";

/** The page ends on the ink band, under the thread: who we are, then two columns of links. */
export function Footer({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  const link = "text-white/75 transition-colors hover:text-white";
  const heading = "text-xs font-bold uppercase tracking-wide text-white/50";

  return (
    <footer className="mt-20 bg-night text-white md:mt-28">
      <Tibeb />
      <div className="mx-auto grid max-w-page gap-10 px-4 pb-12 pt-14 md:grid-cols-[1.6fr_1fr_1fr] lg:px-8">
        <div className="max-w-md">
          <div className="flex items-center gap-2.5">
            <Mark className="size-9" />
            <span className="flex flex-col leading-none">
              <span className="text-lg font-black tracking-tight">{d.brand}</span>
              <span lang={isGeez(lang) ? "en" : "am"} className="mt-1 text-xs text-white/70">{d.brandNative}</span>
            </span>
          </div>
          <p className="mt-5 text-sm text-white/75">{d.footer.tagline}</p>
          <p className="mt-3 text-xs text-white/70">{d.footer.caution}</p>
          {meta?.bot.link && (
            <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-gold mt-6">
              <Send className="size-4" aria-hidden="true" /> {d.join}
            </a>
          )}
        </div>
        <nav aria-label={d.brand} className="flex flex-col gap-3 text-sm">
          <p className={heading}>{d.footer.explore}</p>
          <Link className={link} href={`/${lang}/jobs`}>{d.nav.jobs}</Link>
          <Link className={link} href={`/${lang}/directory`}>{d.nav.directory}</Link>
          <Link className={link} href={`/${lang}/news`}>{d.nav.news}</Link>
          <Link className={link} href={`/${lang}/goods`}>{d.nav.goods}</Link>
          <Link className={link} href={`/${lang}/about`}>{d.nav.about}</Link>
        </nav>
        <div className="flex flex-col gap-3 text-sm">
          <p className={heading}>{d.footer.community}</p>
          <Link className={link} href={`/${lang}/business`}>{d.footer.owners}</Link>
          <Link className={link} href={`/${lang}/about#rules`}>{d.footer.rules}</Link>
          {meta?.bot.link && <a className={link} href={meta.bot.link} target="_blank" rel="noopener noreferrer">{d.footer.bot}</a>}
          {meta?.channel && <a className={link} href={meta.channel} target="_blank" rel="noopener noreferrer">{d.footer.channel}</a>}
        </div>
      </div>
      <div className="mx-auto flex max-w-page flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 px-4 pb-[calc(var(--tabbar-space)+1.5rem)] pt-6 text-xs text-white/70 md:pb-8 lg:px-8">
        <span>© {new Date().getFullYear()} {d.brand}</span>
        <Link className="transition-colors hover:text-white" href={`/${lang}/privacy`}>{d.legal.privacyLink}</Link>
        <Link className="transition-colors hover:text-white" href={`/${lang}/terms`}>{d.legal.termsLink}</Link>
      </div>
    </footer>
  );
}
