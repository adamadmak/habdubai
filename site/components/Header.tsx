import Link from "next/link";
import { Suspense } from "react";
import { Send } from "lucide-react";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { Mark } from "./Logo";
import { LangSwitch, LangSwitchPlain } from "./LangSwitch";
import { NavLinks } from "./NavLinks";

/** A floating island of night: the page and the night bands scroll under it. */
export function Header({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  const join = meta?.bot.link;

  return (
    <header className="sticky top-0 z-40 px-3 pt-3 md:px-4">
      <div className="chrome mx-auto flex h-[var(--header-h)] max-w-6xl items-center gap-3 rounded-full pl-2 pr-2 md:gap-5 md:pl-2.5">
        <Link href={`/${lang}`} className="press flex shrink-0 items-center gap-2.5 rounded-full pr-1" aria-label={d.brand}>
          <Mark className="size-9" />
          <span className="flex flex-col leading-none">
            <span className="text-[0.9375rem] font-bold tracking-tight">{d.brand}</span>
            {/* The name in the other script: Ge'ez on English pages, Latin on the others. */}
            <span lang={lang === "en" ? "am" : "en"} className="mt-1 text-[0.6875rem] font-medium text-white/70">{d.brandNative}</span>
          </span>
        </Link>

        <div className="ml-auto flex items-center gap-2 md:ml-0 md:flex-1">
          <NavLinks lang={lang} labels={d.nav} label={d.navLabel} />
          <div className="ml-auto flex items-center gap-2">
            <Suspense fallback={<LangSwitchPlain lang={lang} label={d.language} />}>
              <LangSwitch lang={lang} label={d.language} />
            </Suspense>
            {join && (
              <a href={join} target="_blank" rel="noopener noreferrer" className="btn btn-gold hidden min-h-10 rounded-full px-4 text-sm sm:inline-flex">
                <Send className="size-4" aria-hidden="true" />
                {d.join}
              </a>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
