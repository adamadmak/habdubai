import { NextResponse, type NextRequest } from "next/server";
import { fromAcceptLanguage, isLocale, LOCALE_COOKIE } from "@/lib/locales";

/**
 * Every page lives under /am, /ti, /om or /en. A path without one goes to the
 * language the visitor chose last time, else the browser's (om or gaz for
 * Afaan Oromo), else English.
 */
export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const first = pathname.split("/")[1];

  if (isLocale(first)) {
    const response = NextResponse.next();
    if (request.cookies.get(LOCALE_COOKIE)?.value !== first) {
      response.cookies.set(LOCALE_COOKIE, first, { path: "/", maxAge: 60 * 60 * 24 * 365, sameSite: "lax" });
    }
    return response;
  }

  const saved = request.cookies.get(LOCALE_COOKIE)?.value;
  const locale = isLocale(saved) ? saved : fromAcceptLanguage(request.headers.get("accept-language"));
  const url = request.nextUrl.clone();
  url.pathname = `/${locale}${pathname === "/" ? "" : pathname}`;

  return NextResponse.redirect(url);
}

export const config = {
  // Not the route handlers, Next's files, or anything with a file extension (sitemap.xml, icon.svg).
  matcher: ["/((?!api|_next|.*\\..*).*)"],
};
