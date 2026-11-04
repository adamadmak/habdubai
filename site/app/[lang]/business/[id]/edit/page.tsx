import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { ChevronLeft, CircleCheck } from "lucide-react";
import { ListingForm } from "@/components/owner/ListingForm";
import { PhotoUpload } from "@/components/owner/PhotoUpload";
import { StatusPill } from "@/components/owner/StatusPill";
import { ApiError, type OwnerBusiness, type OwnerChoices } from "@/lib/api";
import { dict, fill, isLocale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

type Params = { lang: string; id: string };

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).business.edit, robots: { index: false } };
}

export default async function EditListing({ params, searchParams }: { params: Promise<Params>; searchParams: Promise<{ new?: string }> }) {
  const { lang, id } = await params;
  if (!isLocale(lang) || !/^\d+$/.test(id)) notFound();
  if (!(await currentOwner(lang))) redirect(`/${lang}/business/login?next=/${lang}/business/${id}/edit`);
  const d = dict(lang);
  const b = d.business;

  let business: OwnerBusiness;
  let choices: OwnerChoices;
  try {
    [{ business }, choices] = await Promise.all([ownerApi<{ business: OwnerBusiness }>(`/businesses/${id}`, { locale: lang }), ownerApi<OwnerChoices>("/choices", { locale: lang })]);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const live = business.status === "approved" || business.status === "hidden";
  // A live listing's form shows what the owner asked for, even while it waits for review.
  const values = { ...business, ...(business.pending_changes ?? {}) } as OwnerBusiness;
  const justCreated = (await searchParams).new === "1";

  return (
    <div className="mx-auto max-w-7xl px-4 pt-6 md:pt-10 lg:px-8">
      <Link href={`/${lang}/business`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {b.yourListings}
      </Link>
      <div className="mt-4 flex flex-wrap items-center gap-3">
        <h1 className="display text-[1.75rem] md:text-[2.25rem]">{fill(b.editTitle, { name: business.name })}</h1>
        <StatusPill status={business.status} text={b.status[business.status]} />
      </div>
      {business.review_note && business.status === "rejected" && <p className="mt-3 rounded-xl bg-danger-soft p-3 text-sm">{fill(b.reason, { note: business.review_note })}</p>}

      {justCreated && (
        <p className="mt-5 flex items-center gap-2 rounded-2xl bg-success-soft p-4 text-sm font-medium text-success" role="status">
          <CircleCheck className="size-5 shrink-0" aria-hidden="true" /> {b.savedNew}
        </p>
      )}

      {/* The photo first on a phone; beside the form, and in view as it scrolls, on a wide screen. */}
      <div className="mt-6 flex flex-col gap-8 lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-12">
        <section className="card p-5 lg:sticky lg:top-[calc(var(--header-space)+1.5rem)] lg:col-start-2 lg:row-start-1">
          <h2 className="title mb-4 text-lg">{b.sections.photo}</h2>
          <PhotoUpload
            id={business.id}
            lang={lang}
            live={business.photo}
            pending={business.pending_photo}
            sendHeld={justCreated}
            labels={{ ...b.photo, retry: d.common.retry, inReview: b.photoInReview, network: b.network, server: b.serverError, sessionExpired: b.sessionExpired, signInAgain: b.signInAgain }}
          />
        </section>

        <div className="min-w-0 lg:col-start-1 lg:row-start-1">
          <ListingForm lang={lang} b={b} common={d.common} choices={choices} values={values} live={live} />
        </div>
      </div>
    </div>
  );
}
