"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { ApiError, query, type GoodsCreated, type GoodsInterestResult, type OwnerBusiness } from "./api";
import { isLocale, type Locale } from "./locales";
import { ownerApi, ownerToken } from "./owner";

export type FormState = { ok: boolean; message?: string; errors?: Record<string, string[]>; savedAt?: number };

const FIELDS = ["name", "category", "area", "maps_url", "phone", "whatsapp", "telegram", "website", "description", "about", "hours"] as const;

function body(form: FormData) {
  const data: Record<string, unknown> = {};
  for (const field of FIELDS) {
    const value = String(form.get(field) ?? "").trim();
    data[field] = value === "" ? null : value;
  }
  data.city_id = Number(form.get("city_id")) || null;
  data.languages = form.getAll("languages").map(String);
  return data;
}

/** The form's language: the page sends it, and the API words its answers in it. */
function localeOf(form: FormData): Locale {
  const lang = form.get("lang");
  return isLocale(lang) ? lang : "en";
}

function failed(error: unknown): FormState {
  if (error instanceof ApiError) {
    if (error.status === 401) return { ok: false, message: "signin" };
    return { ok: false, message: error.message, errors: error.errors };
  }
  return { ok: false, message: "error" };
}

/** Create a listing, or save changes to one. A new listing then opens its edit page so a photo can be added. */
export async function saveListing(_previous: FormState, form: FormData): Promise<FormState> {
  const lang = localeOf(form);
  const id = Number(form.get("id")) || null;
  let created: OwnerBusiness | null = null;

  try {
    if (id) {
      await ownerApi(`/businesses/${id}`, { method: "PUT", body: body(form), locale: lang });
    } else {
      created = (await ownerApi<{ business: OwnerBusiness }>("/businesses", { method: "POST", body: body(form), locale: lang })).business;
    }
  } catch (error) {
    return failed(error);
  }

  revalidatePath(`/${lang}/business`);

  if (created) {
    redirect(`/${lang}/business/${created.id}/edit?new=1`);
  }

  return { ok: true, savedAt: Date.now() };
}

export async function removeListing(form: FormData): Promise<void> {
  const lang = localeOf(form);
  const id = Number(form.get("id"));

  if (id) {
    try {
      await ownerApi(`/businesses/${id}`, { method: "DELETE", locale: lang });
    } catch {
      // The dashboard shows the listing's real state either way.
    }
  }

  revalidatePath(`/${lang}/business`);
  redirect(`/${lang}/business`);
}

export async function claimListing(_previous: FormState, form: FormData): Promise<FormState> {
  const id = Number(form.get("business_id"));
  const note = String(form.get("note") ?? "").trim();

  try {
    await ownerApi(`/businesses/${id}/claim`, { method: "POST", body: { note: note || null }, locale: localeOf(form) });
  } catch (error) {
    return failed(error);
  }

  return { ok: true, savedAt: Date.now() };
}

// ---- the goods board -------------------------------------------------------------

export type GoodsFormState = FormState & Partial<GoodsCreated> & { code?: string | null };

/**
 * A new trip or parcel. The form's first step is one of three purposes; a
 * parcel to send and a thing to be brought are both kind "sender", told
 * apart by mode. The fee is free (0), an amount, or left out ("to agree").
 */
export async function postGoods(_previous: GoodsFormState, form: FormData): Promise<GoodsFormState> {
  const lang = localeOf(form);
  const text = (name: string) => String(form.get(name) ?? "").trim();
  const purpose = text("purpose");
  const kind = purpose === "traveller" ? "traveller" : "sender";

  const body: Record<string, unknown> = {
    kind,
    direction: text("direction"),
    date: text("date"),
    from: text("from"),
    to: text("to"),
    kilos: Number(text("kilos")) || null,
    categories: form.getAll("categories").map(String),
  };
  if (kind === "sender") body.mode = purpose === "request" ? "request" : "send";
  if (text("description")) body.description = text("description");

  const pricing = text("pricing");
  if (pricing === "free") {
    body.price = 0;
  } else if (pricing === "set") {
    body.price = text("price") === "" ? null : Number(text("price"));
    body.currency = text("currency");
  }

  const interest = Number(text("interest"));
  if (Number.isInteger(interest) && interest > 0) body.interest = interest;

  let created: GoodsCreated;
  try {
    created = await ownerApi<GoodsCreated>("/goods", { method: "POST", body, locale: lang });
  } catch (error) {
    return { ...failed(error), code: error instanceof ApiError ? error.code : null };
  }

  revalidatePath(`/${lang}/goods`, "layout");
  return { ok: true, savedAt: Date.now(), ...created };
}

export type InterestState = { status?: string; message?: string };

/**
 * "I'm interested" on someone's post. Not signed in: to the Telegram sign-in,
 * then back to the post. No post of the other kind yet: to the form, filled
 * in from what the API says is needed, carrying this post's id so the request
 * goes out the moment theirs is posted.
 */
export async function goodsInterest(_previous: InterestState, form: FormData): Promise<InterestState> {
  const lang = localeOf(form);
  const id = Number(form.get("id"));
  if (!Number.isInteger(id) || id <= 0) return { status: "error" };

  const login = `/${lang}/business/login?next=/${lang}/goods/${id}`;
  if (!(await ownerToken())) redirect(login);

  let result: GoodsInterestResult;
  try {
    result = await ownerApi<GoodsInterestResult>(`/goods/${id}/interest`, { method: "POST", locale: lang });
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) redirect(login);
    // A refusal the API words itself (not a member, blocked); anything else is ours to say.
    if (error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 429) return { status: "error", message: error.message };
    return { status: "error" };
  }

  if (result.status === "need_post") {
    const need = result.need;
    redirect(`/${lang}/goods/new${query({ kind: need?.kind, direction: need?.direction, date: need?.date, interest: id })}`);
  }

  return { status: result.status, message: result.message };
}

/** Closes one of the member's own posts. */
export async function closeGoods(form: FormData): Promise<void> {
  const lang = localeOf(form);
  const id = Number(form.get("id"));

  if (Number.isInteger(id) && id > 0) {
    try {
      await ownerApi(`/goods/${id}/close`, { method: "POST", locale: lang });
    } catch {
      // The page shows the post's real state either way.
    }
  }

  revalidatePath(`/${lang}/goods`, "layout");
  redirect(`/${lang}/goods/mine`);
}
