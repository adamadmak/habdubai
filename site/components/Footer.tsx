import Link from "next/link";
import { Send } from "lucide-react";
import type { Dictionary } from "@/lib/i18n";
import { isGeez, type Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { Mark, Tibeb } from "./Logo";

/** The page ends where it began: at night, under the thread. */
export function Footer({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  const link = "text-white/75 transition-colors hover:text-white";

  return (
    <footer className="night mt-20 md:mt-28">
      <Tibeb />
      <span aria-hidden="true" className="sky-word absolute -bottom-10 -right-4 text-[9rem] md:-bottom-16 md:text-[16rem]">ሐበሻ</span>
      <div className="relative mx-auto grid max-w-7xl gap-10 px-4 pb-12 pt-14 md:grid-cols-[1.5fr_1fr_1fr]">
        <div className="max-w-sm">
          <div className="flex items-center gap-2.5">
            <Mark className="size-9" />
            <span className="flex flex-col leading-none">
              <span className="font-bold">{d.brand}</span>
              <span lang={isGeez(lang) ? "en" : "am"} className="mt-1 text-xs text-white/70">{d.brandNative}</span>
            </span>
          </div>
          <p className="mt-5 text-sm text-white/75">{d.footer.tagline}</p>
          <p className="mt-3 text-xs text-white/75">{d.footer.caution}</p>
          {meta?.bot.link && (
            <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-gold mt-6 rounded-full">
              <Send className="size-4" aria-hidden="true" /> {d.join}
            </a>
          )}
        </div>
        <nav aria-label={d.brand} className="flex flex-col gap-3 text-sm">
          <Link className={link} href={`/${lang}/jobs`}>{d.nav.jobs}</Link>
          <Link className={link} href={`/${lang}/directory`}>{d.nav.directory}</Link>
          <Link className={link} href={`/${lang}/news`}>{d.nav.news}</Link>
          <Link className={link} href={`/${lang}/goods`}>{d.nav.goods}</Link>
          <Link className={link} href={`/${lang}/about`}>{d.nav.about}</Link>
        </nav>
        <div className="flex flex-col gap-3 text-sm">
          <Link className={link} href={`/${lang}/business`}>{d.footer.owners}</Link>
          <Link className={link} href={`/${lang}/about#rules`}>{d.footer.rules}</Link>
          {meta?.bot.link && <a className={link} href={meta.bot.link} target="_blank" rel="noopener noreferrer">{d.footer.bot}</a>}
          {meta?.channel && <a className={link} href={meta.channel} target="_blank" rel="noopener noreferrer">{d.footer.channel}</a>}
        </div>
      </div>
      {/* At least 4.5:1 even where the dusk glow lightens the band. */}
      <div className="relative mx-auto flex max-w-7xl flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 px-4 pb-[calc(var(--tabbar-space)+1.5rem)] pt-6 text-xs text-white/75 md:pb-10">
        <span>© {new Date().getFullYear()} {d.brand}</span>
        <Link className="transition-colors hover:text-white" href={`/${lang}/privacy`}>{d.legal.privacyLink}</Link>
        <Link className="transition-colors hover:text-white" href={`/${lang}/terms`}>{d.legal.termsLink}</Link>
      </div>
    </footer>
  );
}
