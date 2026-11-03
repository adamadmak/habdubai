import {
  LayoutDashboard, Users, MessageSquare, Settings, ScrollText, Briefcase, Rss, SlidersHorizontal, Store, MapPin,
  HelpCircle, BookOpen, ShieldAlert,
} from "lucide-react";

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
  { title: "Questions", icon: HelpCircle, href: "/questions" },
  { title: "FAQ and knowledge", icon: BookOpen, href: "/faq" },
  { title: "Safety reports", icon: ShieldAlert, href: "/safety" },
  { title: "Jobs", icon: Briefcase, href: "/jobs" },
  { title: "Job sources", icon: Rss, href: "/job-sources" },
  { title: "Job settings", icon: SlidersHorizontal, href: "/job-settings" },
  { title: "Directory", icon: Store, href: "/directory" },
  { title: "Directory cities", icon: MapPin, href: "/directory-cities" },
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
