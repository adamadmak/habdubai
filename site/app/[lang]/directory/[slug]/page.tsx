import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { BadgeCheck, ChevronLeft, Clock, Globe, Languages, MapPin, MessageCircle, Navigation, Phone, Send, UserCheck } from "lucide-react";
import { BusinessCard } from "@/components/Cards";
import { Cover } from "@/components/Cover";
import { ShareButton } from "@/components/ShareButton";
import { ViewPing } from "@/components/ViewPing";
import { api, apiOrNull, type Business, type Meta } from "@/lib/api";
import { digits, formatDate, host } from "@/lib/format";
import { dict, fill, isLocale, label, pick } from "@/lib/i18n";
import { siteUrl } from "@/lib/site";

type Detail = { business: Business; nearby: Business[] };
type Params = { lang: string; slug: string };

async function load(slug: string): Promise<Detail | null> {
  return /^[a-z0-9-]{1,170}$/.test(slug) ? apiOrNull<Detail>(`/public/businesses/${slug}`, { revalidate: 120 }) : null;
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang, slug } = await params;
  if (!isLocale(lang)) return {};
  const data = await load(slug);
  if (!data) return {};
  const b = data.business;
  const where = [b.area, pick(b.city?.name, lang)].filter(Boolean).join(", ");
  return {
    title: `${b.name} · ${where}`,
    description: b.description ?? where,
    alternates: { canonical: `/${lang}/directory/${b.slug}` },
    openGraph: b.photo ? { images: [{ url: b.photo }] } : undefined,
  };
}

export default async function BusinessPage({ params }: { params: Promise<Params> }) {
  const { lang, slug } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const [data, meta] = await Promise.all([load(slug), api<Meta>("/public/meta", { revalidate: 300 })]);
  if (!data) notFound();
  const { business: b, nearby } = data;

  const kind = label(meta.labels.business_categories[b.category], lang);
  const city = pick(b.city?.name, lang);
  const languages = (b.languages ?? []).map((code) => pick(meta.labels.languages[code], lang)).filter(Boolean);

  const actions = [
    b.phone && { href: `tel:+${digits(b.phone)}`, label: d.common.call, icon: Phone, primary: true },
    b.whatsapp && { href: `https://wa.me/${digits(b.whatsapp)}`, label: d.common.whatsapp, icon: MessageCircle },
    b.telegram && { href: `https://t.me/${b.telegram}`, label: d.common.telegram, icon: Send },
    b.map_link && { href: b.map_link, label: d.common.directions, icon: Navigation },
    b.website && { href: b.website, label: d.common.website, icon: Globe },
  ].filter(Boolean) as { href: string; label: string; icon: typeof Phone; primary?: boolean }[];

  // Search engines read the place as a local business, with its address and phone.
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    name: b.name,
    description: b.description ?? undefined,
    image: b.photo ?? undefined,
    telephone: b.phone ? `+${digits(b.phone)}` : undefined,
    url: siteUrl(`${lang}/directory/${b.slug}`),
    address: { "@type": "PostalAddress", streetAddress: b.area, addressLocality: b.city?.name.en, addressCountry: "AE" },
    geo: b.latitude != null && b.longitude != null ? { "@type": "GeoCoordinates", latitude: b.latitude, longitude: b.longitude } : undefined,
    openingHours: b.hours ?? undefined,
    knowsLanguage: b.languages?.length ? b.languages : undefined,
  };

  return (
    <article className="pb-4">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <ViewPing slug={b.slug} />

      <div className="mx-auto max-w-4xl px-4 pt-6 md:pt-10">
        <Link href={`/${lang}/directory`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
          <ChevronLeft className="size-4" aria-hidden="true" /> {d.directory.back}
        </Link>
      </div>

      <div className="mx-auto mt-4 max-w-4xl px-4">
        <div className="overflow-hidden rounded-[1.75rem] shadow-[0_24px_60px_-30px_rgb(6_12_36/0.5)]">
          {b.photo ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={b.photo} alt={b.name} className="aspect-[16/9] w-full object-cover md:aspect-[21/9]" />
          ) : (
            <Cover category={b.category} name={b.name} seed={b.id} big className="aspect-[16/9] md:aspect-[21/8]" />
          )}
          <div className="tibeb" aria-hidden="true" />
        </div>

        <header className="mt-6">
          <p className="text-sm font-semibold text-accent-ink">{kind}</p>
          <h1 className="display mt-1.5 flex flex-wrap items-center gap-2 text-[2.25rem] md:text-[3rem]">
            {b.name}
            {b.verified && <BadgeCheck className="size-7 text-primary" aria-label={d.common.verified} />}
          </h1>
          <p className="mt-2 flex items-center gap-1.5 text-muted">
            <MapPin className="size-4 shrink-0" aria-hidden="true" /> {[b.area, city].filter(Boolean).join(", ")}
          </p>
          <div className="mt-3 flex flex-wrap gap-2 text-xs font-medium">
            {b.verified && <span className="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-primary"><BadgeCheck className="size-3.5" aria-hidden="true" />{d.common.verifiedNote}</span>}
            {b.owner_managed && <span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2.5 py-1 text-success"><UserCheck className="size-3.5" aria-hidden="true" />{d.common.ownerManaged}</span>}
          </div>
        </header>

        {actions.length > 0 && (
          <div className="mt-6 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
            {actions.map((a) => (
              <a
                key={a.label}
                href={a.href}
                target={a.href.startsWith("tel:") ? undefined : "_blank"}
                rel="noopener noreferrer"
                className={`btn ${a.primary ? "btn-primary" : "btn-quiet"}`}
              >
                <a.icon className="size-4" aria-hidden="true" /> {a.label}
              </a>
            ))}
            <ShareButton title={b.name} url={siteUrl(`${lang}/directory/${b.slug}`)} labels={{ share: d.common.share, copied: d.common.copied }} />
          </div>
        )}

        <div className="mt-8 grid gap-8 md:grid-cols-[1.6fr_1fr]">
          <div>
            {(b.description || b.about) && (
              <section>
                <h2 className="headline text-2xl">{d.directory.about}</h2>
                {b.description && <p className="mt-3 text-[1.0625rem] font-medium">{b.description}</p>}
                {b.about && <p className="prose-plain mt-3 leading-relaxed text-muted">{b.about}</p>}
              </section>
            )}
          </div>
          <aside className="flex flex-col gap-3">
            {b.hours && (
              <div className="card p-4">
                <p className="flex items-center gap-2 text-sm font-semibold"><Clock className="size-4 text-primary" aria-hidden="true" /> {d.directory.hours}</p>
                <p className="prose-plain mt-1.5 text-sm text-muted">{b.hours}</p>
              </div>
            )}
            {languages.length > 0 && (
              <div className="card p-4">
                <p className="flex items-center gap-2 text-sm font-semibold"><Languages className="size-4 text-primary" aria-hidden="true" /> {d.directory.languages}</p>
                <p className="mt-1.5 text-sm text-muted">{languages.join(" · ")}</p>
              </div>
            )}
            <div className="card p-4">
              <p className="flex items-center gap-2 text-sm font-semibold"><MapPin className="size-4 text-primary" aria-hidden="true" /> {d.directory.location}</p>
              <p className="mt-1.5 text-sm text-muted">{[b.area, city].filter(Boolean).join(", ")}</p>
              {b.map_link && <a href={b.map_link} target="_blank" rel="noopener noreferrer" className="mt-2 inline-block text-sm font-semibold text-primary">{d.common.directions} · <span className="font-normal text-muted">{d.directory.mapHint}</span></a>}
              {b.website && <p className="mt-2 text-sm"><a href={b.website} target="_blank" rel="noopener noreferrer" className="text-primary">{host(b.website)}</a></p>}
            </div>
            {b.updated_at && <p className="px-1 text-xs text-faint">{fill(d.common.updated, { date: formatDate(b.updated_at, lang, "medium") })}</p>}
          </aside>
        </div>

        {!b.owner_managed && (
          <section className="night tile mt-10 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between md:p-7">
            <div>
              <h2 className="headline text-xl md:text-2xl">{d.directory.claimTitle}</h2>
              <p className="mt-1.5 text-sm muted-on-night">{d.directory.claimText}</p>
            </div>
            <Link href={`/${lang}/business/claim/${b.slug}`} className="btn btn-gold shrink-0 rounded-full">{d.directory.claimCta}</Link>
          </section>
        )}
      </div>

      {nearby.length > 0 && (
        <section className="mx-auto mt-12 max-w-6xl px-4">
          <h2 className="headline text-[1.75rem]">{d.directory.nearby}</h2>
          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {nearby.map((n) => <BusinessCard key={n.id} business={n} lang={lang} meta={meta} />)}
          </div>
        </section>
      )}
    </article>
  );
}
