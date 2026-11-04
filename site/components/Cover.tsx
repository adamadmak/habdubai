import type { CSSProperties } from "react";
import { BusinessIcon, TradeIcon } from "./CategoryIcon";

/** Each kind of place gets its own two colours, so a row of places never looks like one tile repeated. */
const PLACE_HUES: Record<string, [string, string]> = {
  restaurant: ["#9a3412", "#f97316"],
  cafe: ["#4a2511", "#b0612c"],
  grocery: ["#14532d", "#22a356"],
  salon: ["#9d174d", "#ec6aa6"],
  cargo: ["#134e4a", "#14b8a6"],
  travel: ["#0c4a6e", "#0ea5e9"],
  typing: ["#312e81", "#6366f1"],
  clinic: ["#155e75", "#22b8cf"],
  fashion: ["#581c87", "#a855f7"],
  events: ["#92400e", "#eaa404"],
  worship: ["#1e3a8a", "#4f7cf7"],
  education: ["#3730a3", "#8b5cf6"],
  services: ["#3f3f46", "#8a817a"],
  other: ["#191919", "#d40511"],
};

/** And each kind of work. */
const TRADE_HUES: Record<string, [string, string]> = {
  domestic: ["#9d174d", "#ec6aa6"],
  driver: ["#0c4a6e", "#0ea5e9"],
  security: ["#1f2937", "#64748b"],
  cleaning: ["#134e4a", "#14b8a6"],
  hospitality: ["#9a3412", "#f97316"],
  salon: ["#86198f", "#d946ef"],
  retail: ["#14532d", "#22a356"],
  warehouse: ["#92400e", "#eaa404"],
  nursing: ["#155e75", "#22b8cf"],
  office: ["#312e81", "#6366f1"],
  construction: ["#7c2d12", "#ea580c"],
  other: ["#191919", "#d40511"],
};

function style([a, b]: [string, string]): CSSProperties {
  return { "--cover-a": a, "--cover-b": b } as CSSProperties;
}

export const placeStyle = (category: string) => style(PLACE_HUES[category] ?? PLACE_HUES.other);
export const tradeStyle = (category: string) => style(TRADE_HUES[category] ?? TRADE_HUES.other);

/** The first letters of the first two words, in either script: "Bunna House" → "BH". */
export function initials(name: string): string {
  return name
    .split(/[\s\-·,&]+/u)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => Array.from(word)[0] ?? "")
    .join("")
    .toLocaleUpperCase();
}

/**
 * A place without a photo: its kind's colours, a woven pattern chosen by its
 * id, its initials set large, and the kind's icon. Every place looks like itself.
 */
export function Cover({ category, name, seed, className = "", big = false }: { category: string; name: string; seed: number; className?: string; big?: boolean }) {
  return (
    <div className={`cover cover-p${Math.abs(seed) % 3} ${className}`} style={placeStyle(category)}>
      <span className={`absolute grid place-items-center rounded-full bg-white/18 ring-1 ring-white/25 ${big ? "right-5 top-5 size-14" : "right-3 top-3 size-9"}`}>
        <BusinessIcon category={category} className={big ? "size-7" : "size-[1.125rem]"} />
      </span>
      <span aria-hidden="true" className={`monogram absolute ${big ? "bottom-4 left-6 text-[5.5rem] md:text-[7rem]" : "bottom-2.5 left-3.5 text-[3.25rem]"}`}>
        {initials(name)}
      </span>
    </div>
  );
}

/** A kind of place as a pale tile takes it: the kind's own colour, for `.kind-tile` to tint with. */
export const placeTint = (category: string) => ({ "--tint": (PLACE_HUES[category] ?? PLACE_HUES.other)[1] }) as CSSProperties;

/** A small square for a kind of work or of place, in its colours. */
export function KindBadge({ kind, category, className = "size-11 rounded-2xl", iconClass = "size-5" }: { kind: "trade" | "place"; category: string; className?: string; iconClass?: string }) {
  return (
    <span className={`cover cover-p1 grid shrink-0 place-items-center ${className}`} style={kind === "trade" ? tradeStyle(category) : placeStyle(category)}>
      {kind === "trade" ? <TradeIcon category={category} className={iconClass} /> : <BusinessIcon category={category} className={iconClass} />}
    </span>
  );
}
