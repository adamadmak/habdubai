"use client";
import { useState } from "react";
import { Check, Share2 } from "lucide-react";

/** The phone's own share sheet when there is one; otherwise copy the link. */
export function ShareButton({ title, url, labels }: { title: string; url: string; labels: { share: string; copied: string } }) {
  const [copied, setCopied] = useState(false);

  async function share() {
    if (typeof navigator !== "undefined" && navigator.share) {
      try {
        await navigator.share({ title, url });
        return;
      } catch {
        // Dismissed: nothing to do.
        return;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      window.prompt(labels.share, url);
    }
  }

  return (
    <button type="button" onClick={share} className="btn btn-quiet" aria-live="polite">
      {copied ? <Check className="size-4" aria-hidden="true" /> : <Share2 className="size-4" aria-hidden="true" />}
      {copied ? labels.copied : labels.share}
    </button>
  );
}
