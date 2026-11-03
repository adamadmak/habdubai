import React from "react";
import Link from "next/link";
import { Search } from "lucide-react";
import BrandMark from "@/components/brand-mark";
import { useMediaQuery } from "@/hooks/use-media-query";

type VerticalHeaderProps = {
  handleOpenSearch: () => void;
};

/** Left side of the header: the mark (when the sidebar is a drawer) and "Go to a page". */
const VerticalHeader: React.FC<VerticalHeaderProps> = ({ handleOpenSearch }) => {
  const isDesktop = useMediaQuery("(min-width: 1280px)");

  return (
    <div className="flex items-center md:gap-5 gap-3 min-w-0">
      {!isDesktop && (
        <Link href="/dashboard" className="flex items-center gap-2 rounded-lg" aria-label="Habesha Dubai admin, dashboard">
          <BrandMark className="h-8 w-8" />
          <span className="hidden sm:block font-display text-base font-semibold text-default-900">Habesha Dubai</span>
        </Link>
      )}
      <button
        type="button"
        className="inline-flex h-9 items-center gap-2 rounded-full border border-border bg-card/70 pl-3 pr-2 text-sm text-default-600 transition-colors hover:border-primary/40 hover:text-default-900"
        onClick={handleOpenSearch}
        aria-keyshortcuts="Control+K Meta+K"
      >
        <Search className="h-4 w-4" aria-hidden="true" />
        <span className="hidden md:block pr-6">Go to a page…</span>
        <kbd className="hidden md:inline-flex h-6 items-center rounded-md border border-border bg-default-50 px-1.5 font-sans text-[11px] text-default-500">Ctrl K</kbd>
      </button>
    </div>
  );
};

export default VerticalHeader;
