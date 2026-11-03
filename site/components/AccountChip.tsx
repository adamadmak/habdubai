"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { UserRound } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Who = { name: string; member: boolean } | null;

/**
 * The member's door in the header. The server renders it as "sign in" for
 * everyone, since pages are cached for all visitors alike; once on screen it
 * asks this site's server who holds the session cookie and, for a member,
 * shows their initial and first name instead. Either way it leads to the
 * same page, which sorts out the rest.
 */
export function AccountChip({ lang, labels }: { lang: Locale; labels: { signIn: string; myPage: string } }) {
  const [who, setWho] = useState<Who | undefined>(undefined);

  useEffect(() => {
    let live = true;
    fetch("/api/auth/me", { cache: "no-store" })
      .then((response) => (response.ok ? response.json() : { owner: null }))
      .then((json: { owner: Who }) => live && setWho(json.owner ?? null))
      .catch(() => live && setWho(null));
    return () => {
      live = false;
    };
  }, []);

  const first = who?.name.trim().split(/\s+/)[0] ?? "";
  const initial = first.charAt(0).toUpperCase();

  return (
    <Link
      href={`/${lang}/account`}
      className="press inline-flex h-10 shrink-0 items-center gap-2 rounded-full bg-fg/8 pl-1 pr-3 text-sm font-semibold text-fg"
      aria-label={who ? labels.myPage : labels.signIn}
      title={who ? labels.myPage : labels.signIn}
      data-signed-in={who ? "true" : undefined}
    >
      <span className={`grid size-8 place-items-center rounded-full text-[0.8125rem] font-bold ${who ? "bg-primary text-primary-fg" : "bg-fg/10 text-fg"}`} aria-hidden="true">
        {who ? initial : <UserRound className="size-4" strokeWidth={2} />}
      </span>
      <span className="hidden max-w-[7rem] truncate sm:inline">{who ? first : labels.signIn}</span>
    </Link>
  );
}
