"use client";
import { useEffect } from "react";

/** While a form holds unsaved changes, closing the tab or reloading asks first. */
export function useLeaveGuard(dirty: boolean): void {
  useEffect(() => {
    if (!dirty) return;
    const warn = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = "";
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);
}

/** A click outside a dialog or Esc closes it; with typed changes in it, ask first. */
export function confirmDiscard(dirty: boolean): boolean {
  return !dirty || window.confirm("Close without saving? What you changed here will be lost.");
}
