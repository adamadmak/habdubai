/**
 * The website opened inside Telegram, from a button in the bot. Telegram puts
 * what it signed for this person in the address's fragment, which no server
 * sees on its own. This reads it once, before anything else on the page runs,
 * and talks to the app around the page with the same small messages Telegram's
 * own script sends, so no third-party script loads for anyone.
 */
type Launch = { initData: string };

const SESSION_FLAG = "in-telegram";

/**
 * Telegram's apps put a bridge in the page, and its web client holds the page in a
 * frame. A plain browser tab has neither: launch data there came in a link someone
 * sent, and signing in with it would put the reader in the sender's account.
 */
function insideTelegramApp(): boolean {
  const w = window as unknown as { TelegramWebviewProxy?: unknown; external?: object };
  return Boolean(w.TelegramWebviewProxy) || Boolean(w.external && "notify" in w.external);
}

function read(): Launch | null {
  if (typeof window === "undefined" || !window.location.hash.includes("tgWebAppData=")) return null;
  if (!insideTelegramApp() && window.parent === window) return null;
  const initData = new URLSearchParams(window.location.hash.slice(1)).get("tgWebAppData");

  return initData ? { initData } : null;
}

const launch = read();

/** Inside Telegram's web client the page sits in a frame, where the session cookie cannot be kept. */
const inFrame = () => window.parent !== window;

export function insideTelegram(): boolean {
  if (typeof window === "undefined") return false;
  if (launch) return true;
  try {
    return sessionStorage.getItem(SESSION_FLAG) === "1";
  } catch {
    return false;
  }
}

/** Remembered for this visit: the fragment is gone after the first page. */
export function rememberInsideTelegram(): void {
  try {
    sessionStorage.setItem(SESSION_FLAG, "1");
  } catch {
    // Private browsing: this page still knows; the next one will not.
  }
}

/**
 * The signed data leaves the address bar at once, so a copied or shared link never
 * carries it. With no state of ours: Next keeps its router in step with a replaceState
 * only when the state is not its own, and otherwise its next refresh writes the old address back.
 */
export function clearLaunchFromAddress(): void {
  if (launch && window.location.hash) window.history.replaceState(null, "", window.location.pathname + window.location.search);
}

/** A message to the Telegram app around this page: each kind of client listens in its own way. */
export function tellTelegram(type: string, data: Record<string, unknown> = {}): void {
  try {
    const w = window as unknown as {
      TelegramWebviewProxy?: { postEvent: (type: string, body: string) => void };
      external?: { notify?: (body: string) => void };
    };
    if (w.TelegramWebviewProxy?.postEvent) w.TelegramWebviewProxy.postEvent(type, JSON.stringify(data));
    else if (w.external && "notify" in w.external && w.external.notify) w.external.notify(JSON.stringify({ eventType: type, eventData: data }));
    else if (inFrame()) window.parent.postMessage(JSON.stringify({ eventType: type, eventData: data }), "https://web.telegram.org");
  } catch {
    // An older app that does not know this message: nothing depends on it.
  }
}

let signingIn: Promise<string | null> | null = null;

/**
 * Sign in with what Telegram signed: once per page load, whoever asks first.
 * Resolves to approved (now signed in), same (already was), or anything else
 * when the ordinary sign-in is still the way; null when this is no launch.
 */
export function telegramSignIn(lang: string): Promise<string | null> {
  if (!launch || inFrame()) return Promise.resolve(null);

  signingIn ??= fetch("/api/auth/webapp", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ init_data: launch.initData, lang }),
  })
    .then((response) => response.json())
    .then((data: { status?: string }) => data.status ?? "failed")
    .catch(() => "failed");

  return signingIn;
}
