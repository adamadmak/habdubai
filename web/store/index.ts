import { create } from "zustand";
import { siteConfig } from "@/config/site";
import { persist, createJSONStorage } from "zustand/middleware";

/**
 * Layout settings. The template let each browser pick a layout, sidebar and
 * colour theme and remembered the choice; the admin now has one layout, so
 * these are fixed and nothing about them is stored. (Old "theme-store" and
 * "sidebar-store" entries left in a browser are simply ignored.)
 */
interface ThemeStoreState {
  theme: string;
  setTheme: (theme: string) => void;
  radius: number;
  setRadius: (value: number) => void;
  layout: string;
  setLayout: (value: string) => void;
  navbarType: string;
  setNavbarType: (value: string) => void;
  footerType: string;
  setFooterType: (value: string) => void;
  isRtl: boolean;
  setRtl: (value: boolean) => void;
}

export const useThemeStore = create<ThemeStoreState>()((set) => ({
  theme: siteConfig.theme,
  setTheme: (theme) => set({ theme }),
  radius: siteConfig.radius,
  setRadius: (value) => set({ radius: value }),
  layout: siteConfig.layout,
  setLayout: (value) => set({ layout: value }),
  navbarType: siteConfig.navbarType,
  setNavbarType: (value) => set({ navbarType: value }),
  footerType: siteConfig.footerType,
  setFooterType: (value) => set({ footerType: value }),
  isRtl: false,
  setRtl: (value) => set({ isRtl: value }),
}));

interface SidebarState {
  collapsed: boolean;
  setCollapsed: (value: boolean) => void;
  sidebarType: string;
  setSidebarType: (value: string) => void;
  subMenu: boolean;
  setSubmenu: (value: boolean) => void;
  sidebarBg: string;
  setSidebarBg: (value: string) => void;
  mobileMenu: boolean;
  setMobileMenu: (value: boolean) => void;
}

/** Only whether the sidebar is collapsed is remembered between visits. */
export const useSidebar = create<SidebarState>()(
  persist(
    (set) => ({
      collapsed: false,
      setCollapsed: (value) => set({ collapsed: value }),
      sidebarType: siteConfig.sidebarType,
      setSidebarType: (value) => set({ sidebarType: value }),
      subMenu: false,
      setSubmenu: (value) => set({ subMenu: value }),
      sidebarBg: siteConfig.sidebarBg,
      setSidebarBg: (value) => set({ sidebarBg: value }),
      mobileMenu: false,
      setMobileMenu: (value) => set({ mobileMenu: value }),
    }),
    {
      name: "hd-admin-sidebar",
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ collapsed: state.collapsed }),
    }
  )
);
