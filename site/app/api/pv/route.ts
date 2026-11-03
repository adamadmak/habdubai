import { NextResponse } from "next/server";
import { api } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { refuseCrossSite } from "@/lib/guard";
import { LOCALES } from "@/lib/locales";

/** A page of the site: /am, /ti, /om or /en and what follows. */
const PAGE = new RegExp(`^/(${LOCALES.join("|")})(/[^?#]*)?$`);

/**
 * A page view, from components/PageViews.tsx. The API counts it per day and
 * path only; the visitor goes along for its rate limits, nothing is kept.
 * 204 whatever happens next: a count is never worth an error in someone's console.
 */
export async function POST(request: Request) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const done = new NextResponse(null, { status: 204 });
  const body = await request.json().catch(() => null);
  const path = typeof body?.path === "string" ? body.path : "";
  if (!PAGE.test(path) || path.length > 200) return done;

  try {
    await api("/public/pageview", { method: "POST", body: { path }, client: await currentClient() });
  } catch {
    // A missed count is not worth an error.
  }

  return done;
}
