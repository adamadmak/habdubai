import { NextResponse } from "next/server";
import { ownerToken } from "@/lib/owner";

const API = (process.env.API_URL || "http://localhost:8000/api").replace(/\/+$/, "");

/** A listing photo, passed on to the API with the session's token (the browser never has it). */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const token = await ownerToken();

  if (!token) return NextResponse.json({ message: "Sign in first." }, { status: 401 });
  if (!/^\d+$/.test(id)) return NextResponse.json({ message: "Not found." }, { status: 404 });

  const incoming = await request.formData();
  const photo = incoming.get("photo");
  if (!(photo instanceof File)) return NextResponse.json({ errors: { photo: ["Choose a photo."] } }, { status: 422 });
  if (photo.size > 8 * 1024 * 1024) return NextResponse.json({ errors: { photo: ["The photo is larger than 8 MB."] } }, { status: 422 });

  const outgoing = new FormData();
  outgoing.append("photo", photo, photo.name || "photo.jpg");

  const response = await fetch(`${API}/owner/businesses/${id}/photo`, {
    method: "POST",
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    body: outgoing,
    cache: "no-store",
  });

  return new NextResponse(await response.text(), { status: response.status, headers: { "Content-Type": "application/json" } });
}
