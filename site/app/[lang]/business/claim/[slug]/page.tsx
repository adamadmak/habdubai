import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ChevronLeft, MapPin, Send } from "lucide-react";
import { ClaimForm } from "@/components/owner/ClaimForm";
import { apiOrNull, type Business } from "@/lib/api";
import { dict, fill, isLocale, pick } from "@/lib/i18n";
import { currentOwner } from "@/lib/owner";

export const dynamic = "force-dynamic";

type Params = { lang: string; slug: string };

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).directory.claimCta, robots: { index: false } };
}

export default async function Claim({ params }: { params: Promise<Params> }) {
  const { lang, slug } = await params;
  if (!isLocale(lang) || !/^[a-z0-9-]{1,170}$/.test(slug)) notFound();
  const d = dict(lang);
  const data = await apiOrNull<{ business: Business }>(`/public/businesses/${slug}`, { revalidate: 30 });
  if (!data) notFound();
  const b = data.business;
  const owner = await currentOwner();

  return (
    <div className="mx-auto max-w-2xl px-4 pt-6 md:pt-10">
      <Link href={`/${lang}/directory/${b.slug}`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {b.name}
      </Link>
      <h1 className="display mt-4 text-[1.75rem] md:text-[2.25rem]">{fill(d.business.claimTitle, { name: b.name })}</h1>
      <p className="mt-2 flex items-center gap-1.5 text-muted"><MapPin className="size-4" aria-hidden="true" /> {[b.area, pick(b.city?.name, lang)].filter(Boolean).join(", ")}</p>
      <p className="mt-5">{d.business.claimText}</p>

      <div className="card mt-6 p-5">
        {b.owner_managed ? (
          <p className="text-muted">{d.common.ownerManaged}</p>
        ) : owner ? (
          <ClaimForm businessId={b.id} labels={{ label: d.business.claimLabel, submit: d.business.claimSubmit, sent: d.business.claimSent, error: d.common.errorText }} />
        ) : (
          <div>
            <p className="text-muted">{d.business.claimLogin}</p>
            <Link href={`/${lang}/business/login?next=/${lang}/business/claim/${b.slug}`} className="btn btn-primary mt-4">
              <Send className="size-4" aria-hidden="true" /> {d.business.cta}
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
