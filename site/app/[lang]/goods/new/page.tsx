import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { ChevronLeft } from "lucide-react";
import { Notice, PageHead } from "@/components/Bits";
import { MemberGate } from "@/components/goods/GoodsBits";
import { GoodsForm, type Purpose } from "@/components/goods/GoodsForm";
import { api, GOODS_DIRECTIONS, query, type GoodsBoard, type GoodsDirection, type MyGoods } from "@/lib/api";
import { addDays, todayInDubai } from "@/lib/format";
import { dict, fill, isLocale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

type Search = { kind?: string; mode?: string; direction?: string; date?: string; interest?: string };

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.goods.form.title, description: d.goods.form.lead, robots: { index: false } };
}

export default async function NewGoodsPost({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<Search> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const sp = await searchParams;

  // What the form starts from: a choice on the board, or a post someone was interested in ("need_post").
  const today = todayInDubai();
  const interest = /^\d{1,10}$/.test(sp.interest ?? "") ? Number(sp.interest) : null;
  const askedPurpose: Purpose = sp.kind === "traveller" ? "traveller" : sp.kind === "sender" && sp.mode === "request" ? "request" : "send";
  const directionGiven = (GOODS_DIRECTIONS as readonly string[]).includes(sp.direction ?? "");
  // Without one, a thing to be brought starts from home to the Emirates, anything else the other way.
  const direction: GoodsDirection = directionGiven ? (sp.direction as GoodsDirection) : askedPurpose === "request" ? "to_ae" : "to_et";
  const askedDate = /^\d{4}-\d{2}-\d{2}$/.test(sp.date ?? "") && (sp.date as string) >= today ? (sp.date as string) : "";

  // This page's own address, for coming back after signing in.
  const here = `/${lang}/goods/new${query({ kind: sp.kind === "traveller" || sp.kind === "sender" ? sp.kind : undefined, mode: sp.mode === "request" || sp.mode === "send" ? sp.mode : undefined, direction: directionGiven ? direction : undefined, date: askedDate || undefined, interest })}`;
  if (!(await currentOwner(lang))) redirect(`/${lang}/business/login?next=${encodeURIComponent(here)}`);

  const d = dict(lang);
  const g = d.goods;
  const [mine, board] = await Promise.all([ownerApi<MyGoods>("/goods", { locale: lang }), api<GoodsBoard>("/public/goods")]);
  const choices = board.choices;
  const lastDay = addDays(today, choices.max_days_ahead);
  const { limits, member } = mine;
  const fullTraveller = limits.open_traveller >= limits.max_open;
  const fullSender = limits.open_sender >= limits.max_open;

  // A kind the member has no room for left is not the one the form opens on.
  const purpose: Purpose =
    askedPurpose === "traveller" && fullTraveller && !fullSender ? "send" : askedPurpose !== "traveller" && fullSender && !fullTraveller ? "traveller" : askedPurpose;

  return (
    <>
      <PageHead title={g.form.title} lead={g.form.lead} word="ሰሌዳ" />
      <div className="mx-auto max-w-7xl px-4">
        <Link href={`/${lang}/goods`} className="press mt-6 inline-flex items-center gap-1 text-sm font-semibold text-primary">
          <ChevronLeft className="size-4" aria-hidden="true" /> {g.detail.back}
        </Link>

        <div className="mt-6">
          {!member ? (
            <MemberGate d={d} botLink={mine.bot_link} />
          ) : member.blocked ? (
            <MemberGate d={d} botLink={mine.bot_link} blocked />
          ) : fullTraveller && fullSender ? (
            <div className="max-w-2xl">
              <Notice tone="warning">
                {fill(g.form.full, { max: limits.max_open })}{" "}
                <Link href={`/${lang}/goods/mine`} className="font-semibold text-primary underline underline-offset-2">{g.mine}</Link>
              </Notice>
            </div>
          ) : (
            <GoodsForm
              lang={lang}
              g={g}
              choices={choices}
              member={{ name: member.name, verified_since: member.verified_since, rating: member.rating, ratings: member.ratings, deals: member.deals }}
              limits={limits}
              initial={{ purpose, direction, directionGiven, date: askedDate && askedDate <= lastDay ? askedDate : "", interest }}
              today={today}
              lastDay={lastDay}
              here={here}
              rulesHref={`/${lang}/about#rules`}
              labels={{ sessionExpired: d.business.sessionExpired, draftKept: d.business.draftKept, signInAgain: d.business.signInAgain, errorText: d.common.errorText }}
            />
          )}
        </div>
      </div>
    </>
  );
}
