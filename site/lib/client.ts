/**
 * The visitor behind a request to this website, for the API's per-visitor
 * limits and the "Device" line in the Telegram sign-in prompt. The host's
 * proxy appends the address it saw to X-Forwarded-For, so the last entry is
 * the one a visitor cannot forge.
 */
import { headers } from "next/headers";

export type Client = { ip: string | null; agent: string | null };

export async function currentClient(): Promise<Client> {
  const h = await headers();
  const forwarded = (h.get("x-forwarded-for") ?? "").split(",").map((s) => s.trim()).filter(Boolean);
  const ip = forwarded.at(-1) ?? h.get("x-real-ip") ?? null;

  return { ip, agent: h.get("user-agent")?.slice(0, 200) ?? null };
}
