"use client";
import { startTransition, useActionState, useEffect, useRef, useState, type FormEvent } from "react";
import { BedDouble, CircleAlert, Stamp } from "lucide-react";
import { postJob, type FormState } from "@/lib/actions";
import type { OwnerHiringBusiness } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import { clearDraft, keepDraft, restoreDraft } from "./draft";

/**
 * As short as a job post can be. Where the job is comes from the listing, and
 * the way to apply starts as the listing's own number. One screen, one button.
 */
export function JobForm({ lang, t, common, trades, businesses, chosen, loginHref }: {
  lang: Locale;
  t: Dictionary["business"]["jobs"];
  common: Dictionary["common"];
  trades: { key: string; label: string }[];
  businesses: OwnerHiringBusiness[];
  chosen: number;
  loginHref: string;
}) {
  const [state, action, pending] = useActionState<FormState, FormData>(postJob, { ok: false });
  const [businessId, setBusinessId] = useState(chosen);
  const business = businesses.find((b) => b.id === businessId) ?? businesses[0];
  const [contact, setContact] = useState(business.contact ?? "");
  const form = useRef<HTMLFormElement>(null);
  const errors = state.errors ?? {};
  const fieldErrors = Object.keys(errors).length > 0;

  const err = (key: string) => errors[key]?.[0];
  const invalid = (key: string) => (errors[key] ? { "aria-invalid": true as const, "aria-describedby": `${key}-error` } : {});
  const shown = (key: string, hint?: string) =>
    err(key) ? <p id={`${key}-error`} className="mt-1.5 text-sm text-danger">{err(key)}</p> : hint ? <p className="mt-1.5 text-xs text-muted">{hint}</p> : null;

  // Back from signing in again: what was typed before the session ran out.
  useEffect(() => {
    if (form.current) restoreDraft("job", form.current);
  }, []);

  useEffect(() => {
    if (!form.current) return;
    if (state.message === "signin") keepDraft("job", form.current);
    else if (fieldErrors) form.current.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
  }, [state, fieldErrors]);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    clearDraft("job");
    const data = new FormData(event.currentTarget);
    startTransition(() => action(data));
  };

  const general = state.message === "signin" ? null : fieldErrors ? t.checkFields : state.message === "error" ? common.errorText : state.message;

  return (
    <form ref={form} onSubmit={submit} className="flex flex-col gap-5" noValidate>
      <input type="hidden" name="lang" value={lang} />

      {businesses.length > 1 ? (
        <div>
          <label htmlFor="business_id" className="mb-1.5 block text-sm font-semibold">{t.business}</label>
          <select
            id="business_id"
            name="business_id"
            className="field"
            value={business.id}
            onChange={(e) => {
              const next = businesses.find((b) => b.id === Number(e.target.value));
              setBusinessId(Number(e.target.value));
              if (next?.contact) setContact(next.contact);
            }}
          >
            {businesses.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          {shown("business_id")}
        </div>
      ) : (
        <input type="hidden" name="business_id" value={business.id} />
      )}

      <div>
        <label htmlFor="title" className="mb-1.5 block text-sm font-semibold">{t.title_}</label>
        <input id="title" name="title" className="field" required maxLength={120} placeholder={t.titleExample} {...invalid("title")} />
        {shown("title")}
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label htmlFor="category" className="mb-1.5 block text-sm font-semibold">{t.kind}</label>
          <select id="category" name="category" className="field" required defaultValue="" {...invalid("category")}>
            <option value="" disabled>{t.kindChoose}</option>
            {trades.map((x) => <option key={x.key} value={x.key}>{x.label}</option>)}
          </select>
          {shown("category")}
        </div>
        <div>
          <label htmlFor="salary" className="mb-1.5 flex items-baseline gap-2 text-sm font-semibold">
            {t.salary} <span className="text-xs font-normal text-faint">{t.optional}</span>
          </label>
          <input id="salary" name="salary" className="field" inputMode="numeric" pattern="[0-9]*" placeholder="2500" {...invalid("salary")} />
          {shown("salary", t.salaryHint)}
        </div>
      </div>

      <div>
        <label htmlFor="description" className="mb-1.5 block text-sm font-semibold">{t.description}</label>
        <textarea id="description" name="description" className="field min-h-28" required maxLength={1500} rows={4} placeholder={t.descriptionExample} {...invalid("description")} />
        {shown("description", t.descriptionHint)}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <label className="choice flex items-center gap-2.5 p-3 text-sm font-semibold">
          <input type="checkbox" name="accommodation" value="1" className="sr-only" />
          <BedDouble className="size-5 shrink-0" aria-hidden="true" /> {t.accommodation}
        </label>
        <label className="choice flex items-center gap-2.5 p-3 text-sm font-semibold">
          <input type="checkbox" name="visa" value="1" className="sr-only" />
          <Stamp className="size-5 shrink-0" aria-hidden="true" /> {t.visa}
        </label>
      </div>

      <div>
        <label htmlFor="contact" className="mb-1.5 block text-sm font-semibold">{t.contact}</label>
        <input id="contact" name="contact" className="field" required maxLength={60} value={contact} onChange={(e) => setContact(e.target.value)} placeholder="050 123 4567" {...invalid("contact")} />
        {shown("contact", t.contactHint)}
      </div>

      {general && (
        <p role="alert" className="flex items-start gap-2 text-sm text-danger">
          <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {general}
        </p>
      )}
      {state.message === "signin" && (
        <p role="alert" className="text-sm">
          {t.signedOut} <a href={loginHref} className="font-semibold text-primary underline">{t.signIn}</a>
        </p>
      )}

      <button type="submit" disabled={pending} className="btn btn-primary min-h-12 w-full text-base sm:w-auto sm:self-start">
        {pending ? t.posting : t.submit}
      </button>
      <p className="-mt-2 text-xs text-muted">{t.rule}</p>
    </form>
  );
}
