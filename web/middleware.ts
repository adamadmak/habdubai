import { NextResponse, type NextRequest } from "next/server";
import { getToken } from "next-auth/jwt";

const locales = ["en", "am", "ti"];
const defaultLocale = "en";

/** The locale prefix on this path, or null when there is none. */
function localeOf(pathname: string): string | null {
  const first = pathname.split("/")[1];
  return locales.includes(first) ? first : null;
}

export async function middleware(request: NextRequest) {
  const { pathname, search } = request.nextUrl;
  const locale = localeOf(pathname);

  // /members -> /en/members
  if (locale === null) {
    const url = request.nextUrl.clone();
    url.pathname = `/${defaultLocale}${pathname === "/" ? "/dashboard" : pathname}`;
    return NextResponse.redirect(url);
  }

  const path = pathname.slice(locale.length + 1) || "/";
  const token = await getToken({ req: request, secret: process.env.NEXTAUTH_SECRET ?? process.env.AUTH_SECRET });
  const isAuthPage = path.startsWith("/auth/");

  if (!token && !isAuthPage) {
    const url = request.nextUrl.clone();
    url.pathname = `/${locale}/auth/login`;
    url.search = path === "/" ? "" : `?next=${encodeURIComponent(path + search)}`;
    return NextResponse.redirect(url);
  }

  if (token && (isAuthPage || path === "/")) {
    const url = request.nextUrl.clone();
    url.pathname = `/${locale}/dashboard`;
    url.search = "";
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  // Skip Next internals, the auth API route, static files and anything with an extension.
  matcher: ["/((?!api|_next|assets|images|favicon.ico|.*\\..*).*)"],
};
