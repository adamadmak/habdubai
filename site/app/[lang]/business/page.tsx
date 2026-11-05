import type { Metadata } from "next";
import Link from "@/components/AppLink";
import { notFound } from "next/navigation";
import { ArrowRight, Briefcase, Eye, Hourglass, Pencil, Plus, Send } from "lucide-react";
import { GEEZ, Notice, PageHead } from "@/components/Bits";
import { ListingMock } from "@/components/Cards";
import { Cover } from "@/components/Cover";
import { Night } from "@/components/Dawn";
import { ConfirmSubmit } from "@/components/owner/ConfirmSubmit";
import { OwnerJobsList } from "@/components/owner/OwnerJobsList";
import { StatusPill } from "@/components/owner/StatusPill";
import { removeListing } from "@/lib/actions";
import type { Owner, OwnerBusiness, OwnerClaim, OwnerJobs } from "@/lib/api";
import { number } from "@/lib/format";
import { photoSrcSet } from "@/lib/photos";
import { dict, fill, isLocale, pick, type Dictionary, type Locale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";
import { alternatesFor } from "@/lib/site";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.nav.business, description: d.business.landingLead, alternates: alternatesFor(lang, `/business`) };
}

export default async function BusinessHome({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<{ job?: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const owner = await currentOwner(lang);

  const { job } = await searchParams;

  return owner ? <Dashboard lang={lang} d={d} owner={owner} justPosted={job} /> : <Landing lang={lang} d={d} />;
}

function Landing({ lang, d }: { lang: Locale; d: Dictionary }) {
  return (
    <>
      <Night>
        <div className="mx-auto grid max-w-page gap-12 px-4 lg:px-8 pb-12 pt-10 md:grid-cols-[1.15fr_0.85fr] md:items-center md:pb-16 md:pt-16">
          <div className="settle">
            <p className="glass-white inline-flex items-center gap-2 rounded-full py-1.5 pl-1.5 pr-3.5 text-[0.8125rem] font-semibold">
              <span className="tibeb-thin inline-block w-7 rounded-full" aria-hidden="true" /> {d.nav.business}
            </p>
            <h1 className="display mt-5 text-[2.5rem] md:text-[3.5rem]">{d.business.landingTitle}</h1>
            <p className="mt-5 max-w-xl text-[1.0625rem] muted-on-night md:text-lg">{d.business.landingLead}</p>
            <div className="mt-8 flex flex-wrap items-center gap-4">
              <Link href={`/${lang}/business/login`} className="btn btn-gold min-h-12 rounded-full px-6 text-base">
                <Send className="size-4.5" aria-hidden="true" /> {d.business.cta}
              </Link>
              <span className="text-sm muted-on-night">{d.business.free}</span>
            </div>
          </div>
          {/* What the owner gets: a page of their own, and a post in the channel. */}
          <div className="settle settle-2 relative mx-auto h-[22rem] w-full max-w-sm" aria-hidden="true">
            <div className="glass-white absolute left-0 top-6 w-60 -rotate-6 rounded-[1.375rem] p-3">
              <Cover category="restaurant" name="Selam" seed={0} className="aspect-[16/10] rounded-2xl" />
              <div className="mt-2.5 h-2.5 w-3/4 rounded-full bg-fg/20" />
              <div className="mt-1.5 h-2 w-1/2 rounded-full bg-fg/12" />
              <div className="mt-3 flex gap-1.5"><span className="h-6 flex-1 rounded-lg bg-accent/80" /><span className="h-6 w-10 rounded-lg bg-fg/10" /></div>
            </div>
            <ListingMock lang={lang} className="absolute bottom-0 right-0 rotate-3" />
          </div>
        </div>
      </Night>

      <div className="mx-auto max-w-page px-4 lg:px-8">
        <ol className="reveal mt-14 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {d.business.steps.map((step, i) => (
            <li key={step.title} className="card relative overflow-hidden p-5 md:p-6">
              <span className="geez-mark" aria-hidden="true">{GEEZ[i]}</span>
              <h2 className="headline mt-4 text-[1.25rem]">{step.title}</h2>
              <p className="mt-1.5 text-sm text-muted">{step.text}</p>
            </li>
          ))}
        </ol>

        <p className="mt-8 text-sm text-muted">
        {d.business.alreadyListed}{" "}
        <Link href={`/${lang}/directory`} className="inline-flex items-center gap-1 font-semibold text-primary">
          {d.nav.directory} <ArrowRight className="size-3.5" aria-hidden="true" />
        </Link>
        </p>
      </div>
    </>
  );
}

async function Dashboard({ lang, d, owner, justPosted }: { lang: Locale; d: Dictionary; owner: Owner; justPosted?: string }) {
  const [data, hiring] = await Promise.all([
    ownerApi<{ businesses: OwnerBusiness[]; claims: OwnerClaim[] }>("/businesses", { locale: lang }),
    // The listings still show if this one answer is missing.
    ownerApi<OwnerJobs>("/jobs", { locale: lang }).catch((): OwnerJobs => ({ jobs: [], businesses: [], waiting: false })),
  ]);
  const b = d.business;

  return (
    <>
    <PageHead title={b.yourListings} lead={fill(b.hello, { name: owner.name })}>
      <div className="mt-6 flex flex-wrap gap-2">
        <Link href={`/${lang}/jobs/new`} className="btn btn-gold rounded-full"><Briefcase className="size-4" aria-hidden="true" /> {b.jobs.post}</Link>
        <Link href={`/${lang}/business/new`} className="btn btn-line rounded-full"><Plus className="size-4" aria-hidden="true" /> {b.add}</Link>
        <form action="/api/auth/logout" method="post">
          <input type="hidden" name="lang" value={lang} />
          <input type="hidden" name="back" value={`/${lang}/business`} />
          <button type="submit" className="btn btn-glass rounded-full">{b.signOut}</button>
        </form>
      </div>
    </PageHead>
    <div className="mx-auto max-w-page px-4 lg:px-8">

      {data.businesses.length === 0 ? (
        <div className="card mt-8 p-8 text-center">
          <p className="text-muted">{b.emptyListings}</p>
          <Link href={`/${lang}/business/new`} className="btn btn-primary mt-5"><Plus className="size-4" aria-hidden="true" /> {b.add}</Link>
        </div>
      ) : (
        <ul className="mt-8 grid gap-3 lg:grid-cols-2">
          {data.businesses.map((x) => (
            <li key={x.id} className="card flex flex-col overflow-hidden">
              <div className="flex flex-1 gap-4 p-4">
                <div className="size-20 shrink-0 overflow-hidden rounded-2xl">
                  {x.photo || x.pending_photo ? (
                    // An 80px thumbnail: the 480px copy when the photo is public (a photo in review is a signed link, left alone).
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={(x.photo ?? x.pending_photo) as string} srcSet={photoSrcSet(x.photo ?? x.pending_photo)} sizes="80px" alt="" className="size-full object-cover" />
                  ) : (
                    <Cover category={x.category} name={x.name} seed={x.id} className="size-full [&_.monogram]:text-[2rem] [&>span:first-child]:hidden" />
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="title truncate text-lg">{x.name}</h2>
                    <StatusPill status={x.status} text={b.status[x.status]} />
                  </div>
                  <p className="mt-0.5 truncate text-sm text-muted">{[x.area, pick(x.city, lang)].filter(Boolean).join(", ")}</p>
                  <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    {x.changes_in_review && <span className="inline-flex items-center gap-1 text-warning"><Hourglass className="size-3.5" aria-hidden="true" /> {b.changesInReview}</span>}
                    {x.pending_photo && <span className="inline-flex items-center gap-1 text-warning"><Hourglass className="size-3.5" aria-hidden="true" /> {b.photoInReview}</span>}
                    {x.status === "approved" && <span className="inline-flex items-center gap-1 text-muted"><Eye className="size-3.5" aria-hidden="true" /> {x.views === 1 ? b.viewOne : fill(b.views, { n: number(x.views, lang) })}</span>}
                  </div>
                  {x.review_note && x.status !== "approved" && <p className="mt-2 rounded-lg bg-surface p-2 text-sm">{fill(b.reason, { note: x.review_note })}</p>}
                </div>
              </div>
              <div className="flex flex-wrap gap-2 border-t border-line bg-surface/60 px-4 py-2.5">
                <Link href={`/${lang}/business/${x.id}/edit`} className="btn btn-quiet min-h-9 px-3 text-sm"><Pencil className="size-3.5" aria-hidden="true" /> {b.edit}</Link>
                {x.status === "approved" && (
                  <Link href={`/${lang}/jobs/new?business=${x.id}`} className="btn btn-quiet min-h-9 px-3 text-sm"><Briefcase className="size-3.5" aria-hidden="true" /> {b.jobs.post}</Link>
                )}
                {x.status === "approved" && x.slug && (
                  <Link href={`/${lang}/directory/${x.slug}`} className="btn btn-quiet min-h-9 px-3 text-sm"><Eye className="size-3.5" aria-hidden="true" /> {b.view}</Link>
                )}
                {x.status !== "hidden" && (
                  <form action={removeListing} className="ml-auto">
                    <input type="hidden" name="lang" value={lang} />
                    <input type="hidden" name="id" value={x.id} />
                    <ConfirmSubmit text={x.status === "approved" ? b.takeDown : b.withdraw} confirm={x.status === "approved" ? b.takeDownConfirm : b.withdrawConfirm} />
                  </form>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      {(hiring.jobs.length > 0 || justPosted) && (
        <section id="jobs" className="mt-10 scroll-mt-24">
          <h2 className="title text-xl">{b.jobs.title}</h2>
          {justPosted && <div className="mt-3"><Notice tone="success">{justPosted === "review" ? b.jobs.postedReview : b.jobs.postedChecking}</Notice></div>}
          <OwnerJobsList lang={lang} t={b.jobs} jobs={hiring.jobs} />
        </section>
      )}

      {data.claims.length > 0 && (
        <section className="mt-10">
          <h2 className="title text-xl">{b.claimsTitle}</h2>
          <ul className="mt-3 grid gap-2 lg:grid-cols-2">
            {data.claims.map((c) => (
              <li key={c.id} className="card flex flex-wrap items-center justify-between gap-2 p-4">
                <span className="font-semibold">{c.business?.name}</span>
                <span className="text-sm text-muted">{b.claimStatus[c.status]}{c.review_note ? ` · ${c.review_note}` : ""}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
    </>
  );
}
