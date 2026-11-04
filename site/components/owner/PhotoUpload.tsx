"use client";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { Camera, CircleCheck, Hourglass, RotateCcw, X } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Labels = {
  label: string;
  hint: string;
  pick: string;
  replace: string;
  uploading: string;
  sent: string;
  tooBig: string;
  held: string;
  remove: string;
  retry: string;
  inReview: string;
  network: string;
  server: string;
  sessionExpired: string;
  signInAgain: string;
};

type State = "idle" | "uploading" | "sent" | "held" | "error" | "signin";

/** What the API takes; the photo route refuses anything larger before reading it. */
const MAX_BYTES = 8 * 1024 * 1024;

/**
 * A photo chosen on the new-listing form, before the listing exists to attach
 * it to. It waits here, in this tab's memory, and the listing's own page sends
 * it right after the save takes the owner there, and only then: Back to an older
 * listing's page must not hand it a photo chosen for the next one.
 */
let heldPhoto: Blob | null = null;
let goesWithSave = false;

/** The new-listing form is being sent: a chosen photo goes up on the page it leads to. */
export function sendPhotoWithThisSave(): void {
  goesWithSave = heldPhoto !== null;
}

/** The save did not go through: a chosen photo stays on the form. */
export function keepPhotoHere(): void {
  goesWithSave = false;
}

async function shrink(file: File): Promise<Blob> {
  try {
    const bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext("2d")!;
    // A JPEG has no transparency: a logo sits on white, as the server would put it, not on black.
    context.fillStyle = "#ffffff";
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    return await new Promise<Blob>((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error("encode"))), "image/jpeg", 0.85));
  } catch {
    return file;
  }
}

/**
 * A cover photo: shrunk on the phone before it is sent (faster on mobile
 * data, and the photo comes out the right way up), then re-encoded and
 * reviewed on the server. A failure says which kind it was: too big, no
 * connection, the server, or a session that ran out; and the same photo can be
 * tried again without choosing it again.
 *
 * With no id yet (the new-listing form) the photo waits for the save; with
 * `sendHeld` (the listing's page right after that save) it goes up by itself.
 */
export function PhotoUpload({ id, lang, live = null, pending = null, sendHeld = false, labels }: {
  id?: number;
  lang: Locale;
  live?: string | null;
  pending?: string | null;
  sendHeld?: boolean;
  labels: Labels;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const input = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(pending);
  const [state, setState] = useState<State>(pending ? "sent" : "idle");
  const [message, setMessage] = useState<string>("");
  // The object URL on screen, released when it is replaced or the page goes.
  const shownUrl = useRef<string | null>(null);
  // The photo that did not go, for trying again.
  const failed = useRef<{ blob: Blob; before: string | null } | null>(null);

  useEffect(
    () => () => {
      if (shownUrl.current) URL.revokeObjectURL(shownUrl.current);
      shownUrl.current = null;
    },
    [],
  );

  const send = useCallback(
    async (listing: number, blob: Blob, before: string | null) => {
      setState("uploading");
      setMessage("");
      failed.current = null;

      const previousUrl = shownUrl.current;
      const url = URL.createObjectURL(blob);
      shownUrl.current = url;
      setPreview(url);

      /** Back to the photo that was there before, with the reason this one did not go. */
      const fail = (next: State, text: string) => {
        URL.revokeObjectURL(url);
        shownUrl.current = previousUrl;
        failed.current = { blob, before };
        setPreview(before);
        setState(next);
        setMessage(text);
      };

      const form = new FormData();
      form.append("photo", blob, "photo.jpg");
      form.append("lang", lang);

      let response: Response;
      try {
        response = await fetch(`/api/owner/photo/${listing}`, { method: "POST", body: form });
      } catch {
        fail("error", labels.network);
        return;
      }

      if (response.ok) {
        if (previousUrl) URL.revokeObjectURL(previousUrl);
        setState("sent");
        router.refresh();
        return;
      }

      const data = await response.json().catch(() => ({}));
      if (response.status === 401) fail("signin", "");
      else if (response.status === 413) fail("error", labels.tooBig);
      else if (response.status >= 500) fail("error", labels.server);
      // The route's and the API's own words, already in this page's language (a photo too small, a listing gone).
      else fail("error", data?.errors?.photo?.[0] ?? data?.message ?? labels.server);
    },
    [labels.network, labels.server, labels.tooBig, lang, router],
  );

  // Once only: a reload of the page finds nothing held, and strict mode's second run neither.
  const started = useRef(false);
  useEffect(() => {
    if (started.current || !heldPhoto) return;
    started.current = true;
    if (id === undefined) {
      // Back on the form with a photo already chosen: shown, so it can never go up unnoticed.
      shownUrl.current = URL.createObjectURL(heldPhoto);
      setPreview(shownUrl.current);
      setState("held");
    } else if (sendHeld && goesWithSave) {
      const blob = heldPhoto;
      heldPhoto = null;
      goesWithSave = false;
      void send(id, blob, null);
    }
  }, [id, sendHeld, send]);

  async function choose(file: File | undefined) {
    if (!file) return;
    // Larger than any phone photo: refused before the work of shrinking it.
    if (file.size > 25 * 1024 * 1024) {
      setState("error");
      setMessage(labels.tooBig);
      return;
    }
    setState("uploading");
    setMessage("");
    const blob = await shrink(file);
    if (blob.size > MAX_BYTES) {
      setState("error");
      setMessage(labels.tooBig);
      return;
    }
    if (id === undefined) {
      heldPhoto = blob;
      if (shownUrl.current) URL.revokeObjectURL(shownUrl.current);
      shownUrl.current = URL.createObjectURL(blob);
      setPreview(shownUrl.current);
      setState("held");
      return;
    }
    await send(id, blob, preview);
  }

  function dropHeld() {
    heldPhoto = null;
    if (shownUrl.current) URL.revokeObjectURL(shownUrl.current);
    shownUrl.current = null;
    setPreview(null);
    setMessage("");
    setState("idle");
  }

  const shown = preview ?? live;

  return (
    <div className="flex flex-col gap-3">
      <label htmlFor="photo-file" className="text-sm font-semibold">{labels.label}</label>
      <div className="relative overflow-hidden rounded-2xl border border-line bg-surface">
        {shown ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={shown} alt="" className="aspect-[16/9] w-full object-cover" />
        ) : (
          <div className="grid aspect-[16/9] place-items-center text-faint"><Camera className="size-10" aria-hidden="true" /></div>
        )}
        {state === "uploading" && <div className="waiting absolute inset-0 bg-bg/40" aria-hidden="true" />}
      </div>
      <p id="photo-hint" className="text-xs text-muted">{labels.hint}</p>
      {/* Reached through the button below; out of the tab order so there is one stop, not two. */}
      <input
        ref={input}
        id="photo-file"
        type="file"
        accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
        className="sr-only"
        tabIndex={-1}
        aria-describedby="photo-hint"
        onChange={(e) => {
          void choose(e.target.files?.[0]);
          e.target.value = "";
        }}
      />
      <div className="flex flex-wrap items-center gap-3">
        <button type="button" className="btn btn-line" disabled={state === "uploading"} onClick={() => input.current?.click()}>
          <Camera className="size-4" aria-hidden="true" /> {shown ? labels.replace : labels.pick}
        </button>
        {id === undefined && preview !== null && state !== "uploading" && (
          <button type="button" className="btn btn-quiet" onClick={dropHeld}>
            <X className="size-4" aria-hidden="true" /> {labels.remove}
          </button>
        )}
        {id !== undefined && state === "error" && failed.current && (
          <button type="button" className="btn btn-quiet" onClick={() => failed.current && void send(id, failed.current.blob, failed.current.before)}>
            <RotateCcw className="size-4" aria-hidden="true" /> {labels.retry}
          </button>
        )}
        <span className="min-w-[10rem] flex-1 text-sm" role="status" aria-live="polite">
          {state === "uploading" && <span className="text-muted">{labels.uploading}</span>}
          {state === "held" && <span className="flex items-center gap-1.5 text-muted"><CircleCheck className="size-4 shrink-0 text-success" aria-hidden="true" /> {labels.held}</span>}
          {state === "sent" && <span className="flex items-center gap-1.5 text-success"><Hourglass className="size-4 shrink-0" aria-hidden="true" /> {labels.inReview}</span>}
          {state === "error" && <span className="text-danger">{message || labels.server}</span>}
          {state === "signin" && (
            <span className="text-danger">
              {labels.sessionExpired}{" "}
              <Link href={`/${lang}/business/login?next=${encodeURIComponent(pathname)}`} className="font-semibold text-primary underline underline-offset-2">
                {labels.signInAgain}
              </Link>
            </span>
          )}
          {state === "idle" && live && <span className="flex items-center gap-1.5 text-muted"><CircleCheck className="size-4" aria-hidden="true" /></span>}
        </span>
      </div>
    </div>
  );
}
