"use client";
import Link from "@/components/AppLink";
import { startTransition, useActionState, useEffect, useRef, useState, type CSSProperties, type FormEvent, type ReactNode } from "react";
import { ArrowRight, Ban, CircleAlert, CircleCheck, HandCoins, Info, PlaneTakeoff } from "lucide-react";
import type { Dictionary } from "@/i18n/en";
import { postGoods, type GoodsFormState } from "@/lib/actions";
import type { GoodsCard as Post, GoodsChoices, GoodsCurrency, GoodsDirection, GoodsFit, GoodsMemberBadge, MyGoods } from "@/lib/api";
import { fill } from "@/lib/fill";
import { ethiopianDate, number, weekday } from "@/lib/format";
import { label } from "@/lib/labels";
import type { Locale } from "@/lib/locales";
import { GEEZ, Notice } from "../Bits";
import { clearDraft, keepDraft, takeDraft } from "../owner/draft";
import { GoodsBadge, GoodsCard, MiniGoodsCard, type GoodsTone } from "./GoodsCard";

export type Purpose = GoodsTone;
type Pricing = "free" | "set" | "agree";

const PURPOSES: Purpose[] = ["traveller", "send", "request"];
const DRAFT = "goods:new";

/** The step each of the API's field names belongs to, for its error. */
const FIELD_STEP: Record<string, string> = { kind: "purpose", mode: "purpose", currency: "price" };

function firstKey(list: Record<string, unknown>): string {
  return Object.keys(list)[0] ?? "";
}

/** One numbered step: a Ge'ez numeral on the thread, the question, and its answer below. */
function Step({ n, title, hint, error, id, children }: { n: number; title: string; hint?: string; error?: string; id: string; children: ReactNode }) {
  return (
    <fieldset className="relative" aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}>
      <legend className="mb-3 flex w-full items-center gap-3">
        <span className="geez-mark relative z-10" aria-hidden="true">{GEEZ[n - 1]}</span>
        <span className="title text-[1.0625rem]">{title}</span>
      </legend>
      <div className="pl-12">
        {children}
        {hint && !error && <p id={`${id}-hint`} className="mt-2 text-xs text-muted">{hint}</p>}
        {error && (
          <p id={`${id}-error`} className="mt-2 flex items-start gap-1.5 text-sm text-danger">
            <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {error}
          </p>
        )}
      </div>
    </fieldset>
  );
}

/**
 * The goods form: "register as a traveller or as a sender". Eight steps with
 * Ge'ez numerals down a gold thread, and beside them (below on phones) the
 * card exactly as others will see it, redrawn as the member types. Once a date
 * is picked, the board is asked who a post like this would pair with, so the
 * matching starts before anything is posted.
 *
 * Errors follow the owner forms: said beside the button, the first step to
 * fix takes the focus, and a session that ran out keeps the draft in this tab
 * for after signing in again. On success the card lands on the board and the
 * posts that fit it are offered, each with its "I'm interested".
 */
export function GoodsForm({ lang, g, choices, member, limits, initial, today, lastDay, here, rulesHref, labels }: {
  lang: Locale;
  g: Dictionary["goods"];
  choices: GoodsChoices;
  member: GoodsMemberBadge;
  limits: MyGoods["limits"];
  /** Where the form starts: a choice on the board, or a post the member was interested in. */
  initial: { purpose: Purpose; direction: GoodsDirection; directionGiven: boolean; date: string; interest: number | null };
  today: string;
  lastDay: string;
  here: string;
  rulesHref: string;
  labels: { sessionExpired: string; draftKept: string; signInAgain: string; errorText: string };
}) {
  const [state, action, pending] = useActionState<GoodsFormState, FormData>(postGoods, { ok: false });
  const form = useRef<HTMLFormElement>(null);
  const f = g.form;

  const full = { traveller: limits.open_traveller >= limits.max_open, sender: limits.open_sender >= limits.max_open };
  const [purpose, setPurpose] = useState<Purpose>(initial.purpose);
  const [direction, setDirection] = useState<GoodsDirection>(initial.direction);
  const [date, setDate] = useState(initial.date);
  const [from, setFrom] = useState(firstKey(choices.cities[initial.direction].from));
  const [to, setTo] = useState(firstKey(choices.cities[initial.direction].to));
  const [kilos, setKilos] = useState(initial.purpose === "traveller" ? 10 : 5);
  const [categories, setCategories] = useState<string[]>([]);
  const [description, setDescription] = useState("");
  const [pricing, setPricing] = useState<Pricing>("agree");
  const [price, setPrice] = useState("");
  const [currency, setCurrency] = useState<GoodsCurrency>("AED");
  const [rules, setRules] = useState(false);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [fit, setFit] = useState<GoodsFit | null>(null);
  const directionPicked = useRef(initial.directionGiven);

  const kind = purpose === "traveller" ? "traveller" : "sender";
  const trip = kind === "traveller";
  const cities = choices.cities[direction];
  const quickKilos = trip ? choices.kilos_traveller : choices.kilos_sender;

  // The API's errors under the step they belong to ("categories.0" is the categories step), then this form's own.
  const serverErrors: Record<string, string> = {};
  for (const [key, messages] of Object.entries(state.errors ?? {})) {
    const field = key.split(".")[0];
    const step = FIELD_STEP[field] ?? field;
    if (!serverErrors[step] && messages[0]) serverErrors[step] = messages[0];
  }
  const err = (step: string) => local[step] ?? serverErrors[step];
  const invalid = (step: string) => (err(step) ? { "aria-invalid": true as const } : {});

  // Back from signing in again: what was typed before the session ran out.
  useEffect(() => {
    const draft = takeDraft(DRAFT);
    if (!draft) return;
    const one = (key: string) => draft[key]?.[0] ?? "";
    const p = one("purpose") as Purpose;
    if (PURPOSES.includes(p)) setPurpose(p);
    const dir = one("direction") as GoodsDirection;
    const list = choices.cities[dir] ? dir : direction;
    if (choices.cities[dir]) {
      setDirection(dir);
      directionPicked.current = true;
    }
    if (one("from") in choices.cities[list].from) setFrom(one("from"));
    if (one("to") in choices.cities[list].to) setTo(one("to"));
    if (/^\d{4}-\d{2}-\d{2}$/.test(one("date"))) setDate(one("date"));
    if (Number(one("kilos")) > 0) setKilos(Math.min(choices.max_kilos, Math.round(Number(one("kilos")))));
    setCategories((draft.categories ?? []).filter((c) => c in choices.categories));
    setDescription(one("description"));
    if (["free", "set", "agree"].includes(one("pricing"))) setPricing(one("pricing") as Pricing);
    setPrice(one("price"));
    if (one("currency") === "ETB") setCurrency("ETB");
    setRules(one("rules") === "1");
    // Only once, on arrival.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // After each answer: forget the draft on success, keep it for a sign-in, or take the member to the first step to fix.
  useEffect(() => {
    if (state.ok) clearDraft(DRAFT);
    else if (state.message === "signin" && form.current) keepDraft(DRAFT, form.current);
    else if (Object.keys(state.errors ?? {}).length > 0) form.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
  }, [state]);

  // Who this post would pair with, asked a moment after the date, the direction or the kilos stop changing.
  useEffect(() => {
    if (!date) {
      setFit(null);
      return;
    }
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        const response = await fetch(`/api/goods/fit?${new URLSearchParams({ kind, direction, date, kilos: String(kilos) })}`, { signal: controller.signal });
        setFit(response.ok ? ((await response.json()) as GoodsFit) : null);
      } catch {
        // Cancelled by a newer question, or offline: the list just stays as it was.
      }
    }, 450);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [kind, direction, date, kilos]);

  const changeDirection = (next: GoodsDirection) => {
    if (next === direction) return;
    // The way back: the city it came from becomes the one it goes to, where the lists allow.
    const list = choices.cities[next];
    setFrom(to in list.from ? to : firstKey(list.from));
    setTo(from in list.to ? from : firstKey(list.to));
    setDirection(next);
  };

  // Until the member picks a direction, it follows the purpose: something brought usually comes from home to the Emirates.
  const choosePurpose = (next: Purpose) => {
    setPurpose(next);
    if (!directionPicked.current) changeDirection(next === "request" ? "to_ae" : "to_et");
  };

  const toggleCategory = (key: string) => setCategories((list) => (list.includes(key) ? list.filter((c) => c !== key) : [...list, key]));

  // Submitted by hand rather than through the form's action, so a refused post keeps what was typed.
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (pricing === "set" && !(Number(price) > 0)) problems.price = f.priceNeeded;
    if (!rules) problems.rules = f.rulesNeeded;
    setLocal(problems);
    if (Object.keys(problems).length > 0) {
      setTimeout(() => form.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(), 0);
      return;
    }
    const data = new FormData(event.currentTarget);
    startTransition(() => action(data));
  };

  if (state.ok && state.post) {
    return <Posted state={state} lang={lang} g={g} maxKilos={choices.max_kilos} />;
  }

  // The card as it will appear, from what has been typed so far.
  const preview: Post = {
    id: 0,
    kind,
    mode: trip ? null : purpose === "request" ? "request" : "send",
    direction,
    date,
    from: { key: from, name: cities.from[from] ?? {} },
    to: { key: to, name: cities.to[to] ?? {} },
    kilos,
    kilos_left: trip ? kilos : null,
    categories: categories.map((key) => ({ key, name: choices.categories[key] ?? {} })),
    description: description.trim() || null,
    price: pricing === "free" ? 0 : pricing === "set" && Number(price) > 0 ? Math.round(Number(price)) : null,
    currency: pricing === "set" ? currency : null,
    price_basis: trip ? "per_kg" : "total",
    member,
    created_at: "",
  };

  const fieldErrors = Object.keys(serverErrors).length > 0;
  const general = state.ok || state.message === "signin" ? null : fieldErrors ? f.checkFields : state.message === "error" ? labels.errorText : state.message;
  const fitText = !fit ? null : fit.count === 0 ? (trip ? f.fitTravellerNone : f.fitSenderNone) : fit.count === 1 ? (trip ? f.fitTravellerOne : f.fitSenderOne) : fill(trip ? f.fitTraveller : f.fitSender, { n: number(fit.count, lang) });
  const request = purpose === "request";

  return (
    <form ref={form} onSubmit={submit} noValidate className={`goods-${purpose} grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-12`}>
      <input type="hidden" name="lang" value={lang} />
      {initial.interest && <input type="hidden" name="interest" value={initial.interest} />}

      {/* ---- The steps, on a gold thread. ---- */}
      <div className="relative flex flex-col gap-9 before:absolute before:bottom-6 before:left-[1.0625rem] before:top-6 before:border-l-2 before:border-dashed before:border-accent/45 lg:col-start-1">
        <Step n={1} id="purpose" title={f.steps.purpose} error={err("purpose")}>
          <div className="grid grid-cols-3 gap-2 sm:gap-3">
            {PURPOSES.map((p) => {
              const closed = full[p === "traveller" ? "traveller" : "sender"];
              return (
                <label key={p} className={`choice goods-${p} flex-col items-start gap-2 p-3 sm:p-4`}>
                  <input
                    type="radio"
                    name="purpose"
                    value={p}
                    checked={purpose === p}
                    disabled={closed}
                    onChange={() => choosePurpose(p)}
                    className="sr-only"
                    {...invalid("purpose")}
                  />
                  <GoodsBadge post={{ kind: p === "traveller" ? "traveller" : "sender", mode: p === "traveller" ? null : p }} className="size-10 rounded-xl sm:size-11 sm:rounded-2xl" />
                  <span className="text-sm font-bold leading-snug sm:text-[0.9375rem]">{f.purpose[p].title}</span>
                  <span className="text-xs text-muted">{closed ? fill(f.full, { max: limits.max_open }) : f.purpose[p].text}</span>
                </label>
              );
            })}
          </div>
        </Step>

        <Step n={2} id="direction" title={f.steps.direction} error={err("direction")}>
          <div className="grid gap-2 sm:grid-cols-2">
            {(["to_et", "to_ae"] as const).map((dir) => (
              <label key={dir} className="choice items-center gap-3 px-4 py-3">
                <input
                  type="radio"
                  name="direction"
                  value={dir}
                  checked={direction === dir}
                  onChange={() => {
                    directionPicked.current = true;
                    changeDirection(dir);
                  }}
                  className="sr-only"
                  {...invalid("direction")}
                />
                <PlaneTakeoff className={`size-5 shrink-0 text-[var(--kind-ink)] ${dir === "to_et" ? "-scale-x-100" : ""}`} aria-hidden="true" />
                <span className="font-semibold">{g.filters[dir]}</span>
              </label>
            ))}
          </div>
        </Step>

        <Step n={3} id="date" title={trip ? f.steps.dateTraveller : f.steps.dateSender} hint={trip ? f.dateHintTraveller : f.dateHintSender} error={err("date")}>
          <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            <input
              id="date"
              type="date"
              name="date"
              required
              min={today}
              max={lastDay}
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="field w-auto min-w-[11rem]"
              aria-label={trip ? f.steps.dateTraveller : f.steps.dateSender}
              {...invalid("date")}
            />
            {date && <span className="text-sm text-muted">{weekday(date, lang)} · {ethiopianDate(date, lang)}</span>}
          </div>
        </Step>

        <Step n={4} id="route" title={f.steps.route} error={err("from") ?? err("to")}>
          <div className="grid items-end gap-3 sm:grid-cols-[1fr_auto_1fr]">
            <label className="block">
              <span className="mb-1.5 block text-sm font-semibold">{request ? f.fromRequest : f.from}</span>
              <select name="from" value={from} onChange={(e) => setFrom(e.target.value)} className="field" {...invalid("from")}>
                {Object.entries(cities.from).map(([key, name]) => <option key={key} value={key}>{label(name, lang)}</option>)}
              </select>
            </label>
            <ArrowRight className="mb-3.5 hidden size-5 text-faint sm:block" aria-hidden="true" />
            <label className="block">
              <span className="mb-1.5 block text-sm font-semibold">{request ? f.toRequest : f.to}</span>
              <select name="to" value={to} onChange={(e) => setTo(e.target.value)} className="field" {...invalid("to")}>
                {Object.entries(cities.to).map(([key, name]) => <option key={key} value={key}>{label(name, lang)}</option>)}
              </select>
            </label>
          </div>
        </Step>

        <Step n={5} id="kilos" title={trip ? f.steps.kilosTraveller : f.steps.kilosSender} hint={fill(f.kilosHint, { max: choices.max_kilos })} error={err("kilos")}>
          <output htmlFor="kilos" className="display block text-[2.5rem] leading-none tabular" aria-live="off">
            {fill(f.kilos, { n: number(kilos, lang) })}
          </output>
          <input
            id="kilos"
            type="range"
            name="kilos"
            min={1}
            max={choices.max_kilos}
            step={1}
            value={kilos}
            onChange={(e) => setKilos(Number(e.target.value))}
            className="kilo-range mt-1"
            style={{ "--fill": `${((kilos - 1) / Math.max(1, choices.max_kilos - 1)) * 100}%` } as CSSProperties}
            aria-label={trip ? f.steps.kilosTraveller : f.steps.kilosSender}
            aria-valuetext={fill(f.kilos, { n: kilos })}
            {...invalid("kilos")}
          />
          <div className="mt-1 flex flex-wrap gap-2">
            {quickKilos.map((n) => (
              <button key={n} type="button" className="chip hit" aria-pressed={kilos === n} onClick={() => setKilos(n)}>
                {fill(f.kilos, { n: number(n, lang) })}
              </button>
            ))}
          </div>
        </Step>

        <Step n={6} id="categories" title={trip ? f.steps.categoriesTraveller : f.steps.categoriesSender} hint={trip ? f.categoriesHintTraveller : f.categoriesHintSender} error={err("categories")}>
          <div className="flex flex-wrap gap-2">
            {Object.entries(choices.categories).map(([key, name]) => (
              <label
                key={key}
                className="chip cursor-pointer has-[:checked]:border-[var(--cover-b)] has-[:checked]:bg-[var(--cover-b)] has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary"
              >
                <input type="checkbox" name="categories" value={key} checked={categories.includes(key)} onChange={() => toggleCategory(key)} className="sr-only" {...invalid("categories")} />
                {label(name, lang)}
              </label>
            ))}
          </div>
        </Step>

        {/* The fee starts as "to agree" and a traveller's note is optional, so a traveller can skip both. A parcel needs its note. */}
        <details className="more-options" open={!trip || Boolean(err("price") || err("description"))}>
        <summary className="pl-12">{g.moreOptions}</summary>
        <div className="mt-4 flex flex-col gap-8">
        <Step n={7} id="price" title={f.steps.price} error={err("price")}>
          <div className="grid grid-cols-3 gap-2">
            {(["free", "set", "agree"] as const).map((p) => (
              <label key={p} className="choice flex-col items-start gap-0.5 px-3 py-2.5 sm:px-4">
                <input type="radio" name="pricing" value={p} checked={pricing === p} onChange={() => setPricing(p)} className="sr-only" {...(p === "set" ? invalid("price") : {})} />
                <span className="text-sm font-bold">{f.price[p]}</span>
                <span className="text-xs text-muted">{p === "free" ? f.price.freeText : p === "agree" ? f.price.agreeText : trip ? f.price.setTextTraveller : f.price.setTextSender}</span>
              </label>
            ))}
          </div>
          {pricing === "set" && (
            <div className="mt-3 flex flex-wrap items-end gap-3">
              <label className="block">
                <span className="mb-1.5 block text-sm font-semibold">{f.amount}</span>
                <span className="flex items-center gap-2">
                  <input
                    type="number"
                    name="price"
                    inputMode="numeric"
                    min={1}
                    max={100000}
                    step={1}
                    value={price}
                    onChange={(e) => setPrice(e.target.value)}
                    className="field w-36 tabular"
                    {...invalid("price")}
                  />
                  <span className="text-sm text-muted">{trip ? f.price.setTextTraveller : f.price.setTextSender}</span>
                </span>
              </label>
              <div role="radiogroup" aria-label={f.currency} className="toggle-group w-36">
                <span aria-hidden="true" className="toggle-thumb" style={{ width: "calc((100% - 0.5rem) / 2)", translate: `${currency === "ETB" ? 100 : 0}% 0` }} />
                {(["AED", "ETB"] as const).map((c) => (
                  <label key={c} className={`relative z-[1] grid cursor-pointer place-items-center rounded-full py-2 text-sm font-bold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary ${currency === c ? "text-[color:var(--chip-on-fg)]" : "text-muted"}`}>
                    <input type="radio" name="currency" value={c} checked={currency === c} onChange={() => setCurrency(c)} className="sr-only" />
                    {c}
                  </label>
                ))}
              </div>
            </div>
          )}
          <p className="mt-3 flex items-start gap-2 text-xs text-muted">
            <HandCoins className="mt-px size-4 shrink-0 text-accent-ink" aria-hidden="true" /> {g.money}
          </p>
        </Step>

        <Step n={8} id="description" title={trip ? f.steps.noteTraveller : f.steps.noteSender} error={err("description")}>
          <textarea
            id="description"
            name="description"
            rows={3}
            maxLength={300}
            required={!trip}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder={trip ? f.notePlaceholderTraveller : request ? f.notePlaceholderRequest : f.notePlaceholderSender}
            className="field"
            aria-label={trip ? f.steps.noteTraveller : f.steps.noteSender}
            {...invalid("description")}
          />
          <p className="mt-1.5 flex justify-between gap-3 text-xs text-muted">
            <span>{trip ? f.noteHintTraveller : f.noteHintSender}</span>
            <span className="tabular">{fill(f.chars, { n: description.length })}</span>
          </p>
          <div className="mt-3 flex gap-3 rounded-2xl bg-danger-soft p-3.5">
            <Ban className="mt-0.5 size-4.5 shrink-0 text-danger" aria-hidden="true" />
            <p className="text-sm">
              <span className="font-semibold">{g.bannedTitle}:</span> {g.banned}
            </p>
          </div>
        </Step>
        </div>
        </details>

        <div className="pl-12">
          <label className={`flex cursor-pointer items-start gap-3 rounded-2xl border p-4 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary ${err("rules") ? "border-danger" : "border-line"} bg-elevated`}>
            <input
              type="checkbox"
              name="rules"
              value="1"
              checked={rules}
              onChange={(e) => setRules(e.target.checked)}
              className="mt-0.5 size-5 shrink-0 accent-[var(--cover-b)]"
              aria-describedby={err("rules") ? "rules-error" : undefined}
              {...invalid("rules")}
            />
            <span className="text-sm">
              {f.rules}{" "}
              <Link href={rulesHref} target="_blank" className="font-semibold text-primary underline underline-offset-2">{f.rulesLink}</Link>
            </span>
          </label>
          {err("rules") && (
            <p id="rules-error" className="mt-2 flex items-start gap-1.5 text-sm text-danger">
              <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {err("rules")}
            </p>
          )}
        </div>
      </div>

      {/* ---- The card as others will see it, and who it would pair with. ---- */}
      <aside className="lg:col-start-2 lg:row-span-2 lg:row-start-1">
        <div className="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--header-space)+1.25rem)]">
          <div className="rounded-[var(--radius-tile)] bg-surface p-4 [--perf-bg:var(--color-surface)]">
            <p className="eyebrow mb-3 flex items-center gap-2">
              <span className="tibeb-thin inline-block w-6 rounded-full" aria-hidden="true" /> {f.preview}
            </p>
            <GoodsCard post={preview} lang={lang} t={g.card} maxKilos={choices.max_kilos} preview />
            <p className="mt-3 text-xs text-muted">{f.previewNote}</p>
          </div>

          <section aria-live="polite" className="rounded-[var(--radius-tile)] border border-line p-4">
            {!date ? (
              <p className="text-sm text-muted">{f.fitWaiting}</p>
            ) : fitText ? (
              <>
                <p className="title">{fitText}</p>
                {fit && fit.posts.length > 0 && (
                  <div className="mt-3 flex flex-col gap-2">
                    {fit.posts.map((post) => <MiniGoodsCard key={post.id} post={post} lang={lang} t={g.card} href={`/${lang}/goods/${post.id}`} />)}
                    <p className="mt-1 text-xs text-muted">{f.fitNote}</p>
                  </div>
                )}
              </>
            ) : (
              // Asking: a quiet placeholder where the answer will be.
              <span className="waiting block h-5 w-3/4 rounded-full bg-surface" aria-hidden="true" />
            )}
          </section>
        </div>
      </aside>

      {/* ---- Post it. ---- */}
      <div className="sticky bottom-[calc(var(--tabbar-space)+0.75rem)] z-30 lg:col-start-1 md:bottom-4">
        {initial.interest && (
          <p className="mb-2 flex items-center gap-2 rounded-2xl bg-primary-soft px-4 py-2.5 text-sm font-medium">
            <Info className="size-4 shrink-0 text-primary" aria-hidden="true" /> {f.interestNote}
          </p>
        )}
        <div className="material rounded-2xl border border-line p-3">
          <div className="flex flex-wrap items-center gap-3">
            <button type="submit" className="btn btn-kind" disabled={pending}>{pending ? f.posting : f.submit}</button>
            {/* Always in the page, so a screen reader hears each new answer. */}
            <div className="min-w-[12rem] flex-1 text-sm" aria-live="polite">
              {!pending && state.message === "signin" && (
                <p className="text-danger">
                  {labels.sessionExpired} {labels.draftKept}{" "}
                  <Link href={`/${lang}/business/login?next=${encodeURIComponent(here)}`} className="font-semibold text-primary underline underline-offset-2">
                    {labels.signInAgain}
                  </Link>
                </p>
              )}
              {!pending && general && (
                <p className="flex items-start gap-1.5 text-danger">
                  <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {general}
                </p>
              )}
              {!pending && !general && state.message !== "signin" && Object.keys(local).length > 0 && (
                <p className="flex items-start gap-1.5 text-danger">
                  <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {f.checkFields}
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </form>
  );
}

/**
 * Posted: the card lands, a gold thread runs out under it, and the posts that
 * fit it are offered with their own "I'm interested". The heading takes the
 * focus so a screen reader hears that it worked.
 */
function Posted({ state, lang, g, maxKilos }: { state: GoodsFormState; lang: Locale; g: Dictionary["goods"]; maxKilos: number }) {
  const heading = useRef<HTMLHeadingElement>(null);
  const f = g.form;
  const matches = state.matches ?? [];

  useEffect(() => {
    const still = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    heading.current?.focus({ preventScroll: true });
    heading.current?.scrollIntoView({ behavior: still ? "auto" : "smooth", block: "start" });
  }, []);

  if (!state.post) return null;

  return (
    <div className="mx-auto max-w-5xl">
      <div className="text-center">
        <span className="mx-auto grid size-14 place-items-center rounded-full bg-success-soft text-success">
          <CircleCheck className="size-7" aria-hidden="true" />
        </span>
        <h2 ref={heading} tabIndex={-1} className="display mt-4 text-[2rem] outline-none md:text-[2.5rem]">{f.posted}</h2>
        <p className="mx-auto mt-2 max-w-lg text-muted">{f.postedLead}</p>
      </div>

      <div className="mx-auto mt-8 max-w-sm">
        <div className="land">
          <GoodsCard post={state.post} lang={lang} t={g.card} maxKilos={maxKilos} preview />
        </div>
        <div className="thread tibeb mt-5 rounded-full" aria-hidden="true" />
      </div>

      {state.request && (
        <div className="mx-auto mt-6 max-w-xl">
          <Notice tone={state.request.status === "sent" || state.request.status === "accepted" ? "success" : "info"}>{state.request.message}</Notice>
        </div>
      )}

      <section className="mt-12">
        <h3 className="headline text-[1.5rem] md:text-[1.875rem]">{f.matchesTitle}</h3>
        {matches.length > 0 ? (
          <>
            <p className="mt-1.5 max-w-2xl text-sm text-muted">{f.matchesLead}</p>
            <ul className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {matches.map((post) => (
                <li key={post.id}>
                  <GoodsCard post={post} lang={lang} t={g.card} maxKilos={maxKilos} signedIn />
                </li>
              ))}
            </ul>
          </>
        ) : (
          <p className="mt-2 text-muted">{f.noMatches}</p>
        )}
      </section>

      <div className="mt-10 flex flex-wrap gap-2">
        <Link href={`/${lang}/goods/mine`} className="btn btn-primary">{g.mine}</Link>
        <Link href={`/${lang}/goods`} className="btn btn-line">{g.seeBoard}</Link>
        {/* A full page load, so the form starts clean. */}
        <a href={`/${lang}/goods/new`} className="btn btn-quiet">{f.another}</a>
      </div>
    </div>
  );
}
