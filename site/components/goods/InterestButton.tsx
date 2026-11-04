"use client";
import Link from "next/link";
import { useActionState } from "react";
import { CircleAlert, CircleCheck, Info } from "lucide-react";
import { goodsInterest, type InterestState } from "@/lib/actions";
import type { Locale } from "@/lib/locales";

/** What each answer looks like under the button: done, already done, or a reason it cannot go. */
const TONES: Record<string, { tone: string; icon: typeof CircleCheck }> = {
  sent: { tone: "text-success", icon: CircleCheck },
  accepted: { tone: "text-success", icon: CircleCheck },
  already: { tone: "text-muted", icon: Info },
};

/**
 * "I'm interested". A visitor who is not signed in goes to the Telegram
 * sign-in and comes back to this post. A member without a post on the other
 * side is sent to the form, filled in from this post, and the request goes out
 * the moment theirs is posted (the server action redirects). Every other
 * answer, in the member's language, is said right here.
 */
export function InterestButton({ id, lang, signedIn, labels }: {
  id: number;
  lang: Locale;
  signedIn: boolean;
  labels: { interested: string; sending: string; error: string; signInFirst: string };
}) {
  const [state, action, pending] = useActionState<InterestState, FormData>(goodsInterest, {});

  if (!signedIn) {
    return (
      <div className="relative z-10">
        <Link href={`/${lang}/business/login?next=/${lang}/goods/${id}`} className="btn btn-kind w-full">
          {labels.interested}
        </Link>
        <p className="mt-1.5 text-center text-xs text-muted">{labels.signInFirst}</p>
      </div>
    );
  }

  const done = state.status === "sent" || state.status === "accepted" || state.status === "already";
  const { tone, icon: Icon } = TONES[state.status ?? ""] ?? { tone: "text-danger", icon: CircleAlert };

  return (
    <form action={action} className="relative z-10">
      <input type="hidden" name="id" value={id} />
      <input type="hidden" name="lang" value={lang} />
      <button type="submit" className="btn btn-kind w-full" disabled={pending || done}>
        {pending ? labels.sending : labels.interested}
      </button>
      {/* Always in the page, so a screen reader hears the answer. */}
      <div aria-live="polite" className="text-sm">
        {!pending && state.status && (
          <p className={`mt-2 flex items-start gap-1.5 ${tone}`}>
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {state.message || labels.error}
          </p>
        )}
      </div>
    </form>
  );
}
