import { apiOrNull, type Announcement } from "@/lib/api";
import { firstLine } from "@/components/Cards";
import { pick } from "@/lib/labels";
import { isLocale } from "@/lib/locales";
import { siteUrl } from "@/lib/site";

/** An all-day calendar entry (.ics) for an event, which every phone's calendar opens. */
export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const asked = new URL(request.url).searchParams.get("lang");
  const lang = isLocale(asked) ? asked : "en";

  const data = /^\d+$/.test(id) ? await apiOrNull<{ announcement: Announcement }>(`/public/announcements/${id}`) : null;
  const a = data?.announcement;
  if (!a?.event_date) return new Response("Not found", { status: 404 });

  const text = pick(a.text, lang);
  const day = a.event_date.replace(/-/g, "");
  const next = new Date(`${a.event_date}T12:00:00Z`);
  next.setUTCDate(next.getUTCDate() + 1);
  const end = next.toISOString().slice(0, 10).replace(/-/g, "");
  const escape = (s: string) => s.replace(/\\/g, "\\\\").replace(/;/g, "\\;").replace(/,/g, "\\,").replace(/\r?\n/g, "\\n");
  const stamp = new Date().toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");

  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Habesha Dubai//News//EN",
    "CALSCALE:GREGORIAN",
    "BEGIN:VEVENT",
    `UID:announcement-${a.id}@habesha-dubai`,
    `DTSTAMP:${stamp}`,
    `DTSTART;VALUE=DATE:${day}`,
    `DTEND;VALUE=DATE:${end}`,
    `SUMMARY:${escape(firstLine(text, 90))}`,
    `DESCRIPTION:${escape(text.slice(0, 1500))}`,
    a.event_place ? `LOCATION:${escape(a.event_place)}` : "",
    `URL:${siteUrl(`${lang}/news/${a.id}`)}`,
    "END:VEVENT",
    "END:VCALENDAR",
  ].filter(Boolean);

  return new Response(lines.join("\r\n"), {
    headers: {
      "Content-Type": "text/calendar; charset=utf-8",
      "Content-Disposition": `attachment; filename="event-${a.id}.ics"`,
      "Cache-Control": "public, max-age=300",
    },
  });
}
