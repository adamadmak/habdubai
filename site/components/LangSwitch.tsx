"use client";
import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { LOCALES, LOCALE_NAMES, LOCALE_SHORT, swapLocale, type Locale } from "@/lib/locales";

type Props = { lang: Locale; label: string };

/** Three languages, one tap each: the same page, with its filters, in the language chosen. */
export function LangSwitch({ lang, label }: Props) {
  const pathname = usePathname();
  const qs = useSearchParams().toString();

  return <LangLinks lang={lang} label={label} href={(locale) => swapLocale(pathname, locale) + (qs ? `?${qs}` : "")} />;
}

/**
 * The same links without the query: LangSwitch's Suspense fallback, so the
 * switch is in the server's HTML and works before (or without) JavaScript.
 */
export function LangSwitchPlain({ lang, label }: Props) {
  const pathname = usePathname();

  return <LangLinks lang={lang} label={label} href={(locale) => swapLocale(pathname, locale)} />;
}

function LangLinks({ lang, label, href }: Props & { href: (locale: Locale) => string }) {
  return (
    <div role="group" aria-label={label} className="flex rounded-full bg-white/8 p-0.5 ring-1 ring-white/10">
      {LOCALES.map((locale) => (
        <Link
          key={locale}
          href={href(locale)}
          hrefLang={locale}
          lang={locale}
          title={LOCALE_NAMES[locale]}
          aria-current={locale === lang ? "true" : undefined}
          scroll={false}
          className={`press hit rounded-full px-2.5 py-1.5 text-[0.8125rem] font-semibold leading-none transition-colors ${
            locale === lang ? "bg-white text-night shadow-sm" : "text-white/75 hover:text-white"
          }`}
        >
          {LOCALE_SHORT[locale]}
        </Link>
      ))}
    </div>
  );
}
