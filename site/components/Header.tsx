import Link from "next/link";
import { Suspense } from "react";
import { Send } from "lucide-react";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import type { Meta } from "@/lib/api";
import { AccountChip } from "./AccountChip";
import { Logo } from "./Logo";
import { LangSwitch, LangSwitchPlain } from "./LangSwitch";
import { NavLinks } from "./NavLinks";
import { ThemeToggle } from "./ThemeToggle";

/** A floating island of frosted glass: the page and its bands scroll under it. */
export function Header({ lang, d, meta }: { lang: Locale; d: Dictionary; meta: Meta | null }) {
  const join = meta?.bot.link;

  return (
    <header className="sticky top-0 z-40 px-3 pt-3 md:px-4">
      <div className="chrome mx-auto flex h-[var(--header-h)] max-w-island items-center gap-2 rounded-full pl-2 pr-2 md:gap-5 md:pl-2.5">
        <Link href={`/${lang}`} className="press flex shrink-0 items-center gap-2.5 rounded-full pr-1" aria-label={d.brand}>
          {/* On a tablet the links take the room, and on the smallest phones so do the controls: the H alone there. */}
          <Logo className="text-[2.25rem]" wordClass="hidden min-[350px]:block md:max-lg:hidden" />
        </Link>

        <div className="ml-auto flex items-center gap-2 md:ml-0 md:flex-1">
          <NavLinks lang={lang} labels={d.nav} label={d.navLabel} />
          <div className="ml-auto flex items-center gap-1.5 md:gap-2">
            <Suspense fallback={<LangSwitchPlain lang={lang} label={d.language} />}>
              <LangSwitch lang={lang} label={d.language} />
            </Suspense>
            {/* The first thing to give way on a small phone: it follows the device there. */}
            <span className="contents max-[374px]:hidden"><ThemeToggle labels={d.theme} /></span>
            <AccountChip lang={lang} labels={{ signIn: d.account.signIn, myPage: d.account.myPage }} />
            {join && (
              // On a tablet the links take the room, so the button keeps only its icon there (its words stay for screen readers).
              <a href={join} target="_blank" rel="noopener noreferrer" className="join-cta btn btn-gold hidden min-h-10 rounded-full px-4 text-sm sm:inline-flex md:max-lg:px-3">
                <Send className="size-4" aria-hidden="true" />
                <span className="md:max-lg:sr-only">{d.join}</span>
              </a>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
