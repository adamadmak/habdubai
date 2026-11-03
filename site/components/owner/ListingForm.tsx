"use client";
import Link from "next/link";
import { startTransition, useActionState, type FormEvent, type ReactNode } from "react";
import { CircleCheck } from "lucide-react";
import { saveListing, type FormState } from "@/lib/actions";
import type { OwnerBusiness, OwnerChoices } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";

type Values = Partial<OwnerBusiness>;

function label(labels: Partial<Record<Locale, string>> | undefined, lang: Locale): string {
  const text = labels?.[lang] || labels?.en || "";
  return text.replace(/^[\p{Extended_Pictographic}\u{FE0F}\u{200D}\s]+/u, "").trim();
}

/** One labelled field with its hint, or its error in place of the hint. */
function Field({ name, title, hint, optional, optionalText, error, children }: {
  name: string;
  title: string;
  hint?: string;
  optional?: boolean;
  optionalText: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label htmlFor={name} className="mb-1.5 flex items-baseline gap-2 text-sm font-semibold">
        {title}
        {optional && <span className="text-xs font-normal text-faint">{optionalText}</span>}
      </label>
      {children}
      {hint && !error && <p className="mt-1.5 text-xs text-muted">{hint}</p>}
      {error && <p id={`${name}-error`} className="mt-1.5 text-sm text-danger">{error}</p>}
    </div>
  );
}

export function ListingForm({ lang, b, common, choices, values, live }: {
  lang: Locale;
  b: Dictionary["business"];
  common: Dictionary["common"];
  choices: OwnerChoices;
  values: Values | null;
  live: boolean;
}) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveListing, { ok: false });
  const errors = state.errors ?? {};
  const v = values ?? {};
  const f = b.fields;

  const err = (key: string) => errors[key]?.[0];
  const invalid = (key: string) => (errors[key] ? { "aria-invalid": true as const, "aria-describedby": `${key}-error` } : {});
  const common_ = { optionalText: f.optional };

  // Submitted by hand rather than through the form's action, so a refused save keeps what was typed.
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    startTransition(() => action(data));
  };

  return (
    <form onSubmit={submit} className="flex flex-col gap-8" noValidate>
      <input type="hidden" name="lang" value={lang} />
      {v.id && <input type="hidden" name="id" value={v.id} />}

      {state.message === "signin" && (
        <p className="rounded-xl bg-warning-soft p-3 text-sm">
          <Link href={`/${lang}/business/login`} className="font-semibold text-primary">{b.cta}</Link>
        </p>
      )}
      {state.message && state.message !== "signin" && !state.ok && Object.keys(errors).length === 0 && (
        <p className="rounded-xl bg-danger-soft p-3 text-sm">{state.message === "error" ? common.errorText : state.message}</p>
      )}

      <fieldset className="flex flex-col gap-5">
        <legend className="title mb-4 text-lg">{b.sections.basics}</legend>
        <Field name="name" title={f.name} error={err("name")} {...common_}>
          <input id="name" name="name" className="field" defaultValue={v.name ?? ""} required maxLength={120} autoComplete="organization" {...invalid("name")} />
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field name="category" title={f.category} error={err("category")} {...common_}>
            <select id="category" name="category" className="field" defaultValue={v.category ?? "restaurant"} {...invalid("category")}>
              {Object.entries(choices.categories).map(([key, labels]) => <option key={key} value={key}>{label(labels, lang)}</option>)}
            </select>
          </Field>
          <Field name="city_id" title={f.city} error={err("city_id")} {...common_}>
            <select id="city_id" name="city_id" className="field" defaultValue={v.city_id ?? choices.cities[0]?.id} {...invalid("city_id")}>
              {choices.cities.map((c) => <option key={c.id} value={c.id}>{c.name[lang] || c.name.en}</option>)}
            </select>
          </Field>
        </div>
        <Field name="description" title={f.description} hint={f.descriptionHint} optional error={err("description")} {...common_}>
          <input id="description" name="description" className="field" defaultValue={v.description ?? ""} maxLength={300} {...invalid("description")} />
        </Field>
      </fieldset>

      <fieldset className="flex flex-col gap-5">
        <legend className="title mb-4 text-lg">{b.sections.location}</legend>
        <Field name="area" title={f.area} hint={f.areaHint} error={err("area")} {...common_}>
          <input id="area" name="area" className="field" defaultValue={v.area ?? ""} required maxLength={160} autoComplete="street-address" {...invalid("area")} />
        </Field>
        <Field name="maps_url" title={f.maps} hint={f.mapsHint} optional error={err("maps_url")} {...common_}>
          <input id="maps_url" name="maps_url" type="url" inputMode="url" className="field" defaultValue={v.maps_url ?? ""} placeholder="https://maps.app.goo.gl/…" {...invalid("maps_url")} />
        </Field>
      </fieldset>

      <fieldset className="flex flex-col gap-5">
        <legend className="title mb-4 text-lg">{b.sections.contact}</legend>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field name="phone" title={f.phone} optional error={err("phone")} {...common_}>
            <input id="phone" name="phone" type="tel" inputMode="tel" className="field" defaultValue={v.phone ?? ""} placeholder="04 123 4567" autoComplete="tel" {...invalid("phone")} />
          </Field>
          <Field name="whatsapp" title={f.whatsapp} optional error={err("whatsapp")} {...common_}>
            <input id="whatsapp" name="whatsapp" type="tel" inputMode="tel" className="field" defaultValue={v.whatsapp ?? ""} placeholder="050 123 4567" {...invalid("whatsapp")} />
          </Field>
          <Field name="telegram" title={f.telegram} optional error={err("telegram")} {...common_}>
            <input id="telegram" name="telegram" className="field" defaultValue={v.telegram ? `@${v.telegram}` : ""} placeholder="@username" autoCapitalize="none" autoCorrect="off" {...invalid("telegram")} />
          </Field>
          <Field name="website" title={f.website} optional error={err("website")} {...common_}>
            <input id="website" name="website" type="url" inputMode="url" className="field" defaultValue={v.website ?? ""} placeholder="https://" autoCapitalize="none" {...invalid("website")} />
          </Field>
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-5">
        <legend className="title mb-4 text-lg">{b.sections.details}</legend>
        <Field name="about" title={f.about} hint={f.aboutHint} optional error={err("about")} {...common_}>
          <textarea id="about" name="about" rows={5} className="field" defaultValue={v.about ?? ""} maxLength={1500} {...invalid("about")} />
        </Field>
        <Field name="hours" title={f.hours} hint={f.hoursHint} optional error={err("hours")} {...common_}>
          <input id="hours" name="hours" className="field" defaultValue={v.hours ?? ""} maxLength={160} {...invalid("hours")} />
        </Field>
        <div>
          <p className="mb-2 text-sm font-semibold">{f.languages}</p>
          <div className="flex flex-wrap gap-2">
            {Object.entries(choices.languages).map(([code, labels]) => (
              <label key={code} className="chip cursor-pointer has-[:checked]:border-fg has-[:checked]:bg-fg has-[:checked]:text-bg has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary">
                <input type="checkbox" name="languages" value={code} defaultChecked={(v.languages ?? []).includes(code)} className="sr-only" />
                {labels[lang] || labels.en}
              </label>
            ))}
          </div>
          {err("languages") && <p className="mt-1.5 text-sm text-danger">{err("languages")}</p>}
        </div>
      </fieldset>

      <div className="sticky bottom-[calc(var(--tabbar-h)+env(safe-area-inset-bottom)+0.75rem)] z-30 md:bottom-4">
        <div className="material rounded-2xl border border-line p-3">
          <div className="flex flex-wrap items-center gap-3">
            <button type="submit" className="btn btn-primary" disabled={pending}>{pending ? b.saving : v.id ? b.save : b.submitNew}</button>
            <Link href={`/${lang}/business`} className="btn btn-quiet">{b.cancel}</Link>
            {state.ok && !pending && (
              <span className="flex items-center gap-1.5 text-sm text-success" role="status">
                <CircleCheck className="size-4" aria-hidden="true" /> {live ? b.savedChanges : b.savedNew}
              </span>
            )}
          </div>
          <p className="mt-2 hidden text-xs text-muted sm:block">{live ? b.liveNote : b.pendingNote}</p>
        </div>
      </div>
    </form>
  );
}
