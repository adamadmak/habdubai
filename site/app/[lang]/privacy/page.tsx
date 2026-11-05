import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { LegalPage } from "@/components/Legal";
import { dict, isLocale } from "@/lib/i18n";
import { alternatesFor } from "@/lib/site";

export const revalidate = 900;

// Built on the first visit and kept, instead of being rebuilt for every visitor. No build ever needs the API: the list is empty.
export function generateStaticParams() {
  return [];
}

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.privacy.title, description: d.privacy.lead, alternates: alternatesFor(lang, `/privacy`) };
}

/** What we keep about members, owners and visitors. Every claim in it is checked against the API's code. */
export default async function Privacy({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);

  return <LegalPage lang={lang} d={d} doc={d.privacy} other="terms" />;
}
