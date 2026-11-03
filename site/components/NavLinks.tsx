"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Briefcase, Building2, House, Newspaper, Store } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Item = { href: string; label: string; icon: typeof House };

function items(lang: Locale, labels: Record<"home" | "jobs" | "directory" | "news" | "business", string>): Item[] {
  return [
    { href: `/${lang}`, label: labels.home, icon: House },
    { href: `/${lang}/jobs`, label: labels.jobs, icon: Briefcase },
    { href: `/${lang}/directory`, label: labels.directory, icon: Store },
    { href: `/${lang}/news`, label: labels.news, icon: Newspaper },
    { href: `/${lang}/business`, label: labels.business, icon: Building2 },
  ];
}

function isActive(pathname: string, href: string, lang: Locale): boolean {
  return href === `/${lang}` ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
}

/** The header's links on wide screens; the current one sits on a lit pill with a gold thread under it. */
export function NavLinks({ lang, labels, label }: { lang: Locale; labels: Record<"home" | "jobs" | "directory" | "news" | "business", string>; label: string }) {
  const pathname = usePathname();

  return (
    <nav aria-label={label} className="hidden items-center gap-0.5 md:flex">
      {items(lang, labels).slice(1).map((item) => {
        const active = isActive(pathname, item.href, lang);
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={`press relative rounded-full px-3.5 py-2 text-[0.9375rem] font-medium transition-colors ${active ? "bg-white/12 text-white" : "text-white/70 hover:text-white"}`}
          >
            {item.label}
            {active && <span aria-hidden="true" className="absolute inset-x-4 -bottom-px h-0.5 rounded-full bg-accent" />}
          </Link>
        );
      })}
    </nav>
  );
}

/** The phone's tab bar: a floating island of night within the thumb's reach. */
export function TabBar({ lang, labels, label }: { lang: Locale; labels: Record<"home" | "jobs" | "directory" | "news" | "business", string>; label: string }) {
  const pathname = usePathname();

  return (
    <nav aria-label={label} className="chrome fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-40 rounded-[1.75rem] md:hidden">
      <ul className="mx-auto grid h-[var(--tabbar-h)] max-w-lg grid-cols-5 px-1.5">
        {items(lang, labels).map((item) => {
          const active = isActive(pathname, item.href, lang);
          const Icon = item.icon;
          return (
            <li key={item.href} className="grid place-items-stretch py-1.5">
              <Link
                href={item.href}
                aria-current={active ? "page" : undefined}
                className={`press flex flex-col items-center justify-center gap-1 rounded-[1.25rem] text-[0.6875rem] font-semibold transition-colors ${active ? "bg-white/12 text-white" : "text-white/65"}`}
              >
                <Icon className={`size-[1.375rem] ${active ? "text-accent" : ""}`} strokeWidth={active ? 2.3 : 1.8} aria-hidden="true" />
                <span className="leading-none">{item.label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
