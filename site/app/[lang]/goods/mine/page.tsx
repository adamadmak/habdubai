import type { Metadata } from "next";
import Link from "@/components/AppLink";
import { notFound, redirect } from "next/navigation";
import { Circle, CircleCheck, CircleX, Eye, EyeOff, Handshake, Hourglass, Inbox, MessagesSquare, Plus, Send, ShieldAlert, Star, type LucideIcon } from "lucide-react";
import { GEEZ, PageHead } from "@/components/Bits";
import { DateLeaf } from "@/components/DateLeaf";
import { MemberGate } from "@/components/goods/GoodsBits";
import { cityCode, GoodsBadge, KiloGauge, PriceChip, toneOf } from "@/components/goods/GoodsCard";
import { DealStars } from "@/components/goods/DealStars";
import { ConfirmSubmit } from "@/components/owner/ConfirmSubmit";
import { answerDelivery, closeGoods } from "@/lib/actions";
import { api, type GoodsBoard, type GoodsDeal, type GoodsPostStatus, type MyGoods, type MyGoodsPost } from "@/lib/api";
import { formatDate, number, shortDate } from "@/lib/format";
import { dict, fill, isLocale, label, pick, type Dictionary, type Locale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).goods.mineTitle, robots: { index: false } };
}

const POST_STATUS: Record<GoodsPostStatus, { tone: string; icon: LucideIcon }> = {
  open: { tone: "bg-success-soft text-success", icon: CircleCheck },
  matched: { tone: "bg-primary-soft text-primary", icon: Handshake },
  expired: { tone: "bg-surface text-muted", icon: Hourglass },
  closed: { tone: "bg-surface text-muted", icon: CircleX },
  hidden: { tone: "bg-danger-soft text-danger", icon: EyeOff },
};

const DELIVERED = { tone: "bg-success-soft text-success", icon: CircleCheck };

/** Each state of a deal: its colour and icon beside the plain line that says what happens next. */
const DEAL_STATUS: Record<string, { tone: string; icon: LucideIcon }> = {
  requested: { tone: "text-warning", icon: Hourglass },
  introduced: { tone: "text-primary", icon: MessagesSquare },
  agreed: { tone: "text-success", icon: Handshake },
  disputed: { tone: "text-danger", icon: ShieldAlert },
  completed: { tone: "text-success", icon: CircleCheck },
};

export default async function MyGoodsPage({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<{ failed?: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  if (!(await currentOwner(lang))) redirect(`/${lang}/business/login?next=/${lang}/goods/mine`);
  const failed = Boolean((await searchParams).failed);
  const d = dict(lang);
  const g = d.goods;
  const [mine, maxKilos] = await Promise.all([
    ownerApi<MyGoods>("/goods", { locale: lang }),
    // The scale a parcel's weight is drawn against, from the board's own choices.
    api<GoodsBoard>("/public/goods").then((board) => board.choices.max_kilos).catch(() => 46),
  ]);
  const bot = mine.bot_link ? `${mine.bot_link}?start=goods` : null;
  const { limits } = mine;

  return (
    <>
      <PageHead title={g.mineTitle} lead={g.mineLead} word="ሰሌዳ">
        {mine.member && !mine.member.blocked && (
          <div className="mt-6 flex flex-wrap gap-2">
            <Link href={`/${lang}/goods/new`} className="btn btn-gold rounded-full">
              <Plus className="size-4" aria-hidden="true" /> {g.newPost}
            </Link>
            <Link href={`/${lang}/goods`} className="btn btn-glass rounded-full">{g.seeBoard}</Link>
          </div>
        )}
      </PageHead>

      <div className="mx-auto max-w-page px-4 lg:px-8">
        {!mine.member ? (
          <div className="mt-8">
            <MemberGate d={d} botLink={mine.bot_link} />
          </div>
        ) : (
          <>
            {mine.member.blocked && (
              <div className="mt-8">
                <MemberGate d={d} botLink={mine.bot_link} blocked />
              </div>
            )}

            {/* Side by side from a wide laptop up: what the member posted, and the deals it led to. */}
            <div className="xl:grid xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] xl:items-start xl:gap-12">
            {/* ---- ፩ The posts. ---- */}
            <section className="mt-10">
              <div className="flex flex-wrap items-end justify-between gap-3">
                <h2 className="flex items-center gap-3">
                  <span className="geez-mark" aria-hidden="true">{GEEZ[0]}</span>
                  <span className="headline text-[1.5rem] md:text-[1.75rem]">{g.posts}</span>
                </h2>
                <p className="text-sm text-muted tabular">
                  {fill(g.limits, { t: number(limits.open_traveller, lang), s: number(limits.open_sender, lang), max: number(limits.max_open, lang) })}
                </p>
              </div>
              {mine.posts.length === 0 ? (
                <div className="card mt-4 p-8 text-center">
                  <p className="text-muted">{g.noPosts}</p>
                  <Link href={`/${lang}/goods/new`} className="btn btn-primary mt-5"><Plus className="size-4" aria-hidden="true" /> {g.newPost}</Link>
                </div>
              ) : (
                <ul className="mt-4 flex flex-col gap-3">
                  {mine.posts.map((post) => <PostRow key={post.id} post={post} lang={lang} d={d} maxKilos={maxKilos} />)}
                </ul>
              )}
            </section>

            {/* ---- ፪ The deals. ---- */}
            <section className="mt-12 xl:mt-10">
              <h2 className="flex items-center gap-3">
                <span className="geez-mark" aria-hidden="true">{GEEZ[1]}</span>
                <span className="headline text-[1.5rem] md:text-[1.75rem]">{g.deals}</span>
              </h2>
              {failed && <p role="alert" className="mt-4 rounded-[var(--radius-card)] bg-danger-soft p-3.5 text-sm font-medium text-danger">{g.card.error}</p>}
              {mine.deals.length === 0 ? (
                <p className="card mt-4 flex items-start gap-3 p-5 text-sm text-muted">
                  <Inbox className="mt-0.5 size-5 shrink-0" aria-hidden="true" /> {g.noDeals}
                </p>
              ) : (
                <ul className="mt-4 flex flex-col gap-2.5">
                  {mine.deals.map((deal) => <DealRow key={deal.id} deal={deal} lang={lang} d={d} />)}
                </ul>
              )}
            </section>
            </div>
          </>
        )}

        {/* ---- Everything after "interested" happens in Telegram. ---- */}
        <section className="night tile mt-12 p-6 md:p-8">
          <div className="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div className="max-w-xl">
              <h2 className="headline text-[1.375rem] md:text-[1.625rem]">{g.telegramTitle}</h2>
              <p className="mt-2 text-sm muted-on-night">{g.telegramText}</p>
            </div>
            {bot && (
              <a href={bot} target="_blank" rel="noopener noreferrer" className="btn btn-gold shrink-0 rounded-full">
                <Send className="size-4" aria-hidden="true" /> {g.openBot}
              </a>
            )}
          </div>
        </section>
      </div>
    </>
  );
}

/** One of the member's posts: the route like a ticket, its state, the room left, and the requests it drew. */
function PostRow({ post, lang, d, maxKilos }: { post: MyGoodsPost; lang: Locale; d: Dictionary; maxKilos: number }) {
  const g = d.goods;
  // A closed post says how it ended: delivered and failed read differently from simply closed.
  const ending = post.status === "closed" && (post.outcome === "delivered" || post.outcome === "failed") ? post.outcome : post.status;
  const status = ending === "delivered" ? DELIVERED : (POST_STATUS[post.status] ?? POST_STATUS.closed);
  const StatusIcon = status.icon;
  const from = label(post.from.name, lang);
  const to = label(post.to.name, lang);
  const tone = toneOf(post);
  const kind = tone === "request" ? fill(g.card.kind.request, { city: from }) : g.card.kind[tone];
  const requests = post.requests === 0 ? g.noRequests : post.requests === 1 ? g.requestOne : fill(g.requests, { n: number(post.requests, lang) });

  return (
    <li className={`card goods-${tone} overflow-hidden`}>
      <div className="flex gap-4 p-4">
        <GoodsBadge post={post} className="hidden size-12 rounded-2xl sm:grid" />
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold text-[var(--kind-ink)]">{kind}</p>
          <div className="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <h3 className="display text-[1.375rem] leading-tight" aria-label={fill(g.card.route, { from, to })}>
              {cityCode(post.from)} → {cityCode(post.to)}
            </h3>
            <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${status.tone}`}>
              <StatusIcon className="size-3.5" aria-hidden="true" /> {g.status[ending] ?? post.status}
            </span>
          </div>
          <p className="mt-0.5 truncate text-sm text-muted">{from} → {to} · {shortDate(post.date, lang)}</p>
          <div className="mt-3 flex max-w-md items-start gap-3">
            <KiloGauge post={post} t={g.card} lang={lang} maxKilos={maxKilos} />
            <PriceChip post={post} t={g.card} lang={lang} />
          </div>
          <p className="mt-2 flex items-center gap-1.5 text-xs text-muted">
            <Inbox className="size-3.5" aria-hidden="true" /> {requests}
          </p>
        </div>
        <DateLeaf date={post.date} lang={lang} className="self-start" />
      </div>
      {post.open && (
        <div className="flex flex-wrap gap-2 border-t border-line bg-surface/60 px-4 py-2.5">
          <Link href={`/${lang}/goods/${post.id}`} className="btn btn-quiet min-h-9 px-3 text-sm">
            <Eye className="size-3.5" aria-hidden="true" /> {g.view}
          </Link>
          <form action={closeGoods} className="ml-auto">
            <input type="hidden" name="lang" value={lang} />
            <input type="hidden" name="id" value={post.id} />
            <ConfirmSubmit text={g.close} confirm={g.closeConfirm} />
          </form>
        </div>
      )}
    </li>
  );
}

/** The plain line for a deal: an agreed one reads differently as the flight passes and each side confirms. */
function dealLine(deal: GoodsDeal, g: Dictionary["goods"]): string {
  const lines = g.dealStatus as Record<string, string>;
  if (deal.status === "requested") return lines[`requested_${deal.role}`] ?? lines.other;
  if (deal.status !== "agreed") return lines[deal.status] ?? lines.other;
  if (deal.said_delivered) return fill(lines.agreed_confirmed, { name: deal.other_name });
  if (deal.they_said_delivered) return fill(lines.agreed_they_said, { name: deal.other_name });
  return deal.can_confirm ? lines.agreed_due : lines.agreed;
}

/** Agreed, flight, delivered, closed: where the deal has got to, once there is one to follow. */
function DealStages({ deal, g }: { deal: GoodsDeal; g: Dictionary["goods"] }) {
  const closed = deal.status === "completed";
  const flown = closed || deal.can_confirm || deal.said_delivered || deal.they_said_delivered;
  const stages = [
    { label: g.stages.agreed, done: true },
    { label: g.stages.flight, done: flown },
    { label: g.stages.delivered, done: closed || deal.said_delivered },
    { label: g.stages.closed, done: closed },
  ];

  return (
    <ol className="mt-3 flex items-center gap-1 text-[0.6875rem] font-semibold">
      {stages.map((stage, i) => (
        <li key={stage.label} className="flex min-w-0 flex-1 items-center gap-1">
          <span className={`inline-flex min-w-0 items-center gap-1 ${stage.done ? "text-success" : "text-faint"}`}>
            {stage.done ? <CircleCheck className="size-3.5 shrink-0" aria-hidden="true" /> : <Circle className="size-3.5 shrink-0" aria-hidden="true" />}
            <span className="truncate">{stage.label}</span>
          </span>
          {i < stages.length - 1 && <span className={`h-px flex-1 ${stage.done ? "bg-success/40" : "bg-line"}`} aria-hidden="true" />}
        </li>
      ))}
    </ol>
  );
}

/** One deal: who, which route, where it stands, and, when it is their turn, the buttons that finish it. */
function DealRow({ deal, lang, d }: { deal: GoodsDeal; lang: Locale; d: Dictionary }) {
  const g = d.goods;
  const look = DEAL_STATUS[deal.status] ?? { tone: "text-muted", icon: CircleX };
  const Icon = look.icon;
  const route = `${pick(deal.route.from, lang)} → ${pick(deal.route.to, lang)}`;
  const facts = [route, formatDate(deal.date, lang, "medium"), deal.kilos ? fill(g.form.kilos, { n: number(deal.kilos, lang) }) : null].filter(Boolean).join(" · ");
  const hidden = (
    <>
      <input type="hidden" name="lang" value={lang} />
      <input type="hidden" name="id" value={deal.id} />
    </>
  );

  return (
    <li className="card flex gap-3.5 p-4">
      <span className="grid size-10 shrink-0 place-items-center rounded-full bg-night text-sm font-bold text-white ring-2 ring-accent/50" aria-hidden="true">
        {Array.from(deal.other_name)[0]?.toLocaleUpperCase() ?? "·"}
      </span>
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{fill(g.role[deal.role] ?? g.role.requester, { name: deal.other_name })}</p>
        <p className="mt-0.5 text-sm text-muted">{facts}</p>
        <p className={`mt-1.5 flex items-start gap-1.5 text-sm font-medium ${look.tone}`}>
          <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {dealLine(deal, g)}
        </p>

        {(deal.status === "agreed" || deal.status === "completed") && <DealStages deal={deal} g={g} />}

        {deal.can_confirm && (
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <form action={answerDelivery}>
              {hidden}
              <input type="hidden" name="answer" value="yes" />
              <button type="submit" className="btn btn-primary min-h-10 px-4 text-sm">
                <CircleCheck className="size-4" aria-hidden="true" /> {g.delivered.yes}
              </button>
            </form>
            <form action={answerDelivery}>
              {hidden}
              <input type="hidden" name="answer" value="wait" />
              <button type="submit" className="btn btn-quiet min-h-10 px-3 text-sm">{g.delivered.wait}</button>
            </form>
            <form action={answerDelivery}>
              {hidden}
              <input type="hidden" name="answer" value="no" />
              <ConfirmSubmit text={g.delivered.no} confirm={g.delivered.noConfirm} />
            </form>
          </div>
        )}

        {deal.can_rate && (
          <DealStars
            id={deal.id}
            lang={lang}
            title={fill(g.delivered.rate, { name: deal.other_name })}
            note={g.delivered.rateNote}
            group={g.delivered.group}
            starLabels={[1, 2, 3, 4, 5].map((stars) => (stars === 1 ? g.delivered.starOne : fill(g.delivered.star, { n: stars })))}
            error={g.card.error}
          />
        )}

        {deal.my_stars != null && (
          <p className="mt-2 flex items-center gap-1.5 text-xs text-muted tabular">
            <Star className="size-3.5 fill-accent text-accent" aria-hidden="true" />
            {deal.my_stars === 1 ? fill(g.delivered.yourStarsOne, { name: deal.other_name }) : fill(g.delivered.yourStars, { name: deal.other_name, n: number(deal.my_stars, lang) })}
          </p>
        )}
      </div>
    </li>
  );
}
