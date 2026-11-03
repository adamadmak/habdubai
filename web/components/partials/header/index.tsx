"use client";
import React from "react";
import { cn } from "@/lib/utils";
import { useSidebar } from "@/store";
import { useMediaQuery } from "@/hooks/use-media-query";
import ThemeButton from "./theme-button";
import ProfileInfo from "./profile-info";
import VerticalHeader from "./vertical-header";
import MobileMenuHandler from "./mobile-menu-handler";
import ClassicHeader from "./layout/classic-header";

/** The sticky, frosted bar above every page: search on the left, theme and account on the right. */
const Header = ({ handleOpenSearch }: { handleOpenSearch: () => void; trans?: unknown }) => {
  const { collapsed } = useSidebar();
  const isDesktop = useMediaQuery("(min-width: 1280px)");

  return (
    <ClassicHeader
      className={cn("sticky top-0 z-40", {
        "ltr:xl:ml-[256px] rtl:xl:mr-[256px]": !collapsed,
        "ltr:xl:ml-[72px] rtl:xl:mr-[72px]": collapsed,
      })}
    >
      <div className="w-full bg-card/75 supports-[backdrop-filter]:bg-card/65 backdrop-blur-xl backdrop-saturate-150 md:px-8 px-4 py-2.5 border-b border-border/80">
        <div className="flex justify-between items-center h-full gap-3">
          <VerticalHeader handleOpenSearch={handleOpenSearch} />
          <div className="nav-tools flex items-center gap-1.5">
            <ThemeButton />
            <div className="ltr:pl-1.5 rtl:pr-1.5">
              <ProfileInfo />
            </div>
            {!isDesktop && <MobileMenuHandler />}
          </div>
        </div>
      </div>
    </ClassicHeader>
  );
};

export default Header;
