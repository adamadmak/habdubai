import { ImageResponse } from "next/og";
import { isLocale, type Locale } from "@/lib/locales";

/**
 * The welcome card the bot sends when someone has just joined: a night
 * picture of the brand with the four things that live here. Telegram fetches
 * it by URL, so it is public and cached for a day.
 *
 * Latin words only: the image renderer has no Ge'ez face (the same limit as
 * app/opengraph-image.tsx), so Amharic and Tigrinya get the picture and the
 * place names, and the bot's caption carries the words in their language.
 */
const WORDS: Record<Locale, { welcome: string; line: string; labels: [string, string, string, string] | null }> = {
  en: { welcome: "Welcome to the community", line: "Free · in your language · for Habesha across the Emirates", labels: ["Jobs", "Places", "Questions", "Goods"] },
  om: { welcome: "Baga nagaan dhuftan", line: "Bilisa · afaan keessaniin · Habashaa Imaaraat keessa jiraniif", labels: ["Hojii", "Bakkeewwan", "Gaaffilee", "Meeshaa"] },
  am: { welcome: "Habesha Dubai", line: "Dubai · Abu Dhabi · Sharjah · Ajman · Al Ain", labels: null },
  ti: { welcome: "Habesha Dubai", line: "Dubai · Abu Dhabi · Sharjah · Ajman · Al Ain", labels: null },
};

const GOLD = "#ffc20e";
const INK = "#f5f5f5";

/** Four line icons, hand drawn so the card needs no font or network for them. */
const ICONS = [
  // briefcase
  <svg key="jobs" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke={GOLD} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="7" width="18" height="13" rx="2.5" /><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7M3 12h18" /></svg>,
  // map pin
  <svg key="places" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke={GOLD} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>,
  // question in a circle
  <svg key="questions" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke={GOLD} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12 17h.01" /></svg>,
  // parcel
  <svg key="goods" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke={GOLD} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 4 7v10l8 4 8-4V7l-8-4ZM4 7l8 4 8-4M12 11v10" /></svg>,
];

/** Four pictures, drawn once a day: rendering one costs a second of CPU, and nothing in front of the app caches it. */
const DRAWN = new Map<Locale, { body: ArrayBuffer; at: number }>();
const KEEP_MS = 24 * 60 * 60 * 1000;

const png = (body: ArrayBuffer) => new Response(body, { headers: { "Content-Type": "image/png", "Cache-Control": "public, max-age=86400, s-maxage=86400" } });

export async function GET(_request: Request, { params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) return new Response(null, { status: 404 });
  const l: Locale = lang;
  const w = WORDS[l];

  const drawn = DRAWN.get(l);
  if (drawn && Date.now() - drawn.at < KEEP_MS) return png(drawn.body);

  const diamond = (x: number, y: number, s: number, color: string, opacity = 1) => (
    <div key={`${x}-${y}`} style={{ position: "absolute", left: x, top: y, width: s, height: s, transform: "rotate(45deg)", background: color, opacity }} />
  );

  const image = new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", position: "relative", overflow: "hidden", background: "#191919", color: INK, fontFamily: "Arial, Helvetica, sans-serif" }}>
        {/* The soft red glow of the website's dark bands, low on the right. */}
        <div style={{ position: "absolute", right: -260, bottom: -340, width: 820, height: 700, borderRadius: 999, background: "rgba(212,5,17,0.34)", filter: "blur(100px)" }} />
        {/* The horizon: one thin line of ink, the skyline's idea rather than its picture. */}
        <div style={{ position: "absolute", left: 0, right: 0, top: 418, height: 1, background: "rgba(238,241,251,0.18)" }} />

        <div style={{ display: "flex", alignItems: "center", gap: 22, padding: "56px 72px 0" }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "center", width: 84, height: 84, borderRadius: 24, background: "linear-gradient(135deg, #e0101c, #a8040d)" }}>
            <svg width="60" height="60" viewBox="0 0 32 32">
              <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="#ffc20e" />
              <path d="M16 10 22 16 16 22 10 16Z" fill="#d40511" />
              <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
            </svg>
          </div>
          <div style={{ display: "flex", flexDirection: "column" }}>
            <div style={{ fontSize: 40, fontWeight: 700, letterSpacing: -1 }}>Habesha Dubai</div>
            <div style={{ fontSize: 20, color: "rgba(238,241,251,0.66)" }}>Amharic · Tigrinya · Afaan Oromoo · English</div>
          </div>
        </div>

        <div style={{ display: "flex", flexDirection: "column", padding: "48px 72px 0" }}>
          <div style={{ fontSize: 66, lineHeight: 1.05, fontWeight: 700, letterSpacing: -2, color: "#ffffff" }}>{w.welcome}</div>
          <div style={{ marginTop: 18, fontSize: 26, color: "rgba(238,241,251,0.72)" }}>{w.line}</div>
        </div>

        {/* The four things that live here, as frosted white tiles. */}
        <div style={{ display: "flex", gap: 18, padding: "44px 72px 0" }}>
          {ICONS.map((icon, i) => (
            <div key={i} style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 10, width: 246, height: 118, borderRadius: 26, background: "rgba(255,255,255,0.09)", border: "1px solid rgba(255,255,255,0.18)" }}>
              {icon}
              {w.labels && <div style={{ fontSize: 22, fontWeight: 600, color: INK }}>{w.labels[i]}</div>}
            </div>
          ))}
        </div>

        {/* The tibeb thread along the foot. */}
        <div style={{ position: "absolute", left: 0, right: 0, bottom: 0, height: 22, background: "#d40511", display: "flex", overflow: "hidden" }}>
          <div style={{ position: "absolute", left: 0, right: 0, top: 2, height: 2, background: "#ffc20e" }} />
          <div style={{ position: "absolute", left: 0, right: 0, bottom: 2, height: 2, background: "#ffc20e" }} />
          {Array.from({ length: 30 }, (_, i) => diamond(10 + i * 40, 6, 10, i % 3 === 1 ? "#ffffff" : "#ffc20e", i % 3 === 2 ? 0.9 : 1))}
        </div>
      </div>
    ),
    { width: 1200, height: 630 },
  );

  const body = await image.arrayBuffer();
  DRAWN.set(l, { body, at: Date.now() });

  return png(body);
}
