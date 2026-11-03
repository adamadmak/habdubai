"use client";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { Camera, CircleCheck, Hourglass } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Labels = {
  label: string;
  hint: string;
  pick: string;
  replace: string;
  uploading: string;
  sent: string;
  tooBig: string;
  inReview: string;
  network: string;
  server: string;
  sessionExpired: string;
  signInAgain: string;
};

type State = "idle" | "uploading" | "sent" | "error" | "signin";

/** What the API takes; the photo route refuses anything larger before reading it. */
const MAX_BYTES = 8 * 1024 * 1024;

/**
 * A cover photo: shrunk on the phone before it is sent (faster on mobile
 * data, and the photo comes out the right way up), then re-encoded and
 * reviewed on the server. A failure says which kind it was: too big, no
 * connection, the server, or a session that ran out.
 */
export function PhotoUpload({ id, lang, live, pending, labels }: { id: number; lang: Locale; live: string | null; pending: string | null; labels: Labels }) {
  const router = useRouter();
  const pathname = usePathname();
  const input = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(pending);
  const [state, setState] = useState<State>(pending ? "sent" : "idle");
  const [message, setMessage] = useState<string>("");

  async function shrink(file: File): Promise<Blob> {
    try {
      const bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
      const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(bitmap.width * scale);
      canvas.height = Math.round(bitmap.height * scale);
      canvas.getContext("2d")!.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      return await new Promise<Blob>((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error("encode"))), "image/jpeg", 0.85));
    } catch {
      return file;
    }
  }

  /** Back to the photo that was there before, with the reason this one did not go. */
  function fail(next: State, text: string, before: string | null, objectUrl: string) {
    URL.revokeObjectURL(objectUrl);
    setPreview(before);
    setState(next);
    setMessage(text);
  }

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
    const before = preview;
    const blob = await shrink(file);
    if (blob.size > MAX_BYTES) {
      setState("error");
      setMessage(labels.tooBig);
      return;
    }
    const objectUrl = URL.createObjectURL(blob);
    setPreview(objectUrl);

    const form = new FormData();
    form.append("photo", blob, "photo.jpg");
    form.append("lang", lang);

    let response: Response;
    try {
      response = await fetch(`/api/owner/photo/${id}`, { method: "POST", body: form });
    } catch {
      fail("error", labels.network, before, objectUrl);
      return;
    }

    if (response.ok) {
      setState("sent");
      router.refresh();
      return;
    }

    const data = await response.json().catch(() => ({}));
    if (response.status === 401) fail("signin", "", before, objectUrl);
    else if (response.status === 413) fail("error", labels.tooBig, before, objectUrl);
    else if (response.status >= 500) fail("error", labels.server, before, objectUrl);
    // The route's and the API's own words, already in this page's language (a photo too small, a listing gone).
    else fail("error", data?.errors?.photo?.[0] ?? data?.message ?? labels.server, before, objectUrl);
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
        <span className="min-w-0 flex-1 text-sm" role="status" aria-live="polite">
          {state === "uploading" && <span className="text-muted">{labels.uploading}</span>}
          {state === "sent" && <span className="flex items-center gap-1.5 text-success"><Hourglass className="size-4" aria-hidden="true" /> {labels.inReview}</span>}
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
