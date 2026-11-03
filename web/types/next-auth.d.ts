import "next-auth";
import "next-auth/jwt";

declare module "next-auth" {
  interface Session {
    accessToken: string;
    user: { id: number; name: string; email: string };
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    accessToken?: string;
    user?: { id: number; name: string; email: string };
    /** When the API token runs out (ms since the epoch); set at sign-in. */
    expiresAt?: number;
  }
}
