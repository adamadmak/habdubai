import { ImageResponse } from "next/og";

export const size = { width: 180, height: 180 };
export const contentType = "image/png";

/** The mark for an iPhone's home screen: a gold tibeb diamond on Nile blue, no rounding (iOS adds its own). */
export default function AppleIcon() {
  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", alignItems: "center", justifyContent: "center", background: "linear-gradient(135deg, #2a5cf0, #1336a6)" }}>
        <svg width="140" height="140" viewBox="0 0 32 32">
          <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="#f2b705" />
          <path d="M16 10 22 16 16 22 10 16Z" fill="#1b4ddb" />
          <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
        </svg>
      </div>
    ),
    size,
  );
}
