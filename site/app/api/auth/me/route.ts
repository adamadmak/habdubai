import { NextResponse } from "next/server";
import { checkOwner, clearCookie, OWNER_COOKIE, ownerToken } from "@/lib/owner";

/**
 * Who is signed in, for the chip in the header: the name only. Without a
 * session cookie it answers at once, so a visitor costs the API nothing. A
 * cookie whose token the API no longer accepts (expired, revoked, banned) is
 * dropped here, so it stops costing a call on every page for the next 60 days.
 */
export async function GET() {
  const headers = { "Cache-Control": "no-store" };

  if (!(await ownerToken())) return NextResponse.json({ owner: null }, { headers });

  const owner = await checkOwner();
  if (owner === undefined) {
    // The API is briefly away: the header shows "sign in"; the page itself will say more.
    return NextResponse.json({ owner: null }, { headers });
  }

  const response = NextResponse.json({ owner: owner ? { name: owner.name, member: owner.member } : null }, { headers });
  if (owner === null) clearCookie(response, OWNER_COOKIE);

  return response;
}
