/**
 * Where the Laravel API lives, read on the server at request time.
 *
 * A NEXT_PUBLIC_ value is fixed when the admin is built, so a build that did not
 * see it points the browser at localhost for good; that took the dashboard down
 * once. The server reads the address as it answers and hands it to the browser
 * through the root layout (window.__API_URL__), so it can never be stale.
 */
export function apiUrl(): string {
  return (process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "");
}
