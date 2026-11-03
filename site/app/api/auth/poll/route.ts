import { NextResponse } from "next/server";
import { api } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { refuseCrossSite, requestLocale } from "@/lib/guard";
import { pendingLogin } from "@/lib/login";
import { clearCookie, LOGIN_COOKIE } from "@/lib/owner";

/** "Has the person said yes in Telegram?" When they have, the page asks for the code the bot sent them. */
export async function POST(request: Request) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const pending = await pendingLogin();
  if (!pending) return NextResponse.json({ status: "expired" });

  const body = await request.json().catch(() => ({}));
  const locale = requestLocale(request, body?.lang);

  let status = "pending";
  try {
    status = (await api<{ status: string }>("/owner/login/poll", { method: "POST", body: pending, client: await currentClient(), locale })).status;
  } catch {
    return NextResponse.json({ status: "pending" });
  }

  const shown = status === "confirmed" ? "code" : status === "unknown" || status === "used" ? "expired" : status;
  const response = NextResponse.json({ status: shown });
  if (shown === "declined" || shown === "expired") clearCookie(response, LOGIN_COOKIE);

  return response;
}
