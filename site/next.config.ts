import type { NextConfig } from "next";

/*
 * Server actions check the browser's Origin against the Host the server sees.
 * Behind the host's TLS proxy those can differ, which would refuse every owner
 * save; the public address is allowed explicitly.
 */
const siteHost = (() => {
  try {
    return process.env.SITE_URL ? new URL(process.env.SITE_URL).host : null;
  } catch {
    return null;
  }
})();

/** Listing and announcement photos load from the API's own address. */
const apiOrigin = (() => {
  try {
    return process.env.API_URL ? new URL(process.env.API_URL).origin : null;
  } catch {
    return null;
  }
})();

/*
 * What a page may load: its own scripts and styles (Next inlines a few of
 * each), photos from the API, framed by nobody but Telegram's web client (where
 * the bot's buttons open it), forms only to itself.
 * Production only: the dev server's hot reload needs eval.
 */
const contentSecurityPolicy = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  `img-src 'self' data: blob:${apiOrigin ? ` ${apiOrigin}` : ""}`,
  "font-src 'self' data:",
  "connect-src 'self'",
  "frame-ancestors 'self' https://web.telegram.org",
  "base-uri 'self'",
  "form-action 'self'",
  "object-src 'none'",
].join("; ");

const config: NextConfig = {
  poweredByHeader: false,
  reactStrictMode: true,
  // A page's title and preview tags go in its head for every reader. Next otherwise streams them into
  // the body after the page for ordinary browsers, which adds a race to hydration for no gain here:
  // they come from answers that are cached anyway.
  htmlLimitedBots: /.*/,
  // Listing photos come from the API host; plain <img> with explicit sizes, no optimizer to configure.
  images: { unoptimized: true },
  experimental: {
    // A listing form with a long "about" text; photos go through their own route, not an action.
    serverActions: { bodySizeLimit: "1mb", ...(siteHost ? { allowedOrigins: [siteHost] } : {}) },
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
          ...(process.env.NODE_ENV === "production" ? [{ key: "Content-Security-Policy", value: contentSecurityPolicy }] : []),
        ],
      },
    ];
  },
};

export default config;
