import { NextResponse } from "next/server";
import { api, ApiError } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { refuseCrossSite, requestLocale } from "@/lib/guard";
import { cookieBase, OWNER_COOKIE, OWNER_DAYS, ownerToken } from "@/lib/owner";

type Answer = { status: string; token?: string };

/**
 * The website opened inside Telegram: what Telegram signed for this person goes
 * to the API, which checks it. Right: the token goes into the session cookie,
 * never to the page. The current session comes along, so someone already signed
 * in as this person simply stays signed in.
 */
export async function POST(request: Request) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const body = await request.json().catch(() => ({}));
  const initData = typeof body?.init_data === "string" ? body.init_data.slice(0, 4096) : "";
  if (!initData) return NextResponse.json({ status: "invalid" }, { status: 400 });

  let answer: Answer;
  try {
    answer = await api<Answer>("/owner/login/webapp", {
      method: "POST",
      body: { init_data: initData },
      token: (await ownerToken()) ?? undefined,
      client: await currentClient(),
      locale: requestLocale(request, body?.lang),
    });
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      return NextResponse.json({ status: error.status === 403 ? "blocked" : "invalid" }, { status: error.status });
    }
    return NextResponse.json({ status: error instanceof ApiError && error.status === 429 ? "busy" : "unavailable" }, { status: 503 });
  }

  if (answer.status === "approved" && answer.token) {
    const response = NextResponse.json({ status: "approved" });
    response.cookies.set(OWNER_COOKIE, answer.token, { ...cookieBase, maxAge: OWNER_DAYS * 24 * 3600 });
    return response;
  }

  return NextResponse.json({ status: answer.status });
}
