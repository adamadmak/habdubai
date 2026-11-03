import { NextResponse } from "next/server";
import { api, ApiError } from "@/lib/api";
import { cookieBase, LOGIN_COOKIE } from "@/lib/owner";

type Started = { ref: string; secret: string; code: string; link: string | null; expires_at: string };

/**
 * Start "Continue with Telegram". The browser gets the bot link and the
 * check code; the secret that will collect the token stays in an httpOnly
 * cookie only this server reads.
 */
export async function POST() {
  try {
    const started = await api<Started>("/owner/login/start", { method: "POST" });
    const response = NextResponse.json({ link: started.link, code: started.code, expires_at: started.expires_at });
    response.cookies.set(LOGIN_COOKIE, JSON.stringify({ ref: started.ref, secret: started.secret }), { ...cookieBase, maxAge: 15 * 60 });

    return response;
  } catch (error) {
    const status = error instanceof ApiError ? error.status : 502;
    return NextResponse.json({ status: "unavailable" }, { status: status === 429 ? 429 : 503 });
  }
}
