"use client";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import QRCode from "qrcode";
import { CircleAlert, CircleCheck, KeyRound, Send, ShieldCheck, Smartphone } from "lucide-react";
import type { Locale } from "@/lib/locales";
import { telegramSignIn } from "@/lib/telegram-app";

type Phase = "starting" | "waiting" | "code" | "approved" | "expired" | "declined" | "locked" | "unavailable" | "busy" | "framed";

type Labels = {
  phone: string;
  desktop: string;
  open: string;
  waiting: string;
  opening: string;
  framed: string;
  openTab: string;
  codeTitle: string;
  codeHint: string;
  codeSubmit: string;
  codeWrong: string;
  locked: string;
  busy: string;
  expired: string;
  declined: string;
  retry: string;
  done: string;
  unavailable: string;
  privacy: string;
  network: string;
};

/**
 * "Continue with Telegram": a QR code on a computer, a big button on a
 * phone. The page asks every two seconds whether the person said yes in
 * Telegram (and right away when they come back from the Telegram app), then
 * asks for the six-digit code the bot sent them. The code goes from Telegram
 * to this page, never the other way, so a sign-in link someone else started
 * and forwarded gives them nothing.
 *
 * Every request carries the page's language, so the API answers in it.
 */
export function LoginPanel({ lang, next, labels }: { lang: Locale; next: string; labels: Labels }) {
  const router = useRouter();
  const [phase, setPhase] = useState<Phase>("starting");
  const [link, setLink] = useState<string | null>(null);
  const [qr, setQr] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const expires = useRef<number>(0);
  const busy = useRef(false);
  const codeInput = useRef<HTMLInputElement>(null);

  const start = useCallback(async () => {
    setPhase("starting");
    setQr(null);
    setCode("");
    setError(null);
    try {
      const response = await fetch("/api/auth/start", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ lang }) });
      if (response.status === 429) {
        setPhase("busy");
        return;
      }
      if (!response.ok) throw new Error("unavailable");
      const data: { link: string; expires_at: string } = await response.json();
      setLink(data.link);
      expires.current = new Date(data.expires_at).getTime();
      setQr(await QRCode.toDataURL(data.link, { margin: 1, width: 440, errorCorrectionLevel: "M", color: { dark: "#111418", light: "#ffffff" } }));
      setPhase("waiting");
    } catch {
      setPhase("unavailable");
    }
  }, [lang]);

  const poll = useCallback(async () => {
    if (busy.current) return;
    if (expires.current && Date.now() > expires.current) {
      setPhase("expired");
      return;
    }
    busy.current = true;
    try {
      const response = await fetch("/api/auth/poll", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ lang }) });
      const data: { status: string } = await response.json();
      if (data.status === "code") setPhase("code");
      else if (data.status === "declined" || data.status === "expired") setPhase(data.status);
    } catch {
      // A dropped request: the next tick asks again.
    } finally {
      busy.current = false;
    }
  }, [lang]);

  const verify = async (event: FormEvent) => {
    event.preventDefault();
    if (code.length !== 6 || sending) return;
    setSending(true);
    setError(null);
    try {
      const response = await fetch("/api/auth/verify", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ code, lang }) });
      const data: { status: string; attempts_left?: number } = await response.json();
      if (data.status === "approved") {
        setPhase("approved");
        router.replace(next);
        router.refresh();
      } else if (data.status === "wrong") {
        setError(labels.codeWrong.replace("{n}", String(data.attempts_left ?? 0)));
        setCode("");
        codeInput.current?.focus();
      } else if (data.status === "busy") {
        // Too many tries this minute: the code still stands, so the form stays.
        setError(labels.busy);
        codeInput.current?.focus();
      } else if (data.status === "locked" || data.status === "expired") {
        setPhase(data.status);
      } else {
        setPhase("unavailable");
      }
    } catch {
      // No answer at all: keep the code on screen and say so, rather than starting over.
      setError(labels.network);
      codeInput.current?.focus();
    } finally {
      setSending(false);
    }
  };

  useEffect(() => {
    let live = true;
    // Inside Telegram's web client the page sits in a frame, where the browser keeps no cookie, so
    // no way of signing in can work; a tab of its own can. Starting one here would only say "expired".
    if (window.parent !== window) {
      setPhase("framed");
      return;
    }
    // Opened inside Telegram there is nothing to scan or type; everyone else starts the ordinary way.
    void telegramSignIn(lang).then((status) => {
      if (!live) return;
      if (status === "approved" || status === "same") {
        setPhase("approved");
        router.replace(next);
        router.refresh();
      } else if (status === "blocked") {
        // Telegram already said who this is, and the bot would only decline a code.
        setPhase("declined");
      } else {
        void start();
      }
    });
    return () => {
      live = false;
    };
  }, [start, lang, next, router]);

  useEffect(() => {
    if (phase !== "waiting") return;
    const timer = window.setInterval(poll, 2000);
    const back = () => document.visibilityState === "visible" && void poll();
    document.addEventListener("visibilitychange", back);
    window.addEventListener("focus", back);
    return () => {
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", back);
      window.removeEventListener("focus", back);
    };
  }, [phase, poll]);

  useEffect(() => {
    if (phase === "code") codeInput.current?.focus();
  }, [phase]);

  if (phase === "unavailable" || phase === "expired" || phase === "declined" || phase === "locked" || phase === "busy") {
    return (
      <div className="card p-6 text-center" role="alert">
        <CircleAlert className="mx-auto size-8 text-warning" aria-hidden="true" />
        <p className="mt-3 font-semibold">{labels[phase]}</p>
        <button type="button" onClick={() => void start()} className="btn btn-primary mt-5">{labels.retry}</button>
      </div>
    );
  }

  if (phase === "framed") {
    return (
      <div className="card p-6 text-center" role="status">
        <p className="font-semibold">{labels.framed}</p>
        <a href={window.location.href} target="_blank" rel="noopener noreferrer" className="btn btn-primary mt-5">{labels.openTab}</a>
      </div>
    );
  }

  if (phase === "approved") {
    return (
      <div className="card p-6 text-center" role="status">
        <CircleCheck className="mx-auto size-8 text-success" aria-hidden="true" />
        <p className="mt-3 font-semibold">{labels.done}</p>
      </div>
    );
  }

  if (phase === "code") {
    return (
      <form onSubmit={verify} className="card p-6">
        <KeyRound className="size-8 text-primary" aria-hidden="true" />
        <h2 className="title mt-3 text-xl">{labels.codeTitle}</h2>
        <p className="mt-1.5 text-sm text-muted">{labels.codeHint}</p>
        <label htmlFor="login-code" className="sr-only">{labels.codeTitle}</label>
        <input
          ref={codeInput}
          id="login-code"
          value={code}
          onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="\d{6}"
          maxLength={6}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? "login-code-error" : undefined}
          className="field mt-5 max-w-56 text-center font-mono text-3xl font-bold tracking-[0.35em] tabular"
          placeholder="••••••"
        />
        <div className="mt-5 flex flex-wrap items-center gap-3">
          <button type="submit" className="btn btn-primary w-full sm:w-auto" disabled={code.length !== 6 || sending}>{labels.codeSubmit}</button>
          {/* Always in the page, beside the button, so each new answer is heard. */}
          <p id="login-code-error" className="min-w-0 flex-1 text-sm text-danger" aria-live="assertive">{error}</p>
        </div>
      </form>
    );
  }

  return (
    <>
    {/* Opened from a button in the bot there is nothing to scan: Telegram already said who this is. Decided in CSS, so the first paint is right. */}
    {phase === "starting" && (
      <div className="telegram-only card p-6 text-center" role="status">
        <span className="waiting mx-auto block size-8 rounded-full bg-primary/25" aria-hidden="true" />
        <p className="mt-3 font-semibold">{labels.opening}</p>
      </div>
    )}
    <div className={`card overflow-hidden ${phase === "starting" ? "telegram-hidden" : ""}`}>
      <div className="grid gap-6 p-6 md:grid-cols-[auto_1fr] md:items-center">
        {/* On a computer: scan with the phone. On a phone: the button below does it. */}
        <div className="mx-auto hidden size-[13.75rem] place-items-center rounded-2xl border border-line bg-white p-2 md:grid">
          {qr ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={qr} alt="" className="size-full" />
          ) : (
            <div className="waiting size-full rounded-xl bg-surface" />
          )}
        </div>
        <div>
          <p className="flex items-start gap-2 text-sm text-muted md:hidden">
            <Smartphone className="mt-0.5 size-4 shrink-0" aria-hidden="true" /> {labels.phone}
          </p>
          <p className="hidden text-sm text-muted md:block">{labels.desktop}</p>
          <a href={link ?? "#"} target="_blank" rel="noopener noreferrer" aria-disabled={!link} className="btn btn-primary mt-4 w-full md:w-auto">
            <Send className="size-4" aria-hidden="true" /> {labels.open}
          </a>
        </div>
      </div>
      <div className="flex items-center gap-2 border-t border-line bg-surface px-6 py-3 text-sm text-muted" role="status">
        <span className="waiting inline-block size-2 rounded-full bg-primary" aria-hidden="true" />
        {labels.waiting}
      </div>
      <p className="flex items-center gap-2 px-6 py-3 text-xs text-faint">
        <ShieldCheck className="size-3.5 shrink-0" aria-hidden="true" /> {labels.privacy}
      </p>
    </div>
    </>
  );
}
