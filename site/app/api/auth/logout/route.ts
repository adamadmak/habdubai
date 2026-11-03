import { NextResponse } from "next/server";
import { isLocale } from "@/lib/locales";
import { OWNER_COOKIE, ownerApi } from "@/lib/owner";
import { siteUrl } from "@/lib/site";

/** Sign out: the token is revoked at the API, then the cookie goes. */
export async function POST(request: Request) {
  try {
    await ownerApi("/logout", { method: "POST" });
  } catch {
    // Already invalid: clearing the cookie is all that is left.
  }

  const form = await request.formData().catch(() => null);
  const lang = form?.get("lang");
  // The public address, not request.url: behind the host's proxy that is the container's own.
  const response = NextResponse.redirect(siteUrl(`${isLocale(lang) ? lang : "en"}/business`), 303);
  response.cookies.delete(OWNER_COOKIE);

  return response;
}
