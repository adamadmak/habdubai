import type { Metadata } from "next";
import Link from "@/components/AppLink";
import { notFound, redirect } from "next/navigation";
import { ChevronLeft, Hourglass, Plus } from "lucide-react";
import { JobForm } from "@/components/owner/JobForm";
import { api, type Meta, type OwnerJobs } from "@/lib/api";
import { dict, fill, isLocale, label } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).business.jobs.post, robots: { index: false } };
}

export default async function PostJobPage({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<{ business?: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const here = `/${lang}/jobs/new`;
  const loginHref = `/${lang}/business/login?next=${here}`;
  if (!(await currentOwner(lang))) redirect(loginHref);

  const d = dict(lang);
  const t = d.business.jobs;
  const [data, meta] = await Promise.all([ownerApi<OwnerJobs>("/jobs", { locale: lang }), api<Meta>("/public/meta", { revalidate: 300 })]);
  const { business: wanted } = await searchParams;
  const chosen = data.businesses.find((b) => b.id === Number(wanted))?.id ?? data.businesses[0]?.id ?? 0;
  const trades = Object.keys(meta.labels.trades).map((key) => ({ key, label: label(meta.labels.trades[key], lang) }));

  return (
    <div className="mx-auto max-w-2xl px-4 pt-8 md:pt-12">
      <Link href={`/${lang}/business`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {d.business.yourListings}
      </Link>
      <h1 className="display mt-4 text-[2.25rem] md:text-[3rem]">{t.post}</h1>

      {data.businesses.length === 0 ? (
        <div className="card mt-6 p-6 text-center">
          {data.waiting ? (
            <>
              <Hourglass className="mx-auto size-8 text-warning" aria-hidden="true" />
              <p className="mt-3 text-muted">{t.waiting}</p>
            </>
          ) : (
            <>
              <p className="text-muted">{t.needListing}</p>
              <Link href={`/${lang}/business/new`} className="btn btn-primary mt-5"><Plus className="size-4" aria-hidden="true" /> {d.business.add}</Link>
            </>
          )}
        </div>
      ) : (
        <>
          {data.businesses.length === 1 && <p className="mt-2 text-muted">{fill(t.asBusiness, { name: data.businesses[0].name })}</p>}
          <div className="card mt-6 p-5 md:p-6">
            <JobForm lang={lang} t={t} common={d.common} trades={trades} businesses={data.businesses} chosen={chosen} loginHref={loginHref} />
          </div>
        </>
      )}
    </div>
  );
}
