import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { LegalPage } from "@/components/Legal";
import { dict, isLocale } from "@/lib/i18n";

// The dawn band is tinted by the hour in Dubai; a page with nothing to fetch would otherwise keep its first hour until the next deploy.
export const revalidate = 900;

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.terms.title, description: d.terms.lead, alternates: { canonical: `/${lang}/terms` } };
}

/** The few rules for using the website, the bot, its groups and its channel. */
export default async function Terms({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);

  return <LegalPage lang={lang} d={d} doc={d.terms} other="privacy" />;
}
