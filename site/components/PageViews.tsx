"use client";
import { usePathname } from "next/navigation";
import { useEffect } from "react";

/** The last path reported, so React's development double-run of effects does not count a page twice. */
let lastSent = "";

/**
 * Counts page views, per day and path only: no cookie, no id, no query
 * string. Pages are cached, so the browser reports each one (and each page
 * reached by client navigation) to app/api/pv. A browser asking not to be
 * tracked is not counted at all.
 */
export function PageViews() {
  const pathname = usePathname();

  useEffect(() => {
    const nav = navigator as Navigator & { globalPrivacyControl?: boolean };
    if (nav.doNotTrack === "1" || nav.globalPrivacyControl) return;
    if (!pathname || pathname === lastSent) return;
    lastSent = pathname;

    const body = JSON.stringify({ path: pathname });
    const sent = nav.sendBeacon?.("/api/pv", new Blob([body], { type: "application/json" }));
    if (!sent) {
      void fetch("/api/pv", { method: "POST", headers: { "Content-Type": "application/json" }, body, keepalive: true }).catch(() => undefined);
    }
  }, [pathname]);

  return null;
}
