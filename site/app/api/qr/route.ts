import QRCode from "qrcode";
import { api, type Meta } from "@/lib/api";

/**
 * The bot's address as a code to scan from a computer. A separate file so phones,
 * which get the button and hide the picture, never download it.
 */
export async function GET() {
  const meta = await api<Meta>("/public/meta", { revalidate: 300 }).catch(() => null);
  if (!meta?.bot.link) return new Response(null, { status: 404 });

  const svg = await QRCode.toString(meta.bot.link, { type: "svg", margin: 0, errorCorrectionLevel: "M", color: { dark: "#191919", light: "#ffffff" } });

  return new Response(svg, { headers: { "Content-Type": "image/svg+xml", "Cache-Control": "public, max-age=86400" } });
}
