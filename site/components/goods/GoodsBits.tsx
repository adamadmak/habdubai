import Link from "next/link";
import { ArrowRight, Luggage, ShieldAlert, ShieldCheck, Send } from "lucide-react";
import { query, type GoodsBoard } from "@/lib/api";
import { number } from "@/lib/format";
import type { Dictionary, Locale } from "@/lib/i18n";
import { Section } from "../Bits";
import { FlightPath } from "./FlightPath";
import { TONE_ICON, type GoodsTone } from "./GoodsCard";

/** Where each of the three ways in starts the form. */
export function newPostHref(lang: Locale, tone: GoodsTone): string {
  return `/${lang}/goods/new${query(tone === "traveller" ? { kind: "traveller" } : { kind: "sender", mode: tone })}`;
}

/** One way in: a tile in its kind's colours. */
export function ChoiceTile({ lang, tone, title, text, compact = false, className = "" }: {
  lang: Locale;
  tone: GoodsTone;
  title: string;
  text: string;
  compact?: boolean;
  className?: string;
}) {
  const Icon = TONE_ICON[tone];
  return (
    <Link
      href={newPostHref(lang, tone)}
      className={`goods-${tone} tile tile-link spot flex flex-col bg-[linear-gradient(150deg,var(--cover-a),var(--cover-b))] text-white ${
        compact ? "min-h-[8.5rem] p-4" : "min-h-[9.5rem] p-5 md:min-h-[11rem] md:p-6"
      } ${className}`}
    >
      <span className="weave" aria-hidden="true" />
      <span className={`grid place-items-center rounded-2xl bg-white/16 ring-1 ring-white/25 ${compact ? "size-9" : "size-11"}`}>
        <Icon className={compact ? "size-[1.125rem]" : "size-[1.375rem]"} aria-hidden="true" />
      </span>
      <span className={`headline mt-3.5 block ${compact ? "text-[1.125rem]" : "text-[1.25rem] md:text-[1.5rem]"}`}>{title}</span>
      <span className="mt-1 block text-sm text-white/80">{text}</span>
      <ArrowRight className="tile-arrow mt-auto size-5 self-end pt-1" aria-hidden="true" />
    </Link>
  );
}

/** The board's live numbers, the ones that are not zero. */
export function goodsStats(stats: GoodsBoard["stats"] | undefined | null, d: Dictionary): { n: number; label: string }[] {
  if (!stats) return [];
  const s = d.goods.stats;
  return [
    { n: stats.trips, label: stats.trips === 1 ? s.tripOne : s.trips },
    { n: stats.parcels, label: stats.parcels === 1 ? s.parcelOne : s.parcels },
    { n: stats.kilos, label: s.kilos },
    { n: stats.deals, label: stats.deals === 1 ? s.dealOne : s.deals },
  ].filter((item) => item.n > 0);
}

/**
 * The goods board on the front page: the flight path at rest with the live
 * numbers, and the three ways in. Without the board's numbers (the API was
 * away) it still offers the three ways in.
 */
export function GoodsTeaser({ lang, d, board, mark }: { lang: Locale; d: Dictionary; board: GoodsBoard | null; mark: number }) {
  const g = d.goods;
  const stats = goodsStats(board?.stats, d);

  return (
    <Section mark={mark} title={g.title} lead={g.teaserLead} more={{ href: `/${lang}/goods`, label: g.seeBoard }}>
      <div className="grid gap-3 md:gap-4 lg:grid-cols-[1.25fr_1fr]">
        <Link href={`/${lang}/goods`} className="night tile tile-link flex min-h-[15rem] flex-col p-5 md:p-6">
          <FlightPath still direction="to_et" home={{ name: d.clock.home, code: "ADD · ASM" }} gulf={{ name: d.clock.dubai, code: "DXB" }} className="mx-auto max-w-md" />
          {stats.length > 0 && (
            <dl className="relative mt-8 flex flex-wrap gap-x-7 gap-y-3">
              {stats.map((s) => (
                <div key={s.label} className="flex flex-col-reverse">
                  <dt className="text-xs muted-on-night">{s.label}</dt>
                  <dd className="display text-[1.75rem] leading-none tabular">{number(s.n, lang)}</dd>
                </div>
              ))}
            </dl>
          )}
          <span className="relative mt-auto flex items-center gap-1.5 pt-5 text-sm font-semibold">
            <Luggage className="size-4 text-accent" aria-hidden="true" /> {d.nav.goods} <ArrowRight className="tile-arrow size-4" aria-hidden="true" />
          </span>
        </Link>
        <div className="grid grid-cols-2 gap-3 md:gap-4">
          <ChoiceTile lang={lang} tone="traveller" title={g.choose.traveller.title} text={g.choose.traveller.text} compact className="col-span-2" />
          <ChoiceTile lang={lang} tone="send" title={g.choose.send.title} text={g.choose.send.text} compact />
          <ChoiceTile lang={lang} tone="request" title={g.choose.request.title} text={g.choose.request.text} compact />
        </div>
      </div>
    </Section>
  );
}

/**
 * Signed in, but not able to use the board: not a member yet (membership is
 * proved in the bot, with the phone number), or blocked after two failed deals.
 */
export function MemberGate({ d, botLink, blocked = false }: { d: Dictionary; botLink: string | null; blocked?: boolean }) {
  const g = d.goods;
  if (blocked) {
    return (
      <div className="card relative overflow-hidden p-6 md:p-8">
        <div className="tibeb-thin absolute inset-x-0 top-0" aria-hidden="true" />
        <ShieldAlert className="size-9 text-danger" aria-hidden="true" />
        <h2 className="headline mt-4 text-[1.5rem]">{g.blocked.title}</h2>
        <p className="mt-2 max-w-xl text-muted">{g.blocked.text}</p>
        {botLink && (
          <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-line mt-6">
            <Send className="size-4" aria-hidden="true" /> {g.openBot}
          </a>
        )}
      </div>
    );
  }

  return (
    <div className="night tile p-6 md:p-10">
      <div className="relative max-w-2xl">
        <span className="grid size-12 place-items-center rounded-2xl bg-accent text-night">
          <ShieldCheck className="size-6" aria-hidden="true" />
        </span>
        <h2 className="display mt-5 text-[1.875rem] md:text-[2.5rem]">{g.join.title}</h2>
        <p className="mt-3 muted-on-night">{g.join.text}</p>
        {botLink && (
          <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-gold mt-7 min-h-12 rounded-full px-6 text-base">
            <Send className="size-4.5" aria-hidden="true" /> {g.join.cta}
          </a>
        )}
        <p className="mt-4 text-sm muted-on-night">{g.join.then}</p>
      </div>
    </div>
  );
}
