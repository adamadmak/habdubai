import Link from "next/link";
import { Search, X } from "lucide-react";

/** A search box that submits as a plain GET form, keeping the other filters as hidden fields. */
export function SearchBox({ action, q, placeholder, keep, labels }: {
  action: string;
  q?: string;
  placeholder: string;
  keep: Record<string, string | undefined>;
  labels: { search: string; clear: string };
}) {
  const clearParams = new URLSearchParams(Object.entries(keep).filter(([, v]) => v) as [string, string][]).toString();

  return (
    <form role="search" action={action} className="flex items-center gap-2 rounded-[1.125rem] border border-line bg-elevated p-1.5 pl-4 focus-within:border-primary">
      <Search className="size-5 shrink-0 text-muted" aria-hidden="true" />
      {Object.entries(keep).map(([k, v]) => (v ? <input key={k} type="hidden" name={k} value={v} /> : null))}
      <input
        type="search"
        name="q"
        defaultValue={q}
        placeholder={placeholder}
        aria-label={placeholder}
        enterKeyHint="search"
        className="min-w-0 flex-1 bg-transparent py-2 text-base outline-none placeholder:text-faint"
      />
      {q && (
        <Link href={`${action}${clearParams ? `?${clearParams}` : ""}`} aria-label={labels.clear} className="press grid size-9 place-items-center rounded-full text-muted hover:bg-surface">
          <X className="size-4" aria-hidden="true" />
        </Link>
      )}
      <button type="submit" className="btn btn-primary min-h-10 px-4">{labels.search}</button>
    </form>
  );
}

/** A row of filter chips that scrolls sideways on a phone. */
export function ChipRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div role="group" aria-label={label} className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      {children}
    </div>
  );
}

export function Chip({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link href={href} aria-current={active ? "true" : undefined} className="chip" scroll={false}>
      {children}
    </Link>
  );
}

/** A link to the same page with one filter changed and the page reset. */
export function hrefWith(base: string, current: Record<string, string | undefined>, change: Record<string, string | undefined>): string {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries({ ...current, ...change, page: undefined })) {
    if (v) params.set(k, v);
  }
  const text = params.toString();
  return text ? `${base}?${text}` : base;
}
