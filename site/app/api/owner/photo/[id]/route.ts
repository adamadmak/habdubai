import { NextResponse } from "next/server";
import { refuseCrossSite, requestLocale } from "@/lib/guard";
import { dict } from "@/lib/i18n";
import { ownerToken } from "@/lib/owner";

const API = (process.env.API_URL || "http://localhost:8000/api").replace(/\/+$/, "");

/** A listing photo, passed on to the API with the session's token (the browser never has it). */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const { id } = await params;
  const token = await ownerToken();
  // Until the form is read, the page it came from says the language; then its own lang field does.
  let words = dict(requestLocale(request)).business;

  if (!token) return NextResponse.json({ message: words.sessionExpired }, { status: 401 });
  if (!/^\d+$/.test(id)) return NextResponse.json({ message: words.photo.noListing }, { status: 404 });

  // Refused before reading: the body would otherwise be held in memory whatever its size.
  const length = Number(request.headers.get("content-length") ?? "");
  if (!Number.isFinite(length) || length <= 0) return NextResponse.json({ errors: { photo: [words.photo.choose] } }, { status: 411 });
  if (length > 9 * 1024 * 1024) return NextResponse.json({ errors: { photo: [words.photo.tooBig] } }, { status: 413 });

  const incoming = await request.formData().catch(() => null);
  const locale = requestLocale(request, incoming?.get("lang"));
  words = dict(locale).business;

  const photo = incoming?.get("photo");
  if (!(photo instanceof File)) return NextResponse.json({ errors: { photo: [words.photo.choose] } }, { status: 422 });
  if (photo.size > 8 * 1024 * 1024) return NextResponse.json({ errors: { photo: [words.photo.tooBig] } }, { status: 413 });

  const outgoing = new FormData();
  outgoing.append("photo", photo, photo.name || "photo.jpg");

  try {
    const response = await fetch(`${API}/owner/businesses/${id}/photo`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}`, "X-Locale": locale },
      body: outgoing,
      cache: "no-store",
      // Longer than a page's API call: this one carries the photo.
      signal: AbortSignal.timeout(20_000),
    });

    return new NextResponse(await response.text(), { status: response.status, headers: { "Content-Type": "application/json" } });
  } catch {
    return NextResponse.json({ message: words.serverError }, { status: 502 });
  }
}
