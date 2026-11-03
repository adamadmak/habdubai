"use client";
import React, { useEffect, useRef } from "react";
import { usePathname } from "next/navigation";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";
import { useSidebar } from "@/store";
import { ScrollArea } from "@/components/ui/scroll-area";
import { SidebarBrand, SidebarNav } from "../classic";

/**
 * The same navigation as a drawer, below 1280 px. Opening it focuses the close
 * button, keeps the keyboard inside and stops the page behind from scrolling;
 * closing it (navigation, backdrop, Escape, the button) gives focus back to
 * whatever opened it.
 */
const MobileSidebar = ({ className }: { className?: string; trans?: unknown }) => {
  const { mobileMenu, setMobileMenu } = useSidebar();
  const location = usePathname();
  const drawer = useRef<HTMLElement>(null);
  const closeButton = useRef<HTMLButtonElement>(null);
  const opener = useRef<HTMLElement | null>(null);

  useEffect(() => {
    setMobileMenu(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location]);

  useEffect(() => {
    if (!mobileMenu) {
      // Back to the menu button (or whatever had focus), if it is still on the page.
      const target = opener.current;
      opener.current = null;
      if (target && document.contains(target)) target.focus();
      return;
    }

    opener.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeButton.current?.focus();

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMobileMenu(false);
        return;
      }
      if (event.key !== "Tab" || !drawer.current) return;
      // Tab moves through the drawer's own controls and wraps, so focus never reaches the page behind.
      const focusable = Array.from(drawer.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'));
      if (focusable.length === 0) return;
      event.preventDefault();
      const index = focusable.indexOf(document.activeElement as HTMLElement);
      const step = event.shiftKey ? -1 : 1;
      focusable[(index + step + focusable.length) % focusable.length].focus();
    };
    // Anything that still lands outside (a click on the backdrop, a programmatic focus) comes back in.
    const onFocus = (event: FocusEvent) => {
      if (drawer.current && event.target instanceof Node && !drawer.current.contains(event.target)) closeButton.current?.focus();
    };
    document.addEventListener("focusin", onFocus);
    window.addEventListener("keydown", onKey);
    return () => {
      window.removeEventListener("keydown", onKey);
      document.removeEventListener("focusin", onFocus);
      document.body.style.overflow = previousOverflow;
    };
  }, [mobileMenu, setMobileMenu]);

  return (
    <>
      <aside
        ref={drawer}
        role="dialog"
        aria-modal="true"
        aria-label="Admin pages"
        aria-hidden={!mobileMenu}
        className={cn(
          "fixed top-0 z-[9999] flex h-full w-[272px] flex-col text-white transition-[transform,opacity,visibility] duration-200 xl:hidden",
          "bg-[linear-gradient(180deg,hsl(225_67%_13%)_0%,hsl(226_70%_9%)_100%)]",
          mobileMenu ? "left-0 translate-x-0 visible opacity-100" : "left-0 -translate-x-full invisible opacity-0",
          className
        )}
      >
        <div className="flex items-center gap-2 px-5 pt-5 pb-4">
          <div className="min-w-0 flex-1"><SidebarBrand /></div>
          <button ref={closeButton} type="button" onClick={() => setMobileMenu(false)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-white/70 hover:bg-white/10 focus-visible:outline-gold" aria-label="Close the menu">
            <X className="h-4 w-4" />
          </button>
        </div>
        <div className="tibeb-rule mx-5 mb-2 opacity-70" aria-hidden="true" />
        <ScrollArea className="flex-1">
          <SidebarNav onNavigate={() => setMobileMenu(false)} />
        </ScrollArea>
      </aside>
      {mobileMenu && <div onClick={() => setMobileMenu(false)} className="fixed inset-0 z-[999] bg-navy/50 backdrop-blur-sm xl:hidden" aria-hidden="true" />}
    </>
  );
};

export default MobileSidebar;
