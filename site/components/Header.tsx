import Link from "next/link";
import { Suspense } from "react";
import { Send } from "lucide-react";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { Mark } from "./Logo";
import { LangSwitch } from "./LangSwitch";
import { NavLinks } from "./NavLinks";

/** Floating, translucent: content scrolls under it. */
export function Header({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  const join = meta?.bot.link;

  return (
    <header className="material sticky top-0 z-40 border-b border-line/70">
      <div className="mx-auto flex h-[var(--header-h)] max-w-6xl items-center gap-4 px-4">
        <Link href={`/${lang}`} className="press flex shrink-0 items-center gap-2.5" aria-label={d.brand}>
          <Mark className="size-8" />
          <span className="flex flex-col leading-none">
            <span className="text-[0.9375rem] font-bold tracking-tight">{d.brand}</span>
            <span className="mt-0.5 text-[0.6875rem] font-medium text-muted">{d.brandNative}</span>
          </span>
        </Link>

        <div className="ml-auto flex items-center gap-2 md:ml-6 md:flex-1">
          <NavLinks lang={lang} labels={d.nav} />
          <div className="ml-auto flex items-center gap-2">
            <Suspense fallback={null}>
              <LangSwitch lang={lang} label={d.language} />
            </Suspense>
            {join && (
              <a href={join} target="_blank" rel="noopener noreferrer" className="btn btn-primary hidden min-h-9 px-3.5 text-sm sm:inline-flex">
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
