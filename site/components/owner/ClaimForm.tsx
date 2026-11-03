"use client";
import { startTransition, useActionState, type FormEvent } from "react";
import { CircleCheck } from "lucide-react";
import { claimListing, type FormState } from "@/lib/actions";

export function ClaimForm({ businessId, labels }: { businessId: number; labels: { label: string; submit: string; sent: string; error: string } }) {
  const [state, action, pending] = useActionState<FormState, FormData>(claimListing, { ok: false });

  if (state.ok) {
    return (
      <p className="flex items-center gap-2 rounded-2xl bg-success-soft p-4 font-medium text-success" role="status">
        <CircleCheck className="size-5" aria-hidden="true" /> {labels.sent}
      </p>
    );
  }

  const error = state.errors?.note?.[0] ?? (state.message && state.message !== "signin" ? (state.message === "error" ? labels.error : state.message) : null);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    startTransition(() => action(data));
  };

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <input type="hidden" name="business_id" value={businessId} />
      <div>
        <label htmlFor="note" className="mb-1.5 block text-sm font-semibold">{labels.label}</label>
        <textarea id="note" name="note" rows={4} maxLength={500} className="field" aria-invalid={error ? true : undefined} />
        {error && <p className="mt-1.5 text-sm text-danger">{error}</p>}
      </div>
      <div><button type="submit" className="btn btn-primary" disabled={pending}>{labels.submit}</button></div>
    </form>
  );
}
