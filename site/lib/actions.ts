"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { ApiError, type OwnerBusiness } from "./api";
import { isLocale } from "./locales";
import { ownerApi } from "./owner";

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

function failed(error: unknown): FormState {
  if (error instanceof ApiError) {
    if (error.status === 401) return { ok: false, message: "signin" };
    return { ok: false, message: error.message, errors: error.errors };
  }
  return { ok: false, message: "error" };
}

/** Create a listing, or save changes to one. A new listing then opens its edit page so a photo can be added. */
export async function saveListing(_previous: FormState, form: FormData): Promise<FormState> {
  const lang = String(form.get("lang"));
  const id = Number(form.get("id")) || null;
  let created: OwnerBusiness | null = null;

  try {
    if (id) {
      await ownerApi(`/businesses/${id}`, { method: "PUT", body: body(form) });
    } else {
      created = (await ownerApi<{ business: OwnerBusiness }>("/businesses", { method: "POST", body: body(form) })).business;
    }
  } catch (error) {
    return failed(error);
  }

  revalidatePath(`/${isLocale(lang) ? lang : "en"}/business`);

  if (created) {
    redirect(`/${isLocale(lang) ? lang : "en"}/business/${created.id}/edit?new=1`);
  }

  return { ok: true, savedAt: Date.now() };
}

export async function removeListing(form: FormData): Promise<void> {
  const lang = String(form.get("lang"));
  const id = Number(form.get("id"));

  if (id) {
    try {
      await ownerApi(`/businesses/${id}`, { method: "DELETE" });
    } catch {
      // The dashboard shows the listing's real state either way.
    }
  }

  revalidatePath(`/${isLocale(lang) ? lang : "en"}/business`);
  redirect(`/${isLocale(lang) ? lang : "en"}/business`);
}

export async function claimListing(_previous: FormState, form: FormData): Promise<FormState> {
  const id = Number(form.get("business_id"));
  const note = String(form.get("note") ?? "").trim();

  try {
    await ownerApi(`/businesses/${id}/claim`, { method: "POST", body: { note: note || null } });
  } catch (error) {
    return failed(error);
  }

  return { ok: true, savedAt: Date.now() };
}
