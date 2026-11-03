import { LayoutDashboard, Users, MessageSquare, Settings, ScrollText } from "lucide-react";

export interface MenuItemProps {
  title: string;
  icon: any;
  href?: string;
  isHeader?: boolean;
  child?: MenuItemProps[];
  megaMenu?: MenuItemProps[];
  multi_menu?: MenuItemProps[];
  nested?: MenuItemProps[];
  onClick?: () => void;
}

const items: MenuItemProps[] = [
  { title: "Dashboard", icon: LayoutDashboard, href: "/dashboard" },
  { title: "Members", icon: Users, href: "/members" },
  { title: "Bot texts", icon: MessageSquare, href: "/bot-texts" },
  { title: "Settings", icon: Settings, href: "/settings" },
  { title: "Audit log", icon: ScrollText, href: "/audit" },
];

const classic: MenuItemProps[] = [{ isHeader: true, title: "menu", icon: null }, ...items];

export const menusConfig = {
  mainNav: items,
  sidebarNav: {
    modern: items,
    classic,
  },
};

export type ModernNavType = (typeof menusConfig.sidebarNav.modern)[number];
export type ClassicNavType = (typeof menusConfig.sidebarNav.classic)[number];
export type MainNavType = (typeof menusConfig.mainNav)[number];
