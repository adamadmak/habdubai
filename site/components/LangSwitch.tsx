"use client";
import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";
import { Check, ChevronDown, Languages } from "lucide-react";
import { LOCALES, LOCALE_NAMES, LOCALE_SHORT, swapLocale, type Locale } from "@/lib/locales";

type Props = { lang: Locale; label: string };

/** Four languages: the same page, with its filters, in the language chosen. */
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

/**
 * Four pills do not fit beside the brand on a phone, nor beside the links on
 * a tablet or a small laptop, so below 1280 px the switch is one pill that
 * opens the four languages by name. It is a plain <details>: it opens
 * without JavaScript, and with it a tap elsewhere, Escape or a choice closes
 * it. On wide screens the four sit side by side, one tap each.
 */
function LangLinks({ lang, label, href }: Props & { href: (locale: Locale) => string }) {
  const menu = useRef<HTMLDetailsElement>(null);

  useEffect(() => {
    const onPointer = (event: PointerEvent) => {
      const details = menu.current;
      if (details?.open && !details.contains(event.target as Node)) details.open = false;
    };
    const onKey = (event: KeyboardEvent) => {
      const details = menu.current;
      if (event.key !== "Escape" || !details?.open) return;
      details.open = false;
      details.querySelector("summary")?.focus();
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  const close = () => {
    if (menu.current) menu.current.open = false;
  };

  return (
    <>
      <details ref={menu} className="lang-menu relative xl:hidden">
        <summary
          aria-label={`${label}: ${LOCALE_NAMES[lang]}`}
          className="press hit flex h-10 cursor-pointer select-none items-center gap-1.5 rounded-full border border-fg/10 bg-fg/6 pl-2.5 pr-2 text-[0.8125rem] font-semibold leading-none"
        >
          <Languages className="size-4 text-muted" aria-hidden="true" />
          <span lang={lang}>{LOCALE_SHORT[lang]}</span>
          <ChevronDown className="lang-chevron size-3.5 text-muted" aria-hidden="true" />
        </summary>
        <div role="group" aria-label={label} className="lang-menu-panel absolute right-0 top-[calc(100%+0.75rem)] z-50 w-52 rounded-[1.25rem] p-1.5">
          {LOCALES.map((locale) => (
            <Link
              key={locale}
              href={href(locale)}
              hrefLang={locale}
              lang={locale}
              aria-current={locale === lang ? "true" : undefined}
              scroll={false}
              onClick={close}
              className={`press flex min-h-11 items-center justify-between gap-3 rounded-[0.875rem] px-3.5 text-[0.9375rem] font-semibold transition-colors ${
                locale === lang ? "bg-fg/8 text-fg" : "text-muted hover:bg-fg/5 hover:text-fg"
              }`}
            >
              {LOCALE_NAMES[locale]}
              {locale === lang && <Check className="size-4 shrink-0 text-primary" aria-hidden="true" />}
            </Link>
          ))}
        </div>
      </details>

      <div role="group" aria-label={label} className="hidden rounded-full border border-fg/10 bg-fg/6 p-0.5 xl:flex">
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
              locale === lang ? "bg-elevated text-fg shadow-sm" : "text-muted hover:text-fg"
            }`}
          >
            {LOCALE_SHORT[locale]}
          </Link>
        ))}
      </div>
    </>
  );
}
