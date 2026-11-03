import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { api } from "@/lib/api";
import { cookieBase, LOGIN_COOKIE, OWNER_COOKIE, OWNER_DAYS } from "@/lib/owner";

type Polled = { status: string; token?: string };

/** "Has the person confirmed in Telegram yet?" On yes, the token goes into the session cookie, never to the page. */
export async function POST() {
  const raw = (await cookies()).get(LOGIN_COOKIE)?.value;
  let pending: { ref?: string; secret?: string } = {};
  try {
    pending = raw ? JSON.parse(raw) : {};
  } catch {
    pending = {};
  }

  if (!pending.ref || !pending.secret) {
    return NextResponse.json({ status: "expired" });
  }

  let polled: Polled;
  try {
    polled = await api<Polled>("/owner/login/poll", { method: "POST", body: { ref: pending.ref, secret: pending.secret } });
  } catch {
    return NextResponse.json({ status: "pending" });
  }

  if (polled.status === "approved" && polled.token) {
    const response = NextResponse.json({ status: "approved" });
    response.cookies.set(OWNER_COOKIE, polled.token, { ...cookieBase, maxAge: OWNER_DAYS * 24 * 3600 });
    response.cookies.delete(LOGIN_COOKIE);
    return response;
  }

  const finished = ["declined", "expired", "used", "unknown"].includes(polled.status);
  const response = NextResponse.json({ status: polled.status === "unknown" || polled.status === "used" ? "expired" : polled.status });
  if (finished) response.cookies.delete(LOGIN_COOKIE);
  return response;
}
