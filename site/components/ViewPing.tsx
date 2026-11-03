"use client";
import { useEffect } from "react";

/** Counts a visit to a business page for its owner. The page itself is cached, so the browser reports the visit. */
export function ViewPing({ slug }: { slug: string }) {
  useEffect(() => {
    const url = `/api/views/${slug}`;
    if (!navigator.sendBeacon?.(url)) {
      void fetch(url, { method: "POST", keepalive: true }).catch(() => undefined);
    }
  }, [slug]);

  return null;
}
