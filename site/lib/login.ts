import { cookies } from "next/headers";
import { LOGIN_COOKIE } from "./owner";

/** The sign-in in progress, from its httpOnly cookie. */
export async function pendingLogin(): Promise<{ ref: string; secret: string } | null> {
  const raw = (await cookies()).get(LOGIN_COOKIE)?.value;
  try {
    const parsed = raw ? JSON.parse(raw) : null;
    return parsed && typeof parsed.ref === "string" && typeof parsed.secret === "string" ? parsed : null;
  } catch {
    return null;
  }
}
