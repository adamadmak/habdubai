import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { ListingForm } from "@/components/owner/ListingForm";
import type { OwnerChoices } from "@/lib/api";
import { dict, isLocale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).business.newTitle, robots: { index: false } };
}

export default async function NewListing({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  if (!(await currentOwner(lang))) redirect(`/${lang}/business/login?next=/${lang}/business/new`);
  const d = dict(lang);
  const choices = await ownerApi<OwnerChoices>("/choices", { locale: lang });

  return (
    <div className="mx-auto max-w-2xl px-4 pt-8 md:pt-12">
      <h1 className="display text-[2rem] md:text-[2.5rem]">{d.business.newTitle}</h1>
      <p className="mt-3 text-muted">{d.business.pendingNote}</p>
      <div className="mt-8">
        <ListingForm lang={lang} b={d.business} common={d.common} choices={choices} values={null} live={false} />
      </div>
    </div>
  );
}
