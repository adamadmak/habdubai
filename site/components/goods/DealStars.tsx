"use client";
import { useActionState } from "react";
import { rateDeal, type DealRatingState } from "@/lib/actions";
import type { Locale } from "@/lib/locales";
import { StarPicker } from "../Stars";

/** Stars for the other side of a deal. Once given, the page draws the deal again with what they gave. */
export function DealStars({ id, lang, title, note, group, starLabels, error }: {
  id: number;
  lang: Locale;
  title: string;
  note: string;
  group: string;
  starLabels: string[];
  error: string;
}) {
  const [state, action, pending] = useActionState<DealRatingState, FormData>(rateDeal, {});

  return (
    <form action={action} className="mt-3 border-t border-line pt-3">
      <input type="hidden" name="lang" value={lang} />
      {/* Not name="id": a field called id hides the form's own id, and React then leaves the pressed star out of what it sends. */}
      <input type="hidden" name="deal" value={id} />
      <p className="text-sm font-semibold">{title}</p>
      <div className="-ml-1.5 mt-1">
        <StarPicker value={null} disabled={pending} label={group} starLabels={starLabels} />
      </div>
      <p className="mt-1 text-xs text-muted" aria-live="polite">
        {state.failed ? <span className="text-danger">{error}</span> : note}
      </p>
    </form>
  );
}
