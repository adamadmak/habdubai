import { NextResponse } from "next/server";
import { currentOwner, ownerToken } from "@/lib/owner";

/**
 * Who is signed in, for the chip in the header: the name only. Without a
 * session cookie it answers at once, so a visitor costs the API nothing.
 */
export async function GET() {
  const headers = { "Cache-Control": "no-store" };

  if (!(await ownerToken())) return NextResponse.json({ owner: null }, { headers });

  try {
    const owner = await currentOwner();
    return NextResponse.json({ owner: owner ? { name: owner.name, member: owner.member } : null }, { headers });
  } catch {
    // The API is briefly away: the header shows "sign in"; the page itself will say more.
    return NextResponse.json({ owner: null }, { headers });
  }
}
