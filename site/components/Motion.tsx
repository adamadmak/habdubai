"use client";
import { useEffect } from "react";

/**
 * Two small behaviours for the whole site, set up once after hydration:
 *
 * - Sections marked `.reveal` rise into place the first time they scroll into
 *   view. Anything already on screen is marked shown before the hiding rule
 *   switches on, so nothing visible ever blinks; without JavaScript nothing is
 *   hidden at all.
 * - Tiles marked `.spot` carry a soft light under the pointer (CSS in globals).
 */
export function Motion() {
  useEffect(() => {
    const root = document.documentElement;
    if (!("IntersectionObserver" in window)) return;

    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.setAttribute("data-in", "");
            io.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -6% 0px", threshold: 0.06 },
    );

    const scan = () => {
      document.querySelectorAll<HTMLElement>(".reveal:not([data-seen])").forEach((el) => {
        el.setAttribute("data-seen", "");
        if (el.getBoundingClientRect().top < window.innerHeight * 0.94) el.setAttribute("data-in", "");
        else io.observe(el);
      });
    };

    scan();
    root.setAttribute("data-reveal", "");
    // Pages swapped in by client navigation bring new sections with them.
    const mo = new MutationObserver(scan);
    mo.observe(document.body, { childList: true, subtree: true });

    const move = (event: PointerEvent) => {
      const tile = (event.target as Element | null)?.closest?.<HTMLElement>(".spot");
      if (!tile) return;
      const box = tile.getBoundingClientRect();
      tile.style.setProperty("--mx", `${event.clientX - box.left}px`);
      tile.style.setProperty("--my", `${event.clientY - box.top}px`);
    };
    document.addEventListener("pointermove", move, { passive: true });

    return () => {
      io.disconnect();
      mo.disconnect();
      document.removeEventListener("pointermove", move);
    };
  }, []);

  return null;
}
