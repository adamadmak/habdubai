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

/** The header's links on wide screens. */
export function NavLinks({ lang, labels }: { lang: Locale; labels: Record<"home" | "jobs" | "directory" | "news" | "business", string> }) {
  const pathname = usePathname();

  return (
    <nav aria-label="Main" className="hidden md:flex items-center gap-1">
      {items(lang, labels).slice(1).map((item) => {
        const active = isActive(pathname, item.href, lang);
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={`press rounded-full px-3.5 py-2 text-[0.9375rem] font-medium transition-colors ${active ? "bg-surface text-fg" : "text-muted hover:text-fg"}`}
          >
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

/** The bottom tab bar on phones: thumb's reach, always there. */
export function TabBar({ lang, labels }: { lang: Locale; labels: Record<"home" | "jobs" | "directory" | "news" | "business", string> }) {
  const pathname = usePathname();

  return (
    <nav
      aria-label="Main"
      className="material fixed inset-x-0 bottom-0 z-40 border-t border-line md:hidden"
      style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
    >
      <ul className="mx-auto grid h-[var(--tabbar-h)] max-w-lg grid-cols-5">
        {items(lang, labels).map((item) => {
          const active = isActive(pathname, item.href, lang);
          const Icon = item.icon;
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                aria-current={active ? "page" : undefined}
                className={`press flex h-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-semibold ${active ? "text-primary" : "text-muted"}`}
              >
                <Icon className="size-[1.375rem]" strokeWidth={active ? 2.3 : 1.8} aria-hidden="true" />
                <span className="leading-none">{item.label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
