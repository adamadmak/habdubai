import { ImageResponse } from "next/og";
import { LOGO_HEIGHT, LOGO_INK, LOGO_WIDTH } from "@/lib/logo-images";

export const size = { width: 1200, height: 630 };
export const contentType = "image/png";
export const alt = "HabUAE";

/**
 * The card a shared link shows in Telegram, WhatsApp and the rest: the
 * morning page with the mark, the brand and one line in English (the image
 * renderer has no Ge'ez face, so the page's own title stays on the page).
 */
export default function OpenGraphImage() {
  const diamond = (x: number, y: number, s: number, color: string, opacity = 1) => (
    <div key={`${x}-${y}`} style={{ position: "absolute", left: x, top: y, width: s, height: s, transform: "rotate(45deg)", background: color, opacity }} />
  );

  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", background: "#fffbef", position: "relative", fontFamily: "Arial, Helvetica, sans-serif", color: "#191919" }}>

        <div style={{ display: "flex", flexDirection: "column", gap: 14, padding: "56px 72px 0" }}>
          {/* eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text */}
          <img src={LOGO_INK} width={LOGO_WIDTH * 0.75} height={LOGO_HEIGHT * 0.75} />
          <div style={{ fontSize: 20, color: "#5b5b5b" }}>Amharic · Tigrinya · Afaan Oromoo · English</div>
        </div>

        <div style={{ display: "flex", flexDirection: "column", padding: "56px 72px 0", maxWidth: 980 }}>
          {/* Two lines rather than one box of mixed text: the renderer wants every box with several children to be a flex box. */}
          <div style={{ display: "flex", flexDirection: "column", fontSize: 74, lineHeight: 1.02, fontWeight: 700, letterSpacing: -2 }}>
            <div>The Habesha community in the Emirates,</div>
            <div style={{ color: "#d40511" }}>in one place.</div>
          </div>
          <div style={{ marginTop: 28, fontSize: 28, color: "#5b5b5b", fontFamily: "Arial, Helvetica, sans-serif" }}>
            Jobs checked for scam signs · Habesha places · news and events · the goods board
          </div>
        </div>

        {/* The tibeb thread along the foot. */}
        <div style={{ position: "absolute", left: 0, right: 0, bottom: 0, height: 22, background: "#d40511", display: "flex", overflow: "hidden" }}>
          <div style={{ position: "absolute", left: 0, right: 0, top: 2, height: 2, background: "#ffc20e" }} />
          <div style={{ position: "absolute", left: 0, right: 0, bottom: 2, height: 2, background: "#ffc20e" }} />
          {Array.from({ length: 30 }, (_, i) => diamond(10 + i * 40, 6, 10, i % 3 === 1 ? "#ffffff" : "#ffc20e", i % 3 === 2 ? 0.9 : 1))}
        </div>
      </div>
    ),
    size,
  );
}
