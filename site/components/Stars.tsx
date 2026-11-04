"use client";
import { useState } from "react";
import { Star } from "lucide-react";

/**
 * Five stars to press, each a submit button of the form around it: the form works
 * before the page is live, and the stars light up to the one under the pointer.
 * The form around it must have no field named "id" (see DealStars).
 */
export function StarPicker({ value, disabled = false, label, starLabels, name = "stars" }: {
  value: number | null;
  disabled?: boolean;
  /** What the row as a whole is, for a screen reader. */
  label: string;
  /** What each star says to a screen reader, one to five: plain words, so a server page can hand them in. */
  starLabels: string[];
  name?: string;
}) {
  const [hover, setHover] = useState(0);
  const lit = hover || value || 0;

  return (
    <div role="group" aria-label={label} className="flex" onMouseLeave={() => setHover(0)}>
      {[1, 2, 3, 4, 5].map((stars) => (
        <button
          key={stars}
          type="submit"
          name={name}
          value={stars}
          disabled={disabled}
          aria-label={starLabels[stars - 1]}
          aria-pressed={value === stars}
          onMouseEnter={() => setHover(stars)}
          onFocus={() => setHover(stars)}
          onBlur={() => setHover(0)}
          className="press grid size-11 place-items-center rounded-full disabled:opacity-60"
        >
          <Star className={`size-7 ${stars <= lit ? "fill-accent text-accent" : "text-faint"}`} aria-hidden="true" />
        </button>
      ))}
    </div>
  );
}
