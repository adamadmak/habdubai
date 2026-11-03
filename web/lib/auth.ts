import type { NextAuthOptions } from "next-auth";
import Credentials from "next-auth/providers/credentials";
import { apiUrl } from "@/lib/api-url";

/** How long an admin token lasts on the API, and so how long a dashboard session may. */
export const ADMIN_SESSION_SECONDS = 60 * 60 * 24 * 14;

/**
 * The dashboard signs in against the Laravel API. The Sanctum token the API
 * issues is kept inside next-auth's encrypted JWT cookie and handed to every
 * API call from the browser; the browser never sees it in clear.
 */
export const authOptions: NextAuthOptions = {
  providers: [
    Credentials({
      name: "credentials",
      credentials: {
        email: { label: "Email", type: "email" },
        password: { label: "Password", type: "password" },
      },
      async authorize(credentials) {
        const { email, password } = (credentials ?? {}) as { email?: string; password?: string };
        if (!email || !password) return null;

        let response: Response;
        try {
          response = await fetch(`${apiUrl()}/api/admin/login`, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ email, password }),
            signal: AbortSignal.timeout(15000),
          });
        } catch {
          throw new Error("UNAVAILABLE");
        }

        // next-auth hands a thrown message to the form as `error`; the form words it.
        if (response.status === 429) throw new Error("RATE_LIMITED");
        if (response.status >= 500) throw new Error("UNAVAILABLE");
        if (!response.ok) return null;

        const data = (await response.json()) as { token: string; user: { id: number; name: string; email: string } };

        return { id: String(data.user.id), name: data.user.name, email: data.user.email, accessToken: data.token };
      },
    }),
  ],
  secret: process.env.NEXTAUTH_SECRET ?? process.env.AUTH_SECRET,
  session: { strategy: "jwt", maxAge: ADMIN_SESSION_SECONDS },
  pages: { signIn: "/en/auth/login" },
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.accessToken = (user as { accessToken?: string }).accessToken;
        token.user = { id: Number(user.id), name: user.name ?? "", email: user.email ?? "" };
        // The cookie's own expiry rolls forward on use; the API token's does not.
        token.expiresAt = Date.now() + ADMIN_SESSION_SECONDS * 1000;
      }
      return token;
    },
    async session({ session, token }) {
      session.accessToken = token.accessToken as string;
      session.user = { ...session.user, ...(token.user as { id: number; name: string; email: string }) };
      return session;
    },
  },
  events: {
    // Signing out revokes the API token too, so a copied cookie stops working.
    // Best effort: an unreachable API never keeps an admin signed in.
    async signOut({ token }) {
      if (!token?.accessToken) return;
      try {
        await fetch(`${apiUrl()}/api/admin/logout`, {
          method: "POST",
          headers: { Accept: "application/json", Authorization: `Bearer ${token.accessToken}` },
          signal: AbortSignal.timeout(5000),
        });
      } catch {
        // The token expires on its own.
      }
    },
  },
};
