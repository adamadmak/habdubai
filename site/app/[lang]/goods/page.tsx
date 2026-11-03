import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight, Ban, CalendarClock, HandCoins, ShieldCheck } from "lucide-react";
import { GEEZ, Pager, Section } from "@/components/Bits";
import { countLabel } from "@/components/Cards";
import { FlightPath } from "@/components/goods/FlightPath";
import { ChoiceTile, goodsStats, newPostHref } from "@/components/goods/GoodsBits";
import { GoodsBadge, GoodsCard } from "@/components/goods/GoodsCard";
import { ToggleRow } from "@/components/goods/ToggleRow";
import { Night } from "@/components/Dawn";
import { api, GOODS_DIRECTIONS, query, type GoodsBoard, type GoodsDirection } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { number } from "@/lib/format";
import { dict, fill, isLocale } from "@/lib/i18n";
import { currentOwner } from "@/lib/owner";

type Search = { direction?: string; kind?: string; page?: string };

/** The board's own filter: a trip, a parcel to send, a thing to be brought (or every parcel, from an older link). */
const SHOW = ["traveller", "send", "request", "sender"] as const;
type Show = (typeof SHOW)[number];

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.goods.title, description: d.goods.lead, alternates: { canonical: `/${lang}/goods` } };
}

export default async function GoodsBoardPage({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<Search> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const g = d.goods;
  const sp = await searchParams;

  const direction = (GOODS_DIRECTIONS as readonly string[]).includes(sp.direction ?? "") ? (sp.direction as GoodsDirection) : undefined;
  const show = (SHOW as readonly string[]).includes(sp.kind ?? "") ? (sp.kind as Show) : undefined;
  const page = Math.max(1, Number(sp.page) || 1);
  const filtered = Boolean(direction || show) || page > 1;
  const apiFilter = {
    direction,
    kind: show === "traveller" ? "traveller" : show ? "sender" : undefined,
    mode: show === "send" || show === "request" ? show : undefined,
    page: page > 1 ? page : undefined,
  };

  // The whole board's first page is cached for everyone; a filter or a later page is asked for as this visitor.
  const [board, owner] = await Promise.all([
    api<GoodsBoard>(`/public/goods${query(apiFilter)}`, filtered ? { client: await currentClient() } : {}),
    currentOwner(lang).catch(() => null),
  ]);

  const base = `/${lang}/goods`;
  const current = { direction, kind: show };
  const stats = goodsStats(board.stats, d);
  const pageHref = (p: number) => `${base}${query({ ...current, page: p > 1 ? p : undefined })}`;

  return (
    <>
      <Night word="ሰሌዳ">
        <div className="mx-auto grid max-w-7xl gap-6 px-4 pb-4 pt-10 md:grid-cols-[1.05fr_0.95fr] md:items-center md:gap-12 md:pb-8 md:pt-16">
          <div className="settle">
            <p className="glass-white inline-flex items-center gap-2 rounded-full py-1.5 pl-1.5 pr-3.5 text-[0.8125rem] font-semibold">
              <span className="grid size-6 place-items-center rounded-full bg-accent text-night">
                <ShieldCheck className="size-3.5" aria-hidden="true" />
              </span>
              {g.membersOnly}
            </p>
            <h1 className="display mt-5 text-[2.5rem] md:text-[3.5rem]">
              {g.heroTitle} <span className="gild">{g.heroAccent}</span>
            </h1>
            <p className="mt-5 max-w-xl text-[1.0625rem] muted-on-night md:text-lg">{g.lead}</p>
            {stats.length > 0 && (
              <dl className="mt-7 flex flex-wrap gap-x-8 gap-y-4">
                {stats.map((s) => (
                  <div key={s.label} className="flex flex-col-reverse">
                    <dt className="text-xs muted-on-night">{s.label}</dt>
                    <dd className="display text-[2rem] leading-none tabular">{number(s.n, lang)}</dd>
                  </div>
                ))}
              </dl>
            )}
            {owner && (
              <Link href={`${base}/mine`} className="btn btn-glass mt-7 rounded-full">
                {g.mine} <ArrowRight className="size-4" aria-hidden="true" />
              </Link>
            )}
          </div>
          {/* Keyed by direction, so a new direction flies the plane again, the other way. */}
          <FlightPath
            key={direction ?? "both"}
            direction={direction ?? "to_et"}
            home={{ name: d.clock.home, code: "ADD · ASM" }}
            gulf={{ name: d.clock.dubai, code: "DXB" }}
            className="settle settle-2 mx-auto max-w-md md:max-w-none"
          />
        </div>
      </Night>

      {/* ---- The three ways in. ---- */}
      <section className="mx-auto mt-8 max-w-7xl px-4 md:mt-10" aria-label={g.form.steps.purpose}>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4">
          <ChoiceTile lang={lang} tone="traveller" title={g.choose.traveller.title} text={g.choose.traveller.text} className="col-span-2 md:col-span-1" />
          <ChoiceTile lang={lang} tone="send" title={g.choose.send.title} text={g.choose.send.text} />
          <ChoiceTile lang={lang} tone="request" title={g.choose.request.title} text={g.choose.request.text} />
        </div>
        <p className="mt-4 flex max-w-3xl items-start gap-2 text-sm text-muted">
          <HandCoins className="mt-0.5 size-4 shrink-0 text-accent-ink" aria-hidden="true" /> {g.money}
        </p>
      </section>

      {/* ---- The board. ---- */}
      <section className="mx-auto mt-10 max-w-7xl px-4 md:mt-14">
        <h2 className="sr-only">{d.common.results}</h2>
        <div className="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
          <ToggleRow
            action={base}
            name="direction"
            label={g.filters.direction}
            current={direction}
            keep={{ kind: show }}
            options={[{ label: g.filters.both }, { value: "to_et", label: g.filters.to_et }, { value: "to_ae", label: g.filters.to_ae }]}
            className="w-full lg:w-[29rem]"
          />
          <ToggleRow
            action={base}
            name="kind"
            label={g.filters.kind}
            current={show === "sender" ? undefined : show}
            keep={{ direction }}
            options={[{ label: g.filters.all }, { value: "traveller", label: g.filters.traveller }, { value: "send", label: g.filters.send }, { value: "request", label: g.filters.request }]}
            className="w-full lg:w-[29rem]"
          />
        </div>

        <p className="mb-3 mt-5 text-sm text-muted" aria-live="polite">{countLabel(board.meta.total, g.countOne, g.count, lang)}</p>
        {board.data.length === 0 ? (
          <div className="card relative overflow-hidden p-8 text-center md:p-10">
            <div className="tibeb-thin absolute inset-x-0 top-0" aria-hidden="true" />
            <div className="mx-auto flex w-fit gap-2" aria-hidden="true">
              <GoodsBadge post={{ kind: "traveller", mode: null }} className="size-12 -rotate-6 rounded-2xl" />
              <GoodsBadge post={{ kind: "sender", mode: "send" }} className="size-12 rounded-2xl" />
              <GoodsBadge post={{ kind: "sender", mode: "request" }} className="size-12 rotate-6 rounded-2xl" />
            </div>
            <p className="mx-auto mt-5 max-w-md text-muted">{g.empty}</p>
            <div className="mt-5 flex flex-wrap justify-center gap-2">
              <Link href={newPostHref(lang, "traveller")} className="btn btn-primary">{g.choose.traveller.title}</Link>
              <Link href={newPostHref(lang, "send")} className="btn btn-line">{g.choose.send.title}</Link>
            </div>
          </div>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {board.data.map((post, i) => (
              <li key={post.id} className={`reveal ${["", "reveal-2", "reveal-3"][i % 3]}`}>
                <GoodsCard post={post} lang={lang} t={g.card} maxKilos={board.choices.max_kilos} signedIn={Boolean(owner)} />
              </li>
            ))}
          </ul>
        )}
        <Pager
          page={board.meta.page}
          last={board.meta.last_page}
          href={pageHref}
          labels={{ previous: d.common.previous, next: d.common.next, pageOf: fill(d.common.pageOf, { page: board.meta.page, pages: board.meta.last_page }) }}
        />
      </section>

      {/* ---- The rules, the bot's own. ---- */}
      <Section title={g.howTitle} lead={g.howLead}>
        <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {g.how.map((step, i) => (
            <li key={step.title} className="card p-5">
              <span className="geez-mark" aria-hidden="true">{GEEZ[i]}</span>
              <h3 className="headline mt-4 text-[1.1875rem]">{step.title}</h3>
              <p className="mt-1.5 text-sm text-muted">{step.text}</p>
            </li>
          ))}
        </ol>
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          <div className="flex gap-3.5 rounded-[var(--radius-card)] bg-danger-soft p-5">
            <Ban className="mt-0.5 size-5 shrink-0 text-danger" aria-hidden="true" />
            <div>
              <h3 className="title">{g.bannedTitle}</h3>
              <p className="mt-1 text-sm">{g.banned}</p>
            </div>
          </div>
          <div className="flex gap-3.5 rounded-[var(--radius-card)] bg-surface p-5">
            <CalendarClock className="mt-0.5 size-5 shrink-0 text-accent-ink" aria-hidden="true" />
            <p className="text-sm">{g.dates}</p>
          </div>
        </div>
      </Section>
    </>
  );
}
