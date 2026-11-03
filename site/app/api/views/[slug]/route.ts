import { NextResponse } from "next/server";
import { api } from "@/lib/api";
import { currentClient } from "@/lib/client";

/** A visit to a business page, from the visitor's browser: the API counts each visitor once every few hours. */
export async function POST(_request: Request, { params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  if (!/^[a-z0-9-]{1,170}$/.test(slug)) return new NextResponse(null, { status: 404 });

  try {
    await api(`/public/businesses/${slug}/view`, { method: "POST", client: await currentClient() });
  } catch {
    // A missed count is not worth an error.
  }

  return new NextResponse(null, { status: 204 });
}
