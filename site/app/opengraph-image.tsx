import { ImageResponse } from "next/og";

export const size = { width: 1200, height: 630 };
export const contentType = "image/png";
export const alt = "Habesha Dubai";

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
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", background: "linear-gradient(160deg, #ffffff 0%, #fbf8f2 55%, #f3eee3 100%)", position: "relative", fontFamily: "Georgia, serif", color: "#10142b" }}>
        {/* The aurora: blue top left, gold bottom right. */}
        <div style={{ position: "absolute", left: -200, top: -260, width: 760, height: 620, borderRadius: 999, background: "rgba(27,77,219,0.16)", filter: "blur(80px)" }} />
        <div style={{ position: "absolute", right: -220, bottom: -300, width: 760, height: 640, borderRadius: 999, background: "rgba(242,183,5,0.3)", filter: "blur(90px)" }} />

        <div style={{ display: "flex", alignItems: "center", gap: 22, padding: "64px 72px 0" }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "center", width: 84, height: 84, borderRadius: 24, background: "linear-gradient(135deg, #2a5cf0, #1336a6)" }}>
            <svg width="60" height="60" viewBox="0 0 32 32">
              <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="#f2b705" />
              <path d="M16 10 22 16 16 22 10 16Z" fill="#1b4ddb" />
              <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
            </svg>
          </div>
          <div style={{ display: "flex", flexDirection: "column" }}>
            <div style={{ fontSize: 40, fontWeight: 700, fontFamily: "Arial, Helvetica, sans-serif", letterSpacing: -1 }}>Habesha Dubai</div>
            <div style={{ fontSize: 20, color: "#586078", fontFamily: "Arial, Helvetica, sans-serif" }}>Amharic · Tigrinya · Afaan Oromoo · English</div>
          </div>
        </div>

        <div style={{ display: "flex", flexDirection: "column", padding: "56px 72px 0", maxWidth: 980 }}>
          {/* Two lines rather than one box of mixed text: the renderer wants every box with several children to be a flex box. */}
          <div style={{ display: "flex", flexDirection: "column", fontSize: 74, lineHeight: 1.02, fontWeight: 600, letterSpacing: -2 }}>
            <div>The Habesha community in the Emirates,</div>
            <div style={{ color: "#9c6a00", fontStyle: "italic" }}>in one place.</div>
          </div>
          <div style={{ marginTop: 28, fontSize: 28, color: "#586078", fontFamily: "Arial, Helvetica, sans-serif" }}>
            Jobs checked for scam signs · Habesha places · news and events · the goods board
          </div>
        </div>

        {/* The tibeb thread along the foot. */}
        <div style={{ position: "absolute", left: 0, right: 0, bottom: 0, height: 22, background: "#1b4ddb", display: "flex", overflow: "hidden" }}>
          <div style={{ position: "absolute", left: 0, right: 0, top: 2, height: 2, background: "#f2b705" }} />
          <div style={{ position: "absolute", left: 0, right: 0, bottom: 2, height: 2, background: "#f2b705" }} />
          {Array.from({ length: 30 }, (_, i) => diamond(10 + i * 40, 6, 10, i % 3 === 1 ? "#ffffff" : "#f2b705", i % 3 === 2 ? 0.9 : 1))}
        </div>
      </div>
    ),
    size,
  );
}
