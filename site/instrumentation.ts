/**
 * The API's clock. The host has no cron, and the API's scheduler runs when
 * POST /api/system/tick is called; this long-running server calls it once a
 * minute, from the moment it starts, with nothing to set up. TICK_URL points it
 * elsewhere and TICK_TOKEN answers a token saved on the admin's Settings; both
 * are optional. Missed ticks are harmless: the scheduler catches up on the next.
 */
export async function register() {
  if (process.env.NEXT_RUNTIME !== "nodejs") return;

  const api = (process.env.API_URL || "").replace(/\/+$/, "");
  // By itself only against a real deployment: on a developer's machine the local database
  // can hold the live bot token and real members, and a tick there would message them.
  const deployed = process.env.NODE_ENV === "production" && api.startsWith("https://");
  const url = process.env.TICK_URL || (deployed ? `${api}/system/tick` : "");
  if (!url) return;

  const token = process.env.TICK_TOKEN;
  const state = globalThis as typeof globalThis & { __habeshaTick?: ReturnType<typeof setInterval> };
  if (state.__habeshaTick) return;

  const tick = () =>
    fetch(url, {
      method: "POST",
      headers: { Accept: "application/json", ...(token ? { "X-Tick-Token": token } : {}) },
      cache: "no-store",
      signal: AbortSignal.timeout(110_000),
    })
      .then((response) => {
        // 409: the previous tick is still working. 429: asked too soon. Neither loses anything.
        if (!response.ok && response.status !== 409 && response.status !== 429) console.warn(`[tick] the API answered ${response.status}`);
        // Nothing here reads the answer; leaving it would hold the connection until the timeout.
        void response.body?.cancel();
      })
      .catch((error: unknown) => console.warn(`[tick] ${error instanceof Error ? error.message : "failed"}`));

  setTimeout(tick, 20_000);
  state.__habeshaTick = setInterval(tick, 60_000);
}
