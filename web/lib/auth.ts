import type { NextAuthOptions } from "next-auth";
import Credentials from "next-auth/providers/credentials";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

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

        const response = await fetch(`${API_URL}/api/admin/login`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email, password }),
        });

        if (!response.ok) return null;

        const data = (await response.json()) as { token: string; user: { id: number; name: string; email: string } };

        return { id: String(data.user.id), name: data.user.name, email: data.user.email, accessToken: data.token };
      },
    }),
  ],
  secret: process.env.NEXTAUTH_SECRET ?? process.env.AUTH_SECRET,
  session: { strategy: "jwt", maxAge: 60 * 60 * 24 * 14 },
  pages: { signIn: "/en/auth/login" },
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.accessToken = (user as { accessToken?: string }).accessToken;
        token.user = { id: Number(user.id), name: user.name ?? "", email: user.email ?? "" };
      }
      return token;
    },
    async session({ session, token }) {
      session.accessToken = token.accessToken as string;
      session.user = { ...session.user, ...(token.user as { id: number; name: string; email: string }) };
      return session;
    },
  },
};
