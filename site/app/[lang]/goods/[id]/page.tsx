import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Ban, ChevronLeft, HandCoins } from "lucide-react";
import { GEEZ } from "@/components/Bits";
import { cityCode, GoodsCard, toneOf } from "@/components/goods/GoodsCard";
import { apiOrNull, type GoodsCard as Post } from "@/lib/api";
import { shortDate } from "@/lib/format";
import { dict, fill, isLocale, label } from "@/lib/i18n";
import { currentOwner } from "@/lib/owner";

type Params = { lang: string; id: string };
type Detail = { post: Post; matches: Post[] };

async function load(id: string): Promise<Detail | null> {
  return /^\d{1,10}$/.test(id) ? apiOrNull<Detail>(`/public/goods/${id}`, { revalidate: 30 }) : null;
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { lang, id } = await params;
  if (!isLocale(lang)) return {};
  const data = await load(id);
  if (!data) return {};
  const p = data.post;
  // A member's trip or parcel lasts days and names them: shared by link, never indexed.
  return {
    title: `${cityCode(p.from)} → ${cityCode(p.to)} · ${shortDate(p.date, lang)}`,
    description: fill(dict(lang).goods.card.route, { from: label(p.from.name, lang), to: label(p.to.name, lang) }),
    robots: { index: false },
  };
}

/** One post, on its own: what a shared link or "I'm interested" before signing in comes back to. */
export default async function GoodsPostPage({ params }: { params: Promise<Params> }) {
  const { lang, id } = await params;
  if (!isLocale(lang)) notFound();
  const [data, owner] = await Promise.all([load(id), currentOwner(lang).catch(() => null)]);
  if (!data) notFound();
  const d = dict(lang);
  const g = d.goods;
  const { post, matches } = data;
  const tone = toneOf(post);

  return (
    <div className={`goods-${tone} mx-auto max-w-6xl px-4 pt-6 md:pt-10`}>
      <Link href={`/${lang}/goods`} className="press inline-flex items-center gap-1 text-sm font-semibold text-primary">
        <ChevronLeft className="size-4" aria-hidden="true" /> {g.detail.back}
      </Link>

      <div className="mt-6 grid gap-8 md:grid-cols-[minmax(0,24rem)_1fr] md:gap-12">
        <div>
          <h1 className="sr-only">{fill(g.card.route, { from: label(post.from.name, lang), to: label(post.to.name, lang) })}</h1>
          <div className="settle">
            <GoodsCard post={post} lang={lang} t={g.card} signedIn={Boolean(owner)} linked={false} />
          </div>
        </div>

        <div className="settle settle-2 flex flex-col gap-3 self-start">
          <ol className="flex flex-col gap-3">
            {g.how.slice(1, 3).map((step, i) => (
              <li key={step.title} className="card flex gap-3.5 p-4">
                <span className="geez-mark" aria-hidden="true">{GEEZ[i]}</span>
                <span>
                  <span className="title block">{step.title}</span>
                  <span className="mt-1 block text-sm text-muted">{step.text}</span>
                </span>
              </li>
            ))}
          </ol>
          <p className="flex items-start gap-2.5 rounded-[var(--radius-card)] bg-surface p-4 text-sm">
            <HandCoins className="mt-0.5 size-4.5 shrink-0 text-accent-ink" aria-hidden="true" /> {g.money}
          </p>
          <p className="flex items-start gap-2.5 rounded-[var(--radius-card)] bg-danger-soft p-4 text-sm">
            <Ban className="mt-0.5 size-4.5 shrink-0 text-danger" aria-hidden="true" />
            <span><span className="font-semibold">{g.bannedTitle}:</span> {g.banned}</span>
          </p>
        </div>
      </div>

      {matches.length > 0 && (
        <section className="reveal mt-16">
          <h2 className="headline text-[1.625rem] md:text-[2.125rem]">{g.detail.matches}</h2>
          <p className="mt-1.5 max-w-2xl text-sm text-muted">{g.detail.matchesLead}</p>
          <ul className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {matches.map((m) => (
              <li key={m.id}>
                <GoodsCard post={m} lang={lang} t={g.card} signedIn={Boolean(owner)} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
