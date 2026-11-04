import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { SignInBody } from "@/components/owner/SignInBody";
import { PageHead } from "@/components/Bits";
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
  if (await currentOwner(lang)) redirect(next);
  const d = dict(lang);

  return (
    <>
      <PageHead title={d.business.loginTitle} />
      <SignInBody lang={lang} d={d} next={next} />
    </>
  );
}
