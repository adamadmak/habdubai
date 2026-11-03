import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight, Eye, Hourglass, Pencil, Plus, Send } from "lucide-react";
import { BusinessIcon } from "@/components/CategoryIcon";
import { ConfirmSubmit } from "@/components/owner/ConfirmSubmit";
import { StatusPill } from "@/components/owner/StatusPill";
import { Tibeb } from "@/components/Logo";
import { removeListing } from "@/lib/actions";
import type { Owner, OwnerBusiness, OwnerClaim } from "@/lib/api";
import { number } from "@/lib/format";
import { dict, fill, isLocale, pick, type Dictionary, type Locale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.nav.business, description: d.business.landingLead, alternates: { canonical: `/${lang}/business` } };
}

export default async function BusinessHome({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const owner = await currentOwner();

  return owner ? <Dashboard lang={lang} d={d} owner={owner} /> : <Landing lang={lang} d={d} />;
}

function Landing({ lang, d }: { lang: Locale; d: Dictionary }) {
  return (
    <div className="mx-auto max-w-5xl px-4 pt-10 md:pt-16">
      <div className="max-w-2xl settle">
        <h1 className="display text-[2.25rem] md:text-[3.25rem]">{d.business.landingTitle}</h1>
        <p className="mt-4 text-[1.125rem] text-muted">{d.business.landingLead}</p>
        <div className="mt-7 flex flex-wrap items-center gap-3">
          <Link href={`/${lang}/business/login`} className="btn btn-primary min-h-12 px-5 text-base">
            <Send className="size-4.5" aria-hidden="true" /> {d.business.cta}
          </Link>
          <span className="text-sm text-muted">{d.business.free}</span>
        </div>
      </div>

      <Tibeb className="mt-14" />

      <ol className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {d.business.steps.map((step, i) => (
          <li key={step.title} className="card p-5">
            <span className="grid size-8 place-items-center rounded-full bg-primary text-sm font-bold text-primary-fg tabular">{i + 1}</span>
            <h2 className="title mt-4 text-[1.0625rem]">{step.title}</h2>
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
  );
}

async function Dashboard({ lang, d, owner }: { lang: Locale; d: Dictionary; owner: Owner }) {
  const data = await ownerApi<{ businesses: OwnerBusiness[]; claims: OwnerClaim[] }>("/businesses");
  const b = d.business;

  return (
    <div className="mx-auto max-w-4xl px-4 pt-8 md:pt-12">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="eyebrow">{fill(b.hello, { name: owner.name })}</p>
          <h1 className="display mt-1 text-[2rem] md:text-[2.5rem]">{b.yourListings}</h1>
        </div>
        <div className="flex gap-2">
          <Link href={`/${lang}/business/new`} className="btn btn-primary"><Plus className="size-4" aria-hidden="true" /> {b.add}</Link>
          <form action="/api/auth/logout" method="post">
            <input type="hidden" name="lang" value={lang} />
            <button type="submit" className="btn btn-quiet">{b.signOut}</button>
          </form>
        </div>
      </div>

      {data.businesses.length === 0 ? (
        <div className="card mt-8 p-8 text-center">
          <p className="text-muted">{b.emptyListings}</p>
          <Link href={`/${lang}/business/new`} className="btn btn-primary mt-5"><Plus className="size-4" aria-hidden="true" /> {b.add}</Link>
        </div>
      ) : (
        <ul className="mt-8 flex flex-col gap-3">
          {data.businesses.map((x) => (
            <li key={x.id} className="card overflow-hidden">
              <div className="flex gap-4 p-4">
                <div className="size-20 shrink-0 overflow-hidden rounded-xl bg-primary-soft">
                  {x.photo || x.pending_photo ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={(x.photo ?? x.pending_photo) as string} alt="" className="size-full object-cover" />
                  ) : (
                    <div className="grid size-full place-items-center text-primary"><BusinessIcon category={x.category} className="size-7" /></div>
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
                    {x.status === "approved" && <span className="inline-flex items-center gap-1 text-muted"><Eye className="size-3.5" aria-hidden="true" /> {fill(b.views, { n: number(x.views, lang) })}</span>}
                  </div>
                  {x.review_note && x.status !== "approved" && <p className="mt-2 rounded-lg bg-surface p-2 text-sm">{fill(b.reason, { note: x.review_note })}</p>}
                </div>
              </div>
              <div className="flex flex-wrap gap-2 border-t border-line bg-surface/60 px-4 py-2.5">
                <Link href={`/${lang}/business/${x.id}/edit`} className="btn btn-quiet min-h-9 px-3 text-sm"><Pencil className="size-3.5" aria-hidden="true" /> {b.edit}</Link>
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

      {data.claims.length > 0 && (
        <section className="mt-10">
          <h2 className="title text-xl">{b.claimsTitle}</h2>
          <ul className="mt-3 flex flex-col gap-2">
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
  );
}
