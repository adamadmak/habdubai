import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { ListingForm } from "@/components/owner/ListingForm";
import { PhotoUpload } from "@/components/owner/PhotoUpload";
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

  const b = d.business;

  return (
    <div className="mx-auto max-w-7xl px-4 pt-8 md:pt-12 lg:px-8">
      <h1 className="display text-[2rem] md:text-[2.5rem]">{b.newTitle}</h1>
      <p className="mt-3 max-w-[70ch] text-muted">{b.pendingNote}</p>

      {/* The photo first on a phone; beside the form, and in view as it scrolls, on a wide screen. */}
      <div className="mt-8 flex flex-col gap-8 lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-12">
        <section className="card p-5 lg:sticky lg:top-[calc(var(--header-space)+1.5rem)] lg:col-start-2 lg:row-start-1">
          <h2 className="title mb-4 text-lg">{b.sections.photo}</h2>
          <PhotoUpload lang={lang} labels={{ ...b.photo, retry: d.common.retry, inReview: b.photoInReview, network: b.network, server: b.serverError, sessionExpired: b.sessionExpired, signInAgain: b.signInAgain }} />
        </section>

        <div className="min-w-0 lg:col-start-1 lg:row-start-1">
          <ListingForm lang={lang} b={b} common={d.common} choices={choices} values={null} live={false} />
        </div>
      </div>
    </div>
  );
}
