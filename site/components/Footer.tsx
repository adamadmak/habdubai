import Link from "next/link";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { Mark, Tibeb } from "./Logo";

export function Footer({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  return (
    <footer className="mt-16 border-t border-line bg-surface">
      <Tibeb thin />
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-[1.4fr_1fr_1fr]">
        <div className="max-w-sm">
          <div className="flex items-center gap-2.5">
            <Mark className="size-7" />
            <span className="font-bold">{d.brand}</span>
          </div>
          <p className="mt-4 text-sm text-muted">{d.footer.tagline}</p>
          <p className="mt-3 text-xs text-faint">{d.footer.caution}</p>
        </div>
        <nav aria-label={d.brand} className="flex flex-col gap-2.5 text-sm">
          <Link className="hover:text-primary" href={`/${lang}/jobs`}>{d.nav.jobs}</Link>
          <Link className="hover:text-primary" href={`/${lang}/directory`}>{d.nav.directory}</Link>
          <Link className="hover:text-primary" href={`/${lang}/news`}>{d.nav.news}</Link>
          <Link className="hover:text-primary" href={`/${lang}/about`}>{d.nav.about}</Link>
        </nav>
        <div className="flex flex-col gap-2.5 text-sm">
          <Link className="hover:text-primary" href={`/${lang}/business`}>{d.footer.owners}</Link>
          <Link className="hover:text-primary" href={`/${lang}/about#rules`}>{d.footer.rules}</Link>
          {meta?.bot.link && <a className="hover:text-primary" href={meta.bot.link} target="_blank" rel="noopener noreferrer">{d.footer.bot}</a>}
          {meta?.channel && <a className="hover:text-primary" href={meta.channel} target="_blank" rel="noopener noreferrer">{d.footer.channel}</a>}
        </div>
      </div>
      <div className="mx-auto max-w-6xl px-4 pb-10 text-xs text-faint">© {new Date().getFullYear()} {d.brand}</div>
    </footer>
  );
}
