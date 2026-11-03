"use client";
import React from "react";
import { useMediaQuery } from "@/hooks/use-media-query";
import ClassicSidebar from "./classic";

/** The fixed rail on wide screens; below 1280 px the drawer (MobileSidebar, in the layout) takes over. */
const Sidebar = ({ trans }: { trans?: unknown }) => {
  const isDesktop = useMediaQuery("(min-width: 1280px)");
  return isDesktop ? <ClassicSidebar trans={trans} /> : null;
};

export default Sidebar;
