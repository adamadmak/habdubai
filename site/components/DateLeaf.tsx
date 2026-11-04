import { dayAndMonth } from "@/lib/format";
import type { Locale } from "@/lib/locales";

/**
 * A calendar leaf in gold: the day large, the month above it. On its own, with
 * no dictionaries attached, so the goods form's live preview can show one too;
 * before a date is picked it is an empty leaf.
 */
export function DateLeaf({ date, lang, size = "md", className = "" }: { date: string | null; lang: Locale; size?: "md" | "lg"; className?: string }) {
  const { day, month } = date ? dayAndMonth(date, lang) : { day: "–", month: " " };
  return (
    <div className={`flex shrink-0 flex-col overflow-hidden rounded-2xl border border-accent/40 bg-elevated text-center shadow-[0_6px_18px_-12px_rgb(133_95_0/0.6)] ${size === "lg" ? "w-20" : "w-14"} ${className}`}>
      <span className="bg-accent py-0.5 text-[0.6875rem] font-bold text-[#191919]">{month}</span>
      <span className={`headline py-1 tabular ${size === "lg" ? "text-[2.25rem]" : "text-[1.375rem]"} ${date ? "" : "text-faint"}`}>{day}</span>
    </div>
  );
}
