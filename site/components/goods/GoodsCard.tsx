import Link from "@/components/AppLink";
import { BadgeCheck, HandHeart, Handshake, Luggage, Package, PlaneTakeoff, Star } from "lucide-react";
import type { CSSProperties } from "react";
import type { Dictionary } from "@/i18n/en";
import type { GoodsCard as Post, GoodsKind, GoodsMemberBadge, GoodsMode } from "@/lib/api";
import { fill } from "@/lib/fill";
import { dayAndMonth, monthYear, number, shortDate } from "@/lib/format";
import { label } from "@/lib/labels";
import type { Locale } from "@/lib/locales";
import { initials } from "../Cover";
import { DateLeaf } from "../DateLeaf";
import { TopRated } from "../Score";
import { InterestButton } from "./InterestButton";

/*
 * A post on the goods board, drawn as a boarding pass. No dictionaries are
 * imported here: the page hands in its words (`t`), so the goods form can
 * draw its live preview with the very same component in the browser.
 */

export type CardLabels = Dictionary["goods"]["card"];

/** Which of the three a post is: a trip, a parcel to send, or something to be brought. Each has its colours and icon. */
export type GoodsTone = "traveller" | "send" | "request";

export function toneOf(post: { kind: GoodsKind; mode: GoodsMode | null }): GoodsTone {
  if (post.kind === "traveller") return "traveller";
  return post.mode === "request" ? "request" : "send";
}

export const TONE_ICON = { traveller: PlaneTakeoff, send: Package, request: HandHeart } as const;

/** Airport-style codes for the board's cities, so a route reads like a ticket: DXB → ADD. */
const CODES: Record<string, string> = {
  dubai: "DXB",
  sharjah: "SHJ",
  abu_dhabi: "AUH",
  ajman: "AJM",
  ras_al_khaimah: "RKT",
  fujairah: "FJR",
  al_ain: "AAN",
  other_uae: "UAE",
  addis_ababa: "ADD",
  dire_dawa: "DIR",
  mekelle: "MQX",
  bahir_dar: "BJR",
  gondar: "GDQ",
  hawassa: "AWA",
  jimma: "JIM",
  dessie: "DSE",
  asmara: "ASM",
  massawa: "MSW",
  other_et: "ETH",
};

export function cityCode(city: { key: string; name: Partial<Record<Locale, string>> }): string {
  const known = CODES[city.key];
  if (known) return known;
  const letters = (city.name.en ?? city.key).replace(/[^A-Za-z]/g, "").slice(0, 3).toUpperCase();
  return letters || "···";
}

/** The fee as a chip says it: "AED 25 / kg", "Offer: ETB 1,500", "Free", "Price to agree". */
export function priceText(post: Pick<Post, "kind" | "price" | "currency" | "price_basis">, t: CardLabels, lang: Locale): string {
  if (post.price === null || post.price === undefined) return t.price.agree;
  if (post.price === 0) return t.price.free;
  const amount = `${post.currency ?? ""} ${number(post.price, lang)}`.trim();
  const basis = post.price_basis ?? (post.kind === "traveller" ? "per_kg" : "total");
  return fill(basis === "per_kg" ? t.price.perKg : t.price.offer, { price: amount });
}

export function PriceChip({ post, t, lang, className = "" }: { post: Pick<Post, "kind" | "price" | "currency" | "price_basis">; t: CardLabels; lang: Locale; className?: string }) {
  const free = post.price === 0;
  const open = post.price === null || post.price === undefined;
  return (
    <span
      className={`inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold tabular ${
        free ? "bg-success-soft text-success" : open ? "border border-dashed border-line text-muted" : "bg-accent-soft text-accent-ink"
      } ${className}`}
    >
      {priceText(post, t, lang)}
    </span>
  );
}

/** The kind's gradient square with its icon, like the place and work badges. */
export function GoodsBadge({ post, className = "size-11 rounded-2xl", iconClass = "size-5" }: { post: { kind: GoodsKind; mode: GoodsMode | null }; className?: string; iconClass?: string }) {
  const tone = toneOf(post);
  const Icon = TONE_ICON[tone];
  return (
    <span className={`goods-${tone} cover cover-p1 grid shrink-0 place-items-center ${className}`}>
      <Icon className={iconClass} aria-hidden="true" />
    </span>
  );
}

/** The suitcase: kilos still free of a trip's total, or a parcel's weight against the most one may weigh. */
export function KiloGauge({ post, t, lang, maxKilos }: { post: Pick<Post, "kind" | "kilos" | "kilos_left">; t: CardLabels; lang: Locale; maxKilos: number }) {
  const trip = post.kind === "traveller";
  const scale = trip ? post.kilos : maxKilos;
  const value = trip ? (post.kilos_left ?? post.kilos) : post.kilos;
  const share = scale > 0 ? Math.max(0, Math.min(1, value / scale)) : 0;
  const text = trip
    ? value <= 0
      ? t.full
      : fill(t.kilosLeft, { left: number(value, lang), total: number(post.kilos, lang) })
    : fill(t.weight, { n: number(post.kilos, lang) });
  const Icon = trip ? Luggage : Package;

  return (
    <div className="min-w-0 flex-1">
      <p className="flex items-center gap-1.5 text-sm font-semibold tabular">
        <Icon className="size-4 shrink-0 text-[var(--kind-ink)]" aria-hidden="true" />
        <span className="truncate">{text}</span>
      </p>
      <span className="gauge-case mt-1" aria-hidden="true">
        <span className="gauge" style={{ "--share": share, "--ticks": Math.max(1, Math.round(scale / 5)) } as CSSProperties}>
          <span />
        </span>
      </span>
    </div>
  );
}

/** Who posted it: their name, since when they are verified, their rating and their deals, or that they are new. */
export function TrustBadge({ member, t, lang, className = "" }: { member: GoodsMemberBadge; t: CardLabels; lang: Locale; className?: string }) {
  const since = monthYear(member.verified_since, lang);
  const rated = member.rating !== null && member.ratings > 0;
  const rating = rated ? fill(member.ratings === 1 ? t.ratingOne : t.rating, { rating: (member.rating as number).toFixed(1), n: number(member.ratings, lang) }) : null;
  const deals = member.deals > 0 ? (member.deals === 1 ? t.dealOne : fill(t.deals, { n: number(member.deals, lang) })) : null;

  return (
    <div className={`flex items-center gap-3 ${className}`}>
      <span className="grid size-10 shrink-0 place-items-center rounded-full bg-night text-sm font-bold text-white ring-2 ring-accent/50" aria-hidden="true">
        {initials(member.name) || "·"}
      </span>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1 font-semibold">
          <span className="truncate">{member.name}</span>
          <BadgeCheck className="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span className="sr-only">{t.verified}</span>
        </p>
        <p className="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-muted">
          <span>{since ? fill(t.verifiedSince, { date: since }) : t.verified}</span>
          {rating && (
            <span className="inline-flex items-center gap-1 tabular">
              <Star className="size-3.5 fill-accent text-accent" aria-hidden="true" /> {rating}
            </span>
          )}
          {deals && (
            <span className="inline-flex items-center gap-1 tabular">
              <Handshake className="size-3.5" aria-hidden="true" /> {deals}
            </span>
          )}
          {!rating && !deals && <span className="rounded-full bg-accent-soft px-2 py-0.5 font-semibold text-accent-ink">{t.new}</span>}
          {member.top && <TopRated label={t.top} />}
        </p>
      </div>
    </div>
  );
}

/**
 * The boarding pass. `preview` draws it for the form (no link, a still
 * button); otherwise the whole pass opens the post's page (unless it is that
 * page, `linked={false}`) and the button says "I'm interested".
 */
export function GoodsCard({ post, lang, t, maxKilos = 46, signedIn = false, preview = false, linked = true }: {
  post: Post;
  lang: Locale;
  t: CardLabels;
  maxKilos?: number;
  signedIn?: boolean;
  preview?: boolean;
  linked?: boolean;
}) {
  const tone = toneOf(post);
  const Icon = TONE_ICON[tone];
  const from = label(post.from.name, lang);
  const to = label(post.to.name, lang);
  const kind = tone === "request" ? fill(t.kind.request, { city: from }) : t.kind[tone];
  const route = fill(t.route, { from, to });
  const when = post.date ? fill(post.kind === "traveller" ? t.flight : t.around, { date: shortDate(post.date, lang) }) : " ";

  return (
    <article className={`pass goods-${tone} flex h-full flex-col`} aria-label={`${kind} · ${route}`}>
      {/* The band: what kind of post, in its colours. The date leaf is stamped across its edge. */}
      <div className="cover cover-p1 px-4 pb-3 pt-3.5">
        <p className="flex items-center gap-2 pr-16 text-[0.8125rem] font-semibold">
          <span className="grid size-7 shrink-0 place-items-center rounded-full bg-white/18 ring-1 ring-white/25">
            <Icon className="size-4" aria-hidden="true" />
          </span>
          <span className="truncate">{kind}</span>
        </p>
      </div>
      <DateLeaf date={post.date || null} lang={lang} className="pointer-events-none absolute right-4 top-3 z-10" />

      <div className="px-4 pt-4">
        <h3 className="sr-only">
          {preview || !linked ? route : (
            <Link href={`/${lang}/goods/${post.id}`} className="pass-link">{route}</Link>
          )}
        </h3>
        <div className="flex items-end gap-2" aria-hidden="true">
          <span className="min-w-0">
            <span className="display block text-[2rem] leading-none">{cityCode(post.from)}</span>
            <span className="mt-1 block truncate text-xs font-medium text-muted">{from}</span>
          </span>
          <span className="relative mb-5 min-w-8 flex-1">
            <span className="pass-arc" />
            <span className="absolute left-1/2 top-0 grid size-7 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-elevated text-[var(--kind-ink)] ring-1 ring-line">
              <Icon className="size-3.5" />
            </span>
          </span>
          <span className="min-w-0 text-right">
            <span className="display block text-[2rem] leading-none">{cityCode(post.to)}</span>
            <span className="mt-1 block truncate text-xs font-medium text-muted">{to}</span>
          </span>
        </div>
        <p className="mt-2.5 text-xs font-medium text-muted">{when}</p>
      </div>

      <div className="pass-perf my-4" aria-hidden="true" />

      <div className="flex flex-1 flex-col gap-3.5 px-4 pb-4">
        <div className="flex items-start gap-3">
          <KiloGauge post={post} t={t} lang={lang} maxKilos={maxKilos} />
          <PriceChip post={post} t={t} lang={lang} />
        </div>

        {post.categories.length > 0 && (
          <ul className="flex flex-wrap gap-1.5" aria-label={post.kind === "traveller" ? t.carries : t.contains}>
            {post.categories.map((c) => (
              <li key={c.key} className="rounded-full bg-[var(--kind-soft)] px-2.5 py-1 text-xs font-semibold text-[var(--kind-ink)]">
                {label(c.name, lang)}
              </li>
            ))}
          </ul>
        )}

        {post.description && <p className="prose-plain line-clamp-3 text-sm text-muted">“{post.description}”</p>}

        <TrustBadge member={post.member} t={t} lang={lang} className="mt-auto border-t border-dashed border-line pt-3.5" />

        {preview ? (
          <span className="btn btn-kind pointer-events-none w-full" aria-hidden="true">{t.interested}</span>
        ) : (
          <InterestButton id={post.id} lang={lang} signedIn={signedIn} labels={{ interested: t.interested, sending: t.sending, error: t.error, signInFirst: t.signInFirst }} />
        )}
      </div>
    </article>
  );
}

/** A post in a line: for the "who fits" list beside the form. */
export function MiniGoodsCard({ post, lang, t, href }: { post: Post; lang: Locale; t: CardLabels; href?: string }) {
  const tone = toneOf(post);
  const kilos = post.kind === "traveller" ? fill(t.kilosLeft, { left: number(post.kilos_left ?? post.kilos, lang), total: number(post.kilos, lang) }) : fill(t.weight, { n: number(post.kilos, lang) });
  const body = (
    <>
      <GoodsBadge post={post} className="size-10 rounded-xl" iconClass="size-[1.125rem]" />
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-semibold">
          {cityCode(post.from)} → {cityCode(post.to)} · {dayAndMonth(post.date, lang).day} {dayAndMonth(post.date, lang).month}
        </span>
        <span className="block truncate text-xs text-muted">
          {post.member.name} · {kilos}
        </span>
      </span>
      <PriceChip post={post} t={t} lang={lang} />
    </>
  );
  const box = `goods-${tone} flex items-center gap-3 rounded-2xl border border-line bg-elevated p-2.5`;

  return href ? (
    <a href={href} target="_blank" rel="noopener" className={`${box} press hover:border-fg/30`}>
      {body}
    </a>
  ) : (
    <div className={box}>{body}</div>
  );
}
