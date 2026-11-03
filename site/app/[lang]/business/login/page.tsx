import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { LoginPanel } from "@/components/owner/LoginPanel";
import { dict, isLocale } from "@/lib/i18n";
import { currentOwner } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).business.loginTitle, robots: { index: false } };
}

/** Where to go after signing in: a page of this site in the same language, or the owner's dashboard. */
function safeNext(next: string | undefined, lang: string): string {
  return next && next.startsWith(`/${lang}/`) && !next.startsWith("//") ? next : `/${lang}/business`;
}

export default async function Login({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<{ next?: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const next = safeNext((await searchParams).next, lang);
  if (await currentOwner()) redirect(next);
  const b = dict(lang).business;

  return (
    <div className="mx-auto max-w-2xl px-4 pt-10 md:pt-16">
      <h1 className="display text-[2rem] md:text-[2.5rem]">{b.loginTitle}</h1>
      <div className="mt-8">
        <LoginPanel
          next={next}
          labels={{
            phone: b.loginPhone,
            desktop: b.loginDesktop,
            codeLabel: b.codeLabel,
            open: b.openTelegram,
            waiting: b.waiting,
            expired: b.expired,
            declined: b.declined,
            retry: b.retry,
            done: b.done,
            unavailable: b.unavailable,
            privacy: b.privacy,
          }}
        />
      </div>
    </div>
  );
}
