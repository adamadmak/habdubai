/**
 * The business owner's session. The API token lives in an httpOnly cookie
 * that only this website's server reads; the browser never holds it. The
 * secret of a sign-in in progress lives in a second httpOnly cookie, so the
 * page polling for "confirmed in Telegram?" never sees it either.
 */
import { cookies } from "next/headers";
import type { NextResponse } from "next/server";
import { api, ApiError, type Owner } from "./api";
import { currentClient } from "./client";
import type { Locale } from "./locales";

const PRODUCTION = process.env.NODE_ENV === "production";

/*
 * In production both names carry the __Host- prefix: the browser then takes
 * the cookie only over HTTPS, for the whole site and from this exact host, so
 * nothing on a neighbouring subdomain can plant or overwrite a session. Plain
 * names in development, where the site runs on http://localhost.
 */
export const OWNER_COOKIE = PRODUCTION ? "__Host-hd_owner" : "hd_owner";
export const LOGIN_COOKIE = PRODUCTION ? "__Host-hd_login" : "hd_login";
export const OWNER_DAYS = 60;

export const cookieBase = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: PRODUCTION,
  path: "/",
};

/** Ends a session cookie. A __Host- cookie is only replaced by one with the same attributes, so a bare delete would leave it. */
export function clearCookie(response: NextResponse, name: string): void {
  response.cookies.set(name, "", { ...cookieBase, maxAge: 0 });
}

export async function ownerToken(): Promise<string | null> {
  return (await cookies()).get(OWNER_COOKIE)?.value ?? null;
}

/** A call to the owner API with the session's token, on behalf of this visitor. Throws ApiError(401) without one. */
export async function ownerApi<T>(path: string, init: { method?: string; body?: unknown; locale?: Locale } = {}): Promise<T> {
  const token = await ownerToken();
  if (!token) throw new ApiError(401, "Sign in first.");

  return api<T>(`/owner${path}`, { ...init, token, client: await currentClient() });
}

/** The signed-in owner, or null (no cookie, an expired token, a banned account). */
export async function currentOwner(locale?: Locale): Promise<Owner | null> {
  if (!(await ownerToken())) return null;
  try {
    return (await ownerApi<{ owner: Owner }>("/me", { locale })).owner;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) return null;
    throw error;
  }
}
