import "../assets/scss/globals.scss";
import { Fraunces } from "next/font/google";
import { siteConfig } from "@/config/site";
import Providers from "@/provider/providers";
import "simplebar-react/dist/simplebar.min.css";
import TanstackProvider from "@/provider/providers.client";
import AuthProvider from "@/provider/auth.provider";
import "flatpickr/dist/themes/light.css";
import DirectionProvider from "@/provider/direction.provider";
import { apiUrl } from "@/lib/api-url";

// Every page is rendered per request: the API address below is the running
// server's, never one frozen into the page when it was built.
export const dynamic = "force-dynamic";

// Page titles only (`.font-display`); everything else is Inter, set on <body> in Providers.
const fraunces = Fraunces({ subsets: ["latin"], variable: "--font-display", display: "swap" });

export const metadata = {
  title: {
    default: siteConfig.name,
    template: `%s · ${siteConfig.name}`,
  },
  description: siteConfig.description,
  robots: { index: false, follow: false },
};

export const viewport = { themeColor: "#0A1433" };

export default function RootLayout({ children, params: { lang } }: { children: React.ReactNode; params: { lang: string } }) {
  return (
    <html lang={lang} className={fraunces.variable} suppressHydrationWarning>
      <head>
        {/* The browser's API calls go where the server's do; see lib/api-url.ts. */}
        <script dangerouslySetInnerHTML={{ __html: `window.__API_URL__=${JSON.stringify(apiUrl()).replace(/</g, "\\u003c")}` }} />
      </head>
      <AuthProvider>
        <TanstackProvider>
          <Providers>
            <DirectionProvider lang={lang}>{children}</DirectionProvider>
          </Providers>
        </TanstackProvider>
      </AuthProvider>
    </html>
  );
}
