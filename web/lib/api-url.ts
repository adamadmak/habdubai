/**
 * Where the Laravel API lives, read on the server at request time.
 *
 * The host builds the admin without its environment and only hands the
 * variables to the running container, so a NEXT_PUBLIC_ value inlined at build
 * time would be the localhost fallback forever. The server reads it live and
 * passes it to the browser through the root layout (window.__API_URL__).
 */
export function apiUrl(): string {
  return (process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "");
}
