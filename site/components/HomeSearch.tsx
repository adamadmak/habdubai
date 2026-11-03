"use client";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Search } from "lucide-react";
import type { Locale } from "@/lib/locales";

type Scope = "jobs" | "directory" | "news";

/** One search box for the three things people look for; the scope decides where it goes. */
export function HomeSearch({ lang, placeholder, scopes, searchLabel }: { lang: Locale; placeholder: string; scopes: Record<Scope, string>; searchLabel: string }) {
  const router = useRouter();
  const [scope, setScope] = useState<Scope>("jobs");
  const [q, setQ] = useState("");

  return (
    <form
      role="search"
      className="mt-7"
      onSubmit={(e) => {
        e.preventDefault();
        const term = q.trim();
        router.push(`/${lang}/${scope}${term && scope !== "news" ? `?q=${encodeURIComponent(term)}` : ""}`);
      }}
    >
      <div className="mb-2.5 flex gap-1.5" role="radiogroup" aria-label={searchLabel}>
        {(Object.keys(scopes) as Scope[]).map((key) => (
          <button
            key={key}
            type="button"
            role="radio"
            aria-checked={scope === key}
            aria-pressed={scope === key}
            onClick={() => setScope(key)}
            className="chip"
          >
            {scopes[key]}
          </button>
        ))}
      </div>
      <div className="flex items-center gap-2 rounded-[1.125rem] border border-line bg-elevated p-1.5 pl-4 shadow-[0_8px_30px_-18px_rgb(0_0_0/0.35)] focus-within:border-primary">
        <Search className="size-5 shrink-0 text-muted" aria-hidden="true" />
        <input
          type="search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={placeholder}
          aria-label={placeholder}
          enterKeyHint="search"
          className="min-w-0 flex-1 bg-transparent py-2 text-base outline-none placeholder:text-faint"
        />
        <button type="submit" className="btn btn-primary min-h-10 px-4">{searchLabel}</button>
      </div>
    </form>
  );
}
