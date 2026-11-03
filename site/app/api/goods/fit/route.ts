import { NextResponse } from "next/server";
import { api, GOODS_DIRECTIONS, GOODS_KINDS, query, type GoodsFit } from "@/lib/api";
import { currentClient } from "@/lib/client";
import { refuseCrossSite } from "@/lib/guard";

/**
 * "Who would this post pair with?", asked by the goods form while a member
 * writes (components/goods/GoodsForm.tsx), before anything is posted. Only
 * this site's own pages may ask, the question is checked before it reaches
 * the API, and it goes as the visitor so the API's per-visitor limit applies.
 * Any failure answers "nothing to show": the form simply leaves the list out.
 */
export async function GET(request: Request) {
  const refused = refuseCrossSite(request);
  if (refused) return refused;

  const params = new URL(request.url).searchParams;
  const kind = params.get("kind");
  const direction = params.get("direction");
  const date = params.get("date") ?? "";
  const kilos = Number(params.get("kilos"));

  const valid =
    (GOODS_KINDS as readonly (string | null)[]).includes(kind) &&
    (GOODS_DIRECTIONS as readonly (string | null)[]).includes(direction) &&
    /^\d{4}-\d{2}-\d{2}$/.test(date) &&
    Number.isInteger(kilos) &&
    kilos >= 1 &&
    kilos <= 100;
  if (!valid) return NextResponse.json({ count: 0, posts: [] }, { status: 422 });

  try {
    const fit = await api<GoodsFit>(`/public/goods/fit${query({ kind, direction, date, kilos })}`, { client: await currentClient() });
    return NextResponse.json({ count: fit.count, posts: fit.posts.slice(0, 4) }, { headers: { "Cache-Control": "no-store" } });
  } catch {
    return NextResponse.json({ count: 0, posts: [] }, { status: 503 });
  }
}
