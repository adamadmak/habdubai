"use client";
import Link from "next/link";
import { useActionState } from "react";
import { CircleCheck, Star } from "lucide-react";
import type { Dictionary } from "@/i18n/en";
import { rateBusiness, type RatingState } from "@/lib/actions";
import { fill } from "@/lib/fill";
import type { Locale } from "@/lib/locales";
import { StarPicker } from "./Stars";
import { TopRated } from "./Score";

/**
 * A place's stars: the average, how they fall, and five to press. Someone signed
 * in as a verified member rates here and sees the new average at once; anyone
 * else is told plainly what it takes.
 */
export function BusinessRating({ slug, lang, labels, rating, ratings, top, breakdown, mine, signedIn, blockedBy, botLink }: {
  slug: string;
  lang: Locale;
  labels: Dictionary["directory"]["rating"];
  rating: number | null;
  ratings: number;
  top: boolean;
  breakdown: { stars: number; count: number }[];
  mine: number | null;
  signedIn: boolean;
  blockedBy: "not_member" | "own" | null;
  botLink: string | null;
}) {
  const [state, action, pending] = useActionState<RatingState, FormData>(rateBusiness, {});
  const saved = state.status === "saved";

  const shownRating = saved ? (state.rating ?? null) : rating;
  const shownCount = saved ? (state.ratings ?? 0) : ratings;
  const shownTop = saved ? Boolean(state.top) : top;
  const shownMine = saved ? (state.stars ?? null) : mine;
  // The bars are from when the page was drawn; after a new rating, the average above is what is current.
  const widest = Math.max(1, ...breakdown.map((row) => row.count));

  return (
    <section className="card mt-8 p-5 md:p-6" aria-labelledby="rating-title">
      <h2 id="rating-title" className="headline text-2xl">{labels.title}</h2>

      <div className="mt-4 flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
        <div className="shrink-0">
          {shownRating != null && shownCount > 0 ? (
            <>
              <p className="display flex items-center gap-2 text-[2.75rem] leading-none tabular">
                {shownRating.toFixed(1)}
                <Star className="size-7 fill-accent text-accent" aria-hidden="true" />
              </p>
              <p className="mt-1.5 text-sm text-muted">{shownCount === 1 ? labels.countOne : fill(labels.count, { n: shownCount })}</p>
              {shownTop && <TopRated label={labels.top} className="mt-2" />}
            </>
          ) : (
            <p className="max-w-[14rem] text-sm text-muted">{labels.none}</p>
          )}
        </div>

        {!saved && ratings > 0 && (
          <ul className="min-w-0 flex-1 space-y-1" aria-hidden="true">
            {breakdown.map((row) => (
              <li key={row.stars} className="flex items-center gap-2 text-xs text-muted tabular">
                <span className="w-3 text-right">{row.stars}</span>
                <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-surface">
                  <span className="block h-full rounded-full bg-accent" style={{ width: `${(row.count / widest) * 100}%` }} />
                </span>
                <span className="w-5">{row.count}</span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="mt-5 border-t border-line pt-4">
        {!signedIn ? (
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted">{labels.signInNote}</p>
            <Link href={`/${lang}/business/login?next=/${lang}/directory/${slug}`} className="btn btn-quiet shrink-0">
              <Star className="size-4" aria-hidden="true" /> {labels.signIn}
            </Link>
          </div>
        ) : blockedBy === "own" ? (
          <p className="text-sm text-muted">{labels.own}</p>
        ) : blockedBy === "not_member" ? (
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted">{labels.join}</p>
            {botLink && <a href={botLink} target="_blank" rel="noopener noreferrer" className="btn btn-quiet shrink-0">{labels.joinCta}</a>}
          </div>
        ) : (
          <form action={action}>
            <input type="hidden" name="slug" value={slug} />
            <input type="hidden" name="lang" value={lang} />
            <p className="text-sm font-semibold">{shownMine ? labels.yours : labels.rateIt}</p>
            <div className="-ml-1.5 mt-1">
              <StarPicker
                value={shownMine}
                disabled={pending}
                label={labels.group}
                starLabels={[1, 2, 3, 4, 5].map((stars) => (stars === 1 ? labels.starOne : fill(labels.star, { n: stars })))}
              />
            </div>
            <p className="mt-1 min-h-5 text-xs" aria-live="polite">
              {saved ? (
                <span className="inline-flex items-center gap-1 font-medium text-success"><CircleCheck className="size-3.5" aria-hidden="true" /> {labels.thanks}</span>
              ) : state.status ? (
                <span className="text-danger">{labels.error}</span>
              ) : shownMine ? (
                <span className="text-muted">{labels.change}</span>
              ) : null}
            </p>
          </form>
        )}
      </div>
    </section>
  );
}
