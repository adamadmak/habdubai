import { Medal, Star } from "lucide-react";

/** "★ 4.6 (12)": the stars a member or a place holds, for lists and cards. Nothing before anyone has rated. */
export function Score({ rating, ratings, label, className = "" }: { rating: number | null; ratings: number; label: string; className?: string }) {
  // Loose on purpose: an answer cached from before ratings existed has neither field.
  if (rating == null || !ratings) return null;

  return (
    <span className={`inline-flex items-center gap-1 font-semibold tabular ${className}`} aria-label={label}>
      <Star className="size-3.5 fill-accent text-accent" aria-hidden="true" />
      {rating.toFixed(1)}
      <span className="font-normal text-muted">({ratings})</span>
    </span>
  );
}

/** Earned, not bought: three or more ratings averaging 4.5 or better. */
export function TopRated({ label, className = "" }: { label: string; className?: string }) {
  return (
    <span className={`inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs font-semibold text-accent-ink ${className}`}>
      <Medal className="size-3.5" aria-hidden="true" /> {label}
    </span>
  );
}
