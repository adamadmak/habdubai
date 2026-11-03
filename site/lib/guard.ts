/**
 * For the route handlers that change something: a sign-in, a sign-out, a
 * photo, a count. Browsers say where a request came from; one that another
 * site made the visitor's browser send is refused before it does anything.
 */
import { NextResponse } from "next/server";
import { DEFAULT_LOCALE, isLocale, LOCALE_COOKIE, type Locale } from "./locales";

/** This site's own origins: the request's (as the server and as the proxy in front of it see it) and the public address. */
function ownOrigins(request: Request): Set<string> {
  const origins = new Set<string>();
  const add = (value: string | null | undefined) => {
    try {
      if (value) origins.add(new URL(value).origin);
    } catch {
      // Not an address: nothing to add.
    }
  };

  add(request.url);
  add(process.env.SITE_URL);
  // Behind the host's proxy request.url is the container's own address; the proxy passes the public host on.
  const host = request.headers.get("x-forwarded-host")?.split(",")[0]?.trim() || request.headers.get("host");
  if (host) {
    const proto = request.headers.get("x-forwarded-proto")?.split(",")[0]?.trim() || new URL(request.url).protocol.replace(":", "");
    add(`${proto}://${host}`);
  }

  return origins;
}

/** A 403 for a request sent from another site, or null when it came from this site's own pages. */
export function refuseCrossSite(request: Request): NextResponse | null {
  const fetchSite = request.headers.get("sec-fetch-site");
  const origin = request.headers.get("origin");

  if ((fetchSite && fetchSite !== "same-origin") || (origin && !ownOrigins(request).has(origin))) {
    return NextResponse.json({ message: "Forbidden." }, { status: 403 });
  }

  return null;
}

/** The language of the page behind a request: a field it sent, else the path of the page it came from, else the saved choice. */
export function requestLocale(request: Request, asked?: unknown): Locale {
  if (isLocale(asked)) return asked;

  try {
    const first = new URL(request.headers.get("referer") ?? "").pathname.split("/")[1];
    if (isLocale(first)) return first;
  } catch {
    // No referring page (or a privacy setting hid it).
  }

  const saved = (request.headers.get("cookie") ?? "").match(new RegExp(`(?:^|;\\s*)${LOCALE_COOKIE}=([^;]+)`))?.[1];
  return isLocale(saved) ? saved : DEFAULT_LOCALE;
}
