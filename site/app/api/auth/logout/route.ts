import { NextResponse } from "next/server";
import { refuseCrossSite, requestLocale } from "@/lib/guard";
import { clearCookie, OWNER_COOKIE, ownerApi } from "@/lib/owner";

/** Sign out: the token is revoked at the API, then the cookie goes. */
export async function POST(request: Request) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const form = await request.formData().catch(() => null);
  const lang = requestLocale(request, form?.get("lang"));
  // Where to land afterwards: a page of this site in the same language (the form says which), else the front page.
  const back = String(form?.get("back") ?? "");
  const landing = back.startsWith(`/${lang}/`) && !back.startsWith("//") ? back : `/${lang}`;

  try {
    await ownerApi("/logout", { method: "POST", locale: lang });
  } catch {
    // Already invalid: clearing the cookie is all that is left.
  }

  // A relative Location: the browser resolves it against the address it used,
  // whatever the container behind the host's proxy thinks its own address is.
  const response = new NextResponse(null, { status: 303, headers: { Location: landing } });
  clearCookie(response, OWNER_COOKIE);

  return response;
}
