"use client";
import { createContext, useContext, type ReactNode } from "react";
import type { Locale } from "@/lib/locales";

/**
 * The few words the error and not-found pages need. Those pages have to be
 * client components, and importing the dictionaries there would send all
 * four languages to every visitor; the layout hands down just these.
 */
export type FallbackCopy = {
  lang: Locale;
  notFoundTitle: string;
  notFoundText: string;
  goHome: string;
  errorTitle: string;
  errorText: string;
  retry: string;
};

const Copy = createContext<FallbackCopy | null>(null);

export function FallbackCopyProvider({ copy, children }: { copy: FallbackCopy; children: ReactNode }) {
  return <Copy.Provider value={copy}>{children}</Copy.Provider>;
}

/** Null only outside the language layout, which never renders these pages. */
export function useFallbackCopy(): FallbackCopy | null {
  return useContext(Copy);
}
