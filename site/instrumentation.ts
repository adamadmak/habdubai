/**
 * The API's clock. The host has no cron, and the API's scheduler runs when
 * POST /api/system/tick is called; this long-running server calls it once a
 * minute when TICK_URL and TICK_TOKEN are set. Missed ticks are harmless:
 * the scheduler catches up on the next one.
 */
export async function register() {
  if (process.env.NEXT_RUNTIME !== "nodejs") return;

  const url = process.env.TICK_URL;
  const token = process.env.TICK_TOKEN;
  if (!url || !token) return;

  const state = globalThis as typeof globalThis & { __habeshaTick?: ReturnType<typeof setInterval> };
  if (state.__habeshaTick) return;

  const tick = () =>
    fetch(url, { method: "POST", headers: { "X-Tick-Token": token, Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(55_000) })
      .then((response) => {
        if (!response.ok) console.warn(`[tick] the API answered ${response.status}`);
      })
      .catch((error: unknown) => console.warn(`[tick] ${error instanceof Error ? error.message : "failed"}`));

  setTimeout(tick, 20_000);
  state.__habeshaTick = setInterval(tick, 60_000);
}
