import type { NextConfig } from "next";

const config: NextConfig = {
  poweredByHeader: false,
  reactStrictMode: true,
  // Listing photos come from the API host; plain <img> with explicit sizes, no optimizer to configure.
  images: { unoptimized: true },
  experimental: {
    // A listing form with a long "about" text; photos go through their own route, not an action.
    serverActions: { bodySizeLimit: "1mb" },
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
        ],
      },
    ];
  },
};

export default config;
