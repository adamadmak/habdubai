"use client";
import Link from "@/components/AppLink";
import { useEffect, useState } from "react";
import { telegramSignIn } from "@/lib/telegram-app";
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
    const ask = () =>
      fetch("/api/auth/me", { cache: "no-store" })
        .then((response) => (response.ok ? response.json() : { owner: null }))
        .then((json: { owner: Who }) => live && setWho(json.owner ?? null))
        .catch(() => live && setWho(null));
    // After the sign-in from Telegram, if there is one: a stale cookie checked at the same moment could be cleared after the new one is set.
    void telegramSignIn(lang).finally(() => void ask());
    // Signed in on this very page, by opening it inside Telegram.
    window.addEventListener("hd:signed-in", ask);
    return () => {
      live = false;
      window.removeEventListener("hd:signed-in", ask);
    };
  }, [lang]);

  const first = who?.name.trim().split(/\s+/)[0] ?? "";
  const initial = first.charAt(0).toUpperCase();

  return (
    <Link
      href={`/${lang}/account`}
      className="press inline-flex h-10 shrink-0 items-center gap-2 rounded-full bg-fg/8 pl-1 pr-1 text-sm font-semibold text-fg xl:pr-3"
      aria-label={who ? labels.myPage : labels.signIn}
      title={who ? labels.myPage : labels.signIn}
      data-signed-in={who ? "true" : undefined}
    >
      <span className={`grid size-8 place-items-center rounded-full text-[0.8125rem] font-bold ${who ? "bg-primary text-primary-fg" : "bg-fg/10 text-fg"}`} aria-hidden="true">
        {who ? initial : <UserRound className="size-4" strokeWidth={2} />}
      </span>
      {/* No word until the answer is in: a member never sees "sign in" flash before their own name. */}
      {who !== undefined && <span className="hidden max-w-[7rem] truncate xl:inline">{who ? first : labels.signIn}</span>}
    </Link>
  );
}
