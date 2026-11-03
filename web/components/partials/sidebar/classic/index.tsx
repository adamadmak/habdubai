"use client";
import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronsLeft, ChevronsRight } from "lucide-react";
import { cn, isLocationMatch, getDynamicPath } from "@/lib/utils";
import { useSidebar } from "@/store";
import { menusConfig, type MenuItemProps } from "@/config/menus";
import { ScrollArea } from "@/components/ui/scroll-area";
import BrandMark from "@/components/brand-mark";

/** The navy navigation: grouped pages, the active one marked with a gold bar. Shared by the desktop rail and the phone drawer. */
export const SidebarNav = ({ compact = false, onNavigate }: { compact?: boolean; onNavigate?: () => void }) => {
  const location = getDynamicPath(usePathname());

  return (
    <nav aria-label="Admin pages" className={cn("pb-6", compact ? "px-3" : "px-3")}>
      {menusConfig.sections.map((section) => (
        <div key={section.title} className="mt-4 first:mt-1">
          {compact ? (
            <div className="mx-auto my-3 h-px w-6 bg-white/15" aria-hidden="true" />
          ) : (
            <div className="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/50">{section.title}</div>
          )}
          <ul className="space-y-0.5">
            {section.items.map((item) => (
              <li key={item.href}>
                <NavLink item={item} active={isLocationMatch(item.href, location)} compact={compact} onNavigate={onNavigate} />
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  );
};

const NavLink = ({ item, active, compact, onNavigate }: { item: MenuItemProps; active: boolean; compact: boolean; onNavigate?: () => void }) => {
  const Icon = item.icon;
  return (
    <Link
      href={item.href ?? "#"}
      onClick={onNavigate}
      aria-current={active ? "page" : undefined}
      title={compact ? item.title : undefined}
      className={cn(
        "group relative flex items-center gap-3 rounded-lg text-[13.5px] font-medium transition-colors",
        "focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-gold",
        compact ? "mx-auto h-11 w-11 justify-center" : "px-3 py-2",
        active ? "bg-white/[0.11] text-white" : "text-white/75 hover:bg-white/[0.07] hover:text-white"
      )}
    >
      {active && <span aria-hidden="true" className="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-gold" />}
      <Icon className={cn("h-[18px] w-[18px] shrink-0", active ? "text-gold" : "text-white/60 group-hover:text-white/90")} aria-hidden="true" />
      {compact ? <span className="sr-only">{item.title}</span> : <span className="truncate">{item.title}</span>}
    </Link>
  );
};

export const SidebarBrand = ({ compact = false }: { compact?: boolean }) => (
  <Link href="/dashboard" className={cn("flex items-center gap-3 rounded-lg focus-visible:outline-gold", compact && "justify-center")} aria-label="Habesha Dubai admin, dashboard">
    <BrandMark className="h-9 w-9 shrink-0 drop-shadow-[0_4px_12px_rgba(242,183,5,0.25)]" />
    {!compact && (
      <span className="min-w-0">
        <span className="block font-display text-[17px] font-semibold leading-tight text-white">Habesha Dubai</span>
        <span className="block text-[11px] font-semibold uppercase tracking-[0.14em] text-gold/90">Admin</span>
      </span>
    )}
  </Link>
);

const ClassicSidebar = ({ trans }: { trans?: unknown }) => {
  const { collapsed, setCollapsed } = useSidebar();
  const [hovered, setHovered] = useState(false);
  const compact = collapsed && !hovered;

  return (
    <aside
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      // Keyboard users open a collapsed rail by tabbing into it.
      onFocus={() => setHovered(true)}
      onBlur={(event) => !event.currentTarget.contains(event.relatedTarget as Node | null) && setHovered(false)}
      className={cn(
        "fixed top-0 z-[50] flex h-full flex-col text-white transition-[width] duration-200",
        "bg-[linear-gradient(180deg,hsl(225_67%_13%)_0%,hsl(226_70%_9%)_100%)]",
        compact ? "w-[72px]" : "w-[256px]",
        collapsed && hovered && "shadow-lift"
      )}
    >
      <div className={cn("flex items-center gap-2 pt-5 pb-4", compact ? "px-3 justify-center" : "px-5")}>
        <div className="min-w-0 flex-1">
          <SidebarBrand compact={compact} />
        </div>
        {!compact && (
          <button
            type="button"
            onClick={() => setCollapsed(!collapsed)}
            className="hidden xl:inline-flex h-8 w-8 items-center justify-center rounded-md text-white/60 hover:bg-white/10 hover:text-white focus-visible:outline-gold"
            aria-label={collapsed ? "Keep the sidebar open" : "Collapse the sidebar"}
            aria-pressed={collapsed}
          >
            {collapsed ? <ChevronsRight className="h-4 w-4" /> : <ChevronsLeft className="h-4 w-4" />}
          </button>
        )}
      </div>
      <div className={cn("tibeb-rule mb-2 opacity-70", compact ? "mx-4" : "mx-5")} aria-hidden="true" />
      <ScrollArea className="flex-1">
        <SidebarNav compact={compact} />
      </ScrollArea>
    </aside>
  );
};

export default ClassicSidebar;
