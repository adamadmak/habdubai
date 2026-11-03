import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight, Bell, BellOff, Briefcase, Building2, CircleCheck, Hourglass, MessageCircleQuestion, Package, Send, ShieldCheck, Star, type LucideIcon } from "lucide-react";
import { GEEZ, PageHead } from "@/components/Bits";
import { Night } from "@/components/Dawn";
import { LoginPanel } from "@/components/owner/LoginPanel";
import { api, type Meta, type OwnerHome } from "@/lib/api";
import { formatDate, number } from "@/lib/format";
import { dict, fill, isLocale, label, type Dictionary, type Locale } from "@/lib/i18n";
import { currentOwner, ownerApi } from "@/lib/owner";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  return { title: dict(lang).account.title, robots: { index: false } };
}

/**
 * My Habesha: the member's own page. Signed out it is the Telegram sign-in
 * with what the page will hold; signed in it is who they are in the
 * community and what they have going on. Everything here is read: a deal,
 * a question or an alert is handled where it was started.
 */
export default async function AccountPage({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const owner = await currentOwner(lang);

  if (!owner) return <SignedOut lang={lang} d={d} />;

  const [home, meta] = await Promise.all([
    ownerApi<OwnerHome>("/home", { locale: lang }),
    api<Meta>("/public/meta", { revalidate: 300 }).catch(() => null),
  ]);

  return <Home lang={lang} d={d} home={home} meta={meta} />;
}

function SignedOut({ lang, d }: { lang: Locale; d: Dictionary }) {
  const a = d.account;
  const b = d.business;

  return (
    <>
      <PageHead title={a.signInTitle} lead={a.signInLead} />
      <div className="mx-auto grid max-w-7xl gap-8 px-4 md:grid-cols-[1fr_0.9fr] md:gap-12">
        <div className="mt-8">
          <LoginPanel
            lang={lang}
            next={`/${lang}/account`}
            labels={{
              phone: b.loginPhone, desktop: b.loginDesktop, open: b.openTelegram, waiting: b.waiting, codeTitle: b.codeTitle, codeHint: b.codeHint,
              codeSubmit: b.codeSubmit, codeWrong: b.codeWrong, locked: b.locked, busy: b.busy, expired: b.expired, declined: b.declined,
              retry: b.retry, done: b.done, unavailable: b.unavailable, privacy: b.privacy, network: b.network,
            }}
          />
          <p className="mt-6 text-sm text-muted">
            {d.legal.beforeSignIn}{" "}
            <Link href={`/${lang}/terms`} className="font-semibold text-primary">{d.legal.termsLink}</Link>
            <span aria-hidden="true"> · </span>
            <Link href={`/${lang}/privacy`} className="font-semibold text-primary">{d.legal.privacyLink}</Link>
          </p>
        </div>

        {/* What the page holds once they are in. */}
        <ol className="reveal mt-2 grid gap-3 sm:grid-cols-2 md:mt-8 md:grid-cols-1">
          {a.perks.map((perk, i) => (
            <li key={perk.title} className="card relative overflow-hidden p-5">
              <span className="geez-mark" aria-hidden="true">{GEEZ[i]}</span>
              <h2 className="headline mt-3 text-[1.125rem]">{perk.title}</h2>
              <p className="mt-1 text-sm text-muted">{perk.text}</p>
            </li>
          ))}
        </ol>
      </div>
    </>
  );
}

function Home({ lang, d, home, meta }: { lang: Locale; d: Dictionary; home: OwnerHome; meta: Meta | null }) {
  const a = d.account;
  const m = home.member;
  const name = m?.name ?? home.owner.name;
  const emirate = m ? label(meta?.labels.emirates[m.emirate] ?? { en: m.emirate }, lang) || m.emirate : "";
  const trades = m ? m.trades.map((t) => label(meta?.labels.trades[t] ?? { en: t }, lang) || t) : [];
  const botLink = home.links.bot ?? meta?.bot.link ?? null;

  return (
    <>
      <Night>
        <div className="mx-auto max-w-7xl px-4 pb-10 pt-8 md:pb-14 md:pt-12">
          <div className="settle flex flex-wrap items-start justify-between gap-6">
            <div className="min-w-0">
              <p className="glass-white inline-flex items-center gap-2 rounded-full py-1.5 pl-1.5 pr-3.5 text-[0.8125rem] font-semibold">
                <span className="tibeb-thin inline-block w-7 rounded-full" aria-hidden="true" /> {a.title}
              </p>
              <h1 className="display mt-5 text-[2.25rem] md:text-[3.25rem]">{fill(a.hello, { name })}</h1>
              {m && (
                <div className="mt-5 flex flex-wrap gap-2 text-[0.8125rem] font-semibold">
                  <span className="glass-white inline-flex items-center gap-1.5 rounded-full px-3 py-1.5">
                    <ShieldCheck className="size-4 text-gild" aria-hidden="true" /> {fill(a.verifiedSince, { date: formatDate(m.verified_since, lang, "medium") })}
                  </span>
                  {emirate && <span className="glass-white inline-flex items-center rounded-full px-3 py-1.5">{fill(a.lives, { emirate })}</span>}
                  {trades.map((trade) => (
                    <span key={trade} className="glass-white inline-flex items-center rounded-full px-3 py-1.5">{trade}</span>
                  ))}
                </div>
              )}
            </div>
            <form action="/api/auth/logout" method="post" className="shrink-0">
              <input type="hidden" name="lang" value={lang} />
              <button type="submit" className="btn btn-glass rounded-full">{a.signOut}</button>
            </form>
          </div>

          {m && (
            <div className="settle settle-2 mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
              <Stat label={a.points} value={number(m.helper_points, lang)} note={m.rank ? a.rank[m.rank] : a.noRank} icon={Star} />
              <Stat label={a.deals} value={number(m.trips_completed, lang)} note={m.rating ? fill(a.rating, { avg: m.rating.avg.toFixed(1), count: number(m.rating.count, lang) }) : a.noRating} icon={Package} />
              <Stat label={a.tiles.questions} value={number(home.questions?.total ?? 0, lang)} note={fill(a.tiles.questionsLine, { open: number(home.questions?.open ?? 0, lang), total: number(home.questions?.total ?? 0, lang) })} icon={MessageCircleQuestion} />
            </div>
          )}
        </div>
      </Night>

      <div className="mx-auto max-w-7xl px-4">
        {!m && (
          <div className="card reveal relative mt-8 overflow-hidden p-6 md:p-8">
            <div className="tibeb-thin absolute inset-x-0 top-0" aria-hidden="true" />
            <h2 className="headline mt-2 text-[1.5rem]">{a.notMemberTitle}</h2>
            <p className="mt-2 max-w-xl text-muted">{a.notMemberText}</p>
            {botLink && (
              <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-gold mt-6 min-h-12 rounded-full px-6 text-base">
                <Send className="size-4.5" aria-hidden="true" /> {a.joinCta}
              </a>
            )}
          </div>
        )}

        <div className="reveal mt-8 grid gap-4 md:grid-cols-2">
          {m && home.goods && (
            <Tile icon={Package} title={a.tiles.goods} line={fill(a.tiles.goodsLine, { open: number(home.goods.open_posts, lang), deals: number(home.goods.live_deals, lang) })}>
              <Link href={`/${lang}/goods/mine`} className="btn btn-primary mt-5 rounded-full">
                {a.tiles.goodsCta} <ArrowRight className="size-4 rtl:rotate-180" aria-hidden="true" />
              </Link>
            </Tile>
          )}

          {m && home.questions && (
            <Tile icon={MessageCircleQuestion} title={a.tiles.questions} line={home.questions.total === 0 ? a.tiles.questionsEmpty : fill(a.tiles.questionsLine, { open: number(home.questions.open, lang), total: number(home.questions.total, lang) })}>
              {home.questions.latest.length > 0 && (
                <ul className="mt-4 flex flex-col divide-y divide-line">
                  {home.questions.latest.map((q) => (
                    <li key={q.id} className="flex items-center gap-3 py-2.5 text-sm">
                      {q.status === "solved" ? <CircleCheck className="size-4 shrink-0 text-success" aria-hidden="true" /> : <Hourglass className="size-4 shrink-0 text-warning" aria-hidden="true" />}
                      <span className="min-w-0 flex-1 truncate font-medium">{q.title}</span>
                      <span className="shrink-0 text-muted">{fill(a.replies, { n: number(q.replies, lang) })}</span>
                    </li>
                  ))}
                </ul>
              )}
              {botLink && (
                <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-line mt-5 rounded-full">
                  <Send className="size-4" aria-hidden="true" /> {a.tiles.questionsCta}
                </a>
              )}
            </Tile>
          )}

          {m && home.jobs && (
            <Tile icon={Briefcase} title={a.tiles.jobs} line={`${home.jobs.alerts_on ? a.tiles.alertsOn : a.tiles.alertsOff} · ${fill(a.tiles.savedCount, { n: number(home.jobs.saved_count, lang) })}`} badge={home.jobs.alerts_on ? <Bell className="size-4 text-success" aria-hidden="true" /> : <BellOff className="size-4 text-muted" aria-hidden="true" />}>
              {home.jobs.saved.length > 0 && (
                <ul className="mt-4 flex flex-col divide-y divide-line">
                  {home.jobs.saved.map((job) => (
                    <li key={job.id} className="py-2.5 text-sm">
                      <Link href={`/${lang}/jobs/${job.id}`} className="flex items-center gap-3">
                        <span className={`min-w-0 flex-1 truncate font-medium ${job.open ? "" : "text-muted line-through"}`}>{job.title}</span>
                        {job.company && <span className="shrink-0 truncate text-muted">{job.company}</span>}
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
              <div className="mt-5 flex flex-wrap gap-2">
                <Link href={`/${lang}/jobs`} className="btn btn-primary rounded-full">{a.tiles.jobsCta}</Link>
                {botLink && (
                  <a href={`${botLink}?start=jobs`} target="_blank" rel="noopener noreferrer" className="btn btn-line rounded-full">
                    <Send className="size-4" aria-hidden="true" /> {a.tiles.alertsCta}
                  </a>
                )}
              </div>
            </Tile>
          )}

          <Tile icon={Building2} title={a.tiles.business} line={fill(a.tiles.businessLine, { approved: number(home.businesses.approved, lang), pending: number(home.businesses.pending, lang), claims: number(home.businesses.claims_pending, lang) })}>
            <div className="mt-5 flex flex-wrap gap-2">
              <Link href={`/${lang}/business`} className="btn btn-primary rounded-full">{a.tiles.businessCta}</Link>
              <Link href={`/${lang}/business/new`} className="btn btn-line rounded-full">{a.tiles.businessNew}</Link>
            </div>
          </Tile>
        </div>

        {/* The doors back into Telegram. */}
        {(home.links.channel || home.links.questions_group || home.links.goods_group || botLink) && (
          <section className="reveal mt-10">
            <h2 className="headline text-[1.25rem]">{a.community}</h2>
            <div className="mt-3 flex flex-wrap gap-2">
              {home.links.channel && <a href={home.links.channel} target="_blank" rel="noopener noreferrer" className="btn btn-glass rounded-full">📣 {a.channel}</a>}
              {home.links.questions_group && <a href={home.links.questions_group} target="_blank" rel="noopener noreferrer" className="btn btn-glass rounded-full">❓ {a.questionsGroup}</a>}
              {home.links.goods_group && <a href={home.links.goods_group} target="_blank" rel="noopener noreferrer" className="btn btn-glass rounded-full">📦 {a.goodsGroup}</a>}
              {botLink && <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-glass rounded-full"><Send className="size-4" aria-hidden="true" /> {a.openBot}</a>}
            </div>
          </section>
        )}
      </div>
    </>
  );
}

/** One number on the night band: frosted white, the figure large, a word under it. */
function Stat({ label, value, note, icon: Icon }: { label: string; value: string; note: string; icon: LucideIcon }) {
  return (
    <div className="glass-white rounded-[1.375rem] p-4">
      <div className="flex items-center gap-2 text-[0.8125rem] font-semibold text-muted">
        <Icon className="size-4 text-gild" aria-hidden="true" /> {label}
      </div>
      <div className="display mt-1.5 text-[2rem] leading-none">{value}</div>
      <div className="mt-1.5 text-[0.8125rem] text-muted">{note}</div>
    </div>
  );
}

/** One part of the member's life here: a title, one line of figures, and the way to it. */
function Tile({ icon: Icon, title, line, badge, children }: { icon: LucideIcon; title: string; line: string; badge?: React.ReactNode; children?: React.ReactNode }) {
  return (
    <section className="card p-5 md:p-6">
      <div className="flex items-center gap-3">
        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
          <Icon className="size-5" aria-hidden="true" />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="headline text-[1.125rem]">{title}</h2>
          <p className="mt-0.5 text-sm text-muted">{line}</p>
        </div>
        {badge}
      </div>
      {children}
    </section>
  );
}
