"use client";
import Link from "@/components/AppLink";
import { usePathname } from "next/navigation";
import { startTransition, useActionState, useEffect, useRef, type FormEvent } from "react";
import { CircleAlert, CircleCheck } from "lucide-react";
import { claimListing, type FormState } from "@/lib/actions";
import type { Locale } from "@/lib/locales";
import { clearDraft, keepDraft, restoreDraft } from "./draft";

type Labels = { label: string; submit: string; sent: string; error: string; sessionExpired: string; draftKept: string; signInAgain: string };

export function ClaimForm({ businessId, lang, labels }: { businessId: number; lang: Locale; labels: Labels }) {
  const [state, action, pending] = useActionState<FormState, FormData>(claimListing, { ok: false });
  const form = useRef<HTMLFormElement>(null);
  const pathname = usePathname();
  const draftKey = `claim:${businessId}`;

  const noteError = state.errors?.note?.[0];
  // Anything that is not about the note itself, said beside the button.
  const general = state.ok || state.message === "signin" || noteError ? null : state.message === "error" ? labels.error : state.message;

  // Back from signing in again: the note typed before the session ran out.
  useEffect(() => {
    if (form.current) restoreDraft(draftKey, form.current);
  }, [draftKey]);

  useEffect(() => {
    if (state.ok) clearDraft(draftKey);
    else if (state.message === "signin" && form.current) keepDraft(draftKey, form.current);
    else if (noteError) form.current?.querySelector<HTMLElement>("#note")?.focus();
  }, [state, draftKey, noteError]);

  if (state.ok) {
    return (
      <p className="flex items-center gap-2 rounded-2xl bg-success-soft p-4 font-medium text-success" role="status">
        <CircleCheck className="size-5" aria-hidden="true" /> {labels.sent}
      </p>
    );
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    startTransition(() => action(data));
  };

  return (
    <form ref={form} onSubmit={submit} className="flex flex-col gap-4">
      <input type="hidden" name="business_id" value={businessId} />
      <input type="hidden" name="lang" value={lang} />
      <div>
        <label htmlFor="note" className="mb-1.5 block text-sm font-semibold">{labels.label}</label>
        <textarea
          id="note"
          name="note"
          rows={4}
          maxLength={500}
          className="field"
          aria-invalid={noteError ? true : undefined}
          aria-describedby={noteError ? "note-error" : undefined}
        />
        {noteError && <p id="note-error" className="mt-1.5 text-sm text-danger">{noteError}</p>}
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <button type="submit" className="btn btn-primary" disabled={pending}>{labels.submit}</button>
        {/* Always in the page, so a screen reader hears each new answer. */}
        <div className="min-w-[12rem] flex-1 text-sm" aria-live="polite">
          {!pending && state.message === "signin" && (
            <p className="text-danger">
              {labels.sessionExpired} {labels.draftKept}{" "}
              <Link href={`/${lang}/business/login?next=${encodeURIComponent(pathname)}`} className="font-semibold text-primary underline underline-offset-2">
                {labels.signInAgain}
              </Link>
            </p>
          )}
          {!pending && general && (
            <p className="flex items-start gap-1.5 text-danger">
              <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {general}
            </p>
          )}
        </div>
      </div>
    </form>
  );
}
