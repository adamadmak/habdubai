"use client";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Search } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Scope = "jobs" | "directory" | "news";
const SCOPES: Scope[] = ["jobs", "directory", "news"];

/**
 * One search box for the three things people look for. The scope is a row of
 * toggle buttons whose lit thumb slides to the choice, so the box always says
 * where the search will go. Each button is its own tab stop and says whether
 * it is pressed; the term goes along to whichever list is chosen.
 */
export function HomeSearch({ lang, placeholder, scopes, scopeLabel, searchLabel }: {
  lang: Locale;
  placeholder: string;
  scopes: Record<Scope, string>;
  scopeLabel: string;
  searchLabel: string;
}) {
  const router = useRouter();
  const [scope, setScope] = useState<Scope>("jobs");
  const [q, setQ] = useState("");
  const index = SCOPES.indexOf(scope);

  return (
    <form
      role="search"
      className="mt-8 max-w-xl"
      onSubmit={(e) => {
        e.preventDefault();
        const term = q.trim();
        router.push(`/${lang}/${scope}${term ? `?q=${encodeURIComponent(term)}` : ""}`);
      }}
    >
      <div className="glass relative mb-3 grid w-full max-w-sm grid-cols-3 rounded-full p-1" role="group" aria-label={scopeLabel}>
        <span
          aria-hidden="true"
          className="seg-thumb absolute inset-y-1 left-1 w-[calc((100%-0.5rem)/3)] rounded-full bg-white shadow-[0_4px_14px_-6px_rgb(0_0_0/0.5)] transition-transform duration-300 ease-[cubic-bezier(0.2,0.8,0.2,1)]"
          style={{ transform: `translateX(${index * 100}%)` }}
        />
        {SCOPES.map((key) => (
          <button
            key={key}
            type="button"
            aria-pressed={scope === key}
            onClick={() => setScope(key)}
            className={`seg relative z-10 rounded-full px-3 py-2 text-sm font-semibold transition-colors ${scope === key ? "text-night" : "text-white/75 hover:text-white"}`}
          >
            {scopes[key]}
          </button>
        ))}
      </div>
      <div className="searchbar">
        <Search className="size-5 shrink-0 text-muted" aria-hidden="true" />
        <input
          type="search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={placeholder}
          aria-label={placeholder}
          enterKeyHint="search"
          className="min-w-0 flex-1 bg-transparent py-2.5 text-base outline-none placeholder:text-faint"
        />
        <button type="submit" className="btn btn-primary min-h-11 rounded-[0.95rem] px-5">{searchLabel}</button>
      </div>
    </form>
  );
}
