import { am } from "@/i18n/am";
import { en, type Dictionary } from "@/i18n/en";
import { om } from "@/i18n/om";
import { ti } from "@/i18n/ti";
import type { Locale } from "./locales";

export * from "./locales";
export { fill } from "./fill";
export { label, pick, pickLang, stripEmoji } from "./labels";
export type { Dictionary };

const DICTIONARIES: Record<Locale, Dictionary> = { am, ti, om, en };

export function dict(locale: Locale): Dictionary {
  return DICTIONARIES[locale] ?? en;
}
