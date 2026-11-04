"use client";
import { usePathname, useSearchParams } from "next/navigation";
import { useEffect } from "react";

/**
 * A list's filters, kept in the address: a reload, Back from an opened row and
 * a shared link all come back to the same view. A value at its default stays
 * out of the address. Changed through the browser's own history, which Next
 * keeps in step with useSearchParams: these pages fetch their own data, so a
 * click answers at once, with no trip to the server for the page.
 */
export function useUrlState<K extends string>(defaults: Record<K, string>): [Record<K, string>, (changes: Partial<Record<K, string>>) => void] {
  const pathname = usePathname();
  const params = useSearchParams();

  const values = Object.fromEntries((Object.keys(defaults) as K[]).map((key) => [key, params.get(key) ?? defaults[key]])) as Record<K, string>;

  const set = (changes: Partial<Record<K, string>>) => {
    const next = new URLSearchParams(params.toString());
    (Object.entries(changes) as [K, string][]).forEach(([key, value]) => {
      if (!value || value === defaults[key]) next.delete(key);
      else next.set(key, value);
    });
    const query = next.toString();
    window.history.replaceState(null, "", query ? `${pathname}?${query}` : pathname);
  };

  return [values, set];
}

/** A page number from the address: a hand-edited or stale value lands on page one. */
export function pageNumber(value: string): number {
  const page = Number(value);

  return Number.isInteger(page) && page > 0 ? page : 1;
}

/**
 * A page number can outlive its page: an old link, or the last row of the last page
 * going away. The list then shows nothing and no pager to get back with, so it
 * moves to the last page there is.
 */
export function useLastPageAtMost(lastPage: number | undefined, page: number, setPage: (page: number) => void): void {
  useEffect(() => {
    if (lastPage !== undefined && page > Math.max(lastPage, 1)) setPage(Math.max(lastPage, 1));
    // setPage is made afresh each render; only the numbers decide.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lastPage, page]);
}
