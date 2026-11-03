"use client";
import { useEffect, useState } from "react";
import { Moon, Sun } from "lucide-react";

type Mode = "light" | "dark";

/** What the page shows right now: the reader's choice, else the device's. */
function current(): Mode {
  const chosen = document.documentElement.dataset.theme;
  if (chosen === "light" || chosen === "dark") return chosen;
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

/**
 * One dial in the header: the sun by day, the moon by night. A tap switches
 * the whole site and remembers it (the script in layout.tsx applies the
 * choice before the first paint, so there is never a flash). The server
 * renders the sun; the dial learns the real mode once it is on screen.
 */
export function ThemeToggle({ labels }: { labels: { light: string; dark: string } }) {
  const [mode, setMode] = useState<Mode>("light");

  useEffect(() => {
    setMode(current());
    // A device that changes its scheme while the page is open, when the reader has not chosen.
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const follow = () => {
      if (!document.documentElement.dataset.theme) setMode(media.matches ? "dark" : "light");
    };
    media.addEventListener("change", follow);
    return () => media.removeEventListener("change", follow);
  }, []);

  const toggle = () => {
    const next: Mode = current() === "dark" ? "light" : "dark";
    document.documentElement.dataset.theme = next;
    // The browser's own bar follows the device's scheme through its media query; a chosen theme overrides it here.
    document.querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]').forEach((meta) => {
      meta.content = next === "dark" ? "#070c1f" : "#fbf8f2";
    });
    try {
      localStorage.setItem("theme", next);
    } catch {
      // Private browsing: the choice lasts for this page only.
    }
    setMode(next);
  };

  // The label names what the tap will do, not what is shown.
  return (
    <button type="button" onClick={toggle} className="theme-dial press" data-mode={mode} aria-label={mode === "dark" ? labels.light : labels.dark} title={mode === "dark" ? labels.light : labels.dark}>
      <Sun className="sun size-[1.125rem]" aria-hidden="true" strokeWidth={1.9} />
      <Moon className="moon size-[1.125rem]" aria-hidden="true" strokeWidth={1.9} />
    </button>
  );
}
