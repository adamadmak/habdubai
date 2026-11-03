/**
 * The business owner's session. The API token lives in an httpOnly cookie
 * that only this website's server reads; the browser never holds it. The
 * secret of a sign-in in progress lives in a second httpOnly cookie, so the
 * page polling for "confirmed in Telegram?" never sees it either.
 */
import { cookies } from "next/headers";
import { api, ApiError, type Owner } from "./api";
import { currentClient } from "./client";

export const OWNER_COOKIE = "hd_owner";
export const LOGIN_COOKIE = "hd_login";
export const OWNER_DAYS = 60;

export const cookieBase = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.NODE_ENV === "production",
  path: "/",
};

export async function ownerToken(): Promise<string | null> {
  return (await cookies()).get(OWNER_COOKIE)?.value ?? null;
}

/** A call to the owner API with the session's token, on behalf of this visitor. Throws ApiError(401) without one. */
export async function ownerApi<T>(path: string, init: { method?: string; body?: unknown } = {}): Promise<T> {
  const token = await ownerToken();
  if (!token) throw new ApiError(401, "Sign in first.");

  return api<T>(`/owner${path}`, { ...init, token, client: await currentClient() });
}

/** The signed-in owner, or null (no cookie, an expired token, a banned account). */
export async function currentOwner(): Promise<Owner | null> {
  if (!(await ownerToken())) return null;
  try {
    return (await ownerApi<{ owner: Owner }>("/me")).owner;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) return null;
    throw error;
  }
}
