"use client";
import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { Camera, CircleCheck, Hourglass } from "lucide-react";

type Labels = { label: string; hint: string; pick: string; replace: string; uploading: string; sent: string; tooBig: string; inReview: string };

/**
 * A cover photo: shrunk on the phone before it is sent (faster on mobile
 * data, and the photo comes out the right way up), then re-encoded and
 * reviewed on the server.
 */
export function PhotoUpload({ id, live, pending, labels }: { id: number; live: string | null; pending: string | null; labels: Labels }) {
  const router = useRouter();
  const input = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(pending);
  const [state, setState] = useState<"idle" | "uploading" | "sent" | "error">(pending ? "sent" : "idle");
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

  async function choose(file: File | undefined) {
    if (!file) return;
    if (file.size > 25 * 1024 * 1024) {
      setState("error");
      setMessage(labels.tooBig);
      return;
    }
    setState("uploading");
    setMessage("");
    const blob = await shrink(file);
    setPreview(URL.createObjectURL(blob));

    const form = new FormData();
    form.append("photo", blob, "photo.jpg");
    try {
      const response = await fetch(`/api/owner/photo/${id}`, { method: "POST", body: form });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        setState("error");
        setMessage(data?.errors?.photo?.[0] ?? data?.message ?? "");
        return;
      }
      setState("sent");
      router.refresh();
    } catch {
      setState("error");
    }
  }

  const shown = preview ?? live;

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-semibold">{labels.label}</p>
      <div className="relative overflow-hidden rounded-2xl border border-line bg-surface">
        {shown ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={shown} alt="" className="aspect-[16/9] w-full object-cover" />
        ) : (
          <div className="grid aspect-[16/9] place-items-center text-faint"><Camera className="size-10" aria-hidden="true" /></div>
        )}
        {state === "uploading" && <div className="waiting absolute inset-0 bg-bg/40" aria-hidden="true" />}
      </div>
      <p className="text-xs text-muted">{labels.hint}</p>
      <input ref={input} type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" className="sr-only" onChange={(e) => void choose(e.target.files?.[0])} />
      <div className="flex flex-wrap items-center gap-3">
        <button type="button" className="btn btn-line" disabled={state === "uploading"} onClick={() => input.current?.click()}>
          <Camera className="size-4" aria-hidden="true" /> {shown ? labels.replace : labels.pick}
        </button>
        <span className="text-sm" role="status" aria-live="polite">
          {state === "uploading" && <span className="text-muted">{labels.uploading}</span>}
          {state === "sent" && <span className="flex items-center gap-1.5 text-success"><Hourglass className="size-4" aria-hidden="true" /> {labels.inReview}</span>}
          {state === "error" && <span className="text-danger">{message || labels.tooBig}</span>}
          {state === "idle" && live && <span className="flex items-center gap-1.5 text-muted"><CircleCheck className="size-4" aria-hidden="true" /></span>}
        </span>
      </div>
    </div>
  );
}
