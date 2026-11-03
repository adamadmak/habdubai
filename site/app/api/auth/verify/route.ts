import { NextResponse } from "next/server";
import { api, ApiError } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { pendingLogin } from "@/lib/login";
import { cookieBase, LOGIN_COOKIE, OWNER_COOKIE, OWNER_DAYS } from "@/lib/owner";

type Verified = { status: string; token?: string; attempts_left?: number };

/** The code from Telegram. Right: the token goes into the session cookie, never to the page. */
export async function POST(request: Request) {
  const pending = await pendingLogin();
  if (!pending) return NextResponse.json({ status: "expired" });

  const body = await request.json().catch(() => ({}));
  const code = typeof body?.code === "string" ? body.code.replace(/\D/g, "").slice(0, 6) : "";

  let verified: Verified;
  try {
    verified = await api<Verified>("/owner/login/verify", { method: "POST", body: { ...pending, code }, client: await currentClient() });
  } catch (error) {
    return NextResponse.json({ status: error instanceof ApiError && error.status === 429 ? "busy" : "unavailable" }, { status: 503 });
  }

  if (verified.status === "approved" && verified.token) {
    const response = NextResponse.json({ status: "approved" });
    response.cookies.set(OWNER_COOKIE, verified.token, { ...cookieBase, maxAge: OWNER_DAYS * 24 * 3600 });
    response.cookies.delete(LOGIN_COOKIE);
    return response;
  }

  const response = NextResponse.json({ status: verified.status, attempts_left: verified.attempts_left });
  if (["locked", "expired", "unknown"].includes(verified.status)) response.cookies.delete(LOGIN_COOKIE);
  return response;
}
