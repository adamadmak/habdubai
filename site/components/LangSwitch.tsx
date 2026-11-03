"use client";
import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { LOCALES, LOCALE_NAMES, LOCALE_SHORT, swapLocale, type Locale } from "@/lib/locales";

/** Three languages, one tap each: the same page, in the language chosen. */
export function LangSwitch({ lang, label }: { lang: Locale; label: string }) {
  const pathname = usePathname();
  const params = useSearchParams();
  const qs = params.toString();

  return (
    <div role="group" aria-label={label} className="flex rounded-full bg-white/8 p-0.5 ring-1 ring-white/10">
      {LOCALES.map((locale) => (
        <Link
          key={locale}
          href={swapLocale(pathname, locale) + (qs ? `?${qs}` : "")}
          hrefLang={locale}
          lang={locale}
          title={LOCALE_NAMES[locale]}
          aria-current={locale === lang ? "true" : undefined}
          scroll={false}
          className={`press rounded-full px-2.5 py-1.5 text-[0.8125rem] font-semibold leading-none transition-colors ${
            locale === lang ? "bg-white text-night shadow-sm" : "text-white/65 hover:text-white"
          }`}
        >
          {LOCALE_SHORT[locale]}
        </Link>
      ))}
    </div>
  );
}
