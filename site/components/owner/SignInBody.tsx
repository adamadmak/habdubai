import Link from "next/link";
import { GEEZ } from "@/components/Bits";
import { LoginPanel } from "@/components/owner/LoginPanel";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";

/**
 * The sign-in, and beside it what signing in gives: the same page wherever a
 * member arrives from (My Habesha, the business pages, a goods post).
 */
export function SignInBody({ lang, d, next }: { lang: Locale; d: Dictionary; next: string }) {
  const a = d.account;
  const b = d.business;

  return (
    <div className="mx-auto grid max-w-page gap-8 px-4 md:grid-cols-[1fr_0.9fr] md:gap-12 lg:px-8">
      <div className="mt-8">
        <LoginPanel
          lang={lang}
          next={next}
          labels={{
            phone: b.loginPhone, desktop: b.loginDesktop, open: b.openTelegram, waiting: b.waiting, opening: b.opening, framed: b.framed, openTab: b.openTab, codeTitle: b.codeTitle, codeHint: b.codeHint,
            codeSubmit: b.codeSubmit, codeWrong: b.codeWrong, locked: b.locked, busy: b.busy, expired: b.expired, declined: b.declined,
            retry: b.retry, done: b.done, unavailable: b.unavailable, privacy: b.privacy, network: b.network,
          }}
        />
        <p className="mt-6 text-sm text-muted">
          {d.legal.beforeSignIn}{" "}
          <Link href={`/${lang}/terms`} className="font-semibold text-primary">{d.legal.termsLink}</Link>
          <span aria-hidden="true"> · </span>
          <Link href={`/${lang}/privacy`} className="font-semibold text-primary">{d.legal.privacyLink}</Link>
        </p>
      </div>

      {/* What the page holds once they are in. */}
      <ol className="reveal mt-2 grid gap-3 sm:grid-cols-2 md:mt-8 md:grid-cols-1">
        {a.perks.map((perk, i) => (
          <li key={perk.title} className="card relative overflow-hidden p-5">
            <span className="geez-mark" aria-hidden="true">{GEEZ[i]}</span>
            <h2 className="headline mt-3 text-[1.125rem]">{perk.title}</h2>
            <p className="mt-1 text-sm text-muted">{perk.text}</p>
          </li>
        ))}
      </ol>
    </div>
  );
}
