import {
  LayoutDashboard, Users, MessageSquare, Settings, ScrollText, Briefcase, Rss, SlidersHorizontal, Store, MapPin,
  HelpCircle, BookOpen, ShieldAlert, Megaphone, Flag, Package, Rocket,
} from "lucide-react";

export interface MenuItemProps {
  title: string;
  icon: any;
  href?: string;
  isHeader?: boolean;
  /** Words "Go to a page" also matches. */
  keywords?: string;
  child?: MenuItemProps[];
  megaMenu?: MenuItemProps[];
  multi_menu?: MenuItemProps[];
  nested?: MenuItemProps[];
  onClick?: () => void;
}

/** Every page of the admin, and nothing else. Headers group them in the sidebar. */
const sections: { title: string; items: MenuItemProps[] }[] = [
  {
    title: "Overview",
    items: [
      { title: "Launch checklist", icon: Rocket, href: "/launch", keywords: "readiness go live health blockers" },
      { title: "Dashboard", icon: LayoutDashboard, href: "/dashboard", keywords: "home stats overview" },
    ],
  },
  {
    title: "Community",
    items: [
      { title: "Members", icon: Users, href: "/members", keywords: "people ban role leader" },
      { title: "Announcements", icon: Megaphone, href: "/announcements", keywords: "news post topics" },
      { title: "Questions", icon: HelpCircle, href: "/questions", keywords: "ask answers forum" },
      { title: "FAQ and knowledge", icon: BookOpen, href: "/faq", keywords: "assistant articles" },
      { title: "Moderation", icon: Flag, href: "/moderation", keywords: "reports strikes mute" },
      { title: "Safety reports", icon: ShieldAlert, href: "/safety", keywords: "employer abuse safe" },
      { title: "Goods board", icon: Package, href: "/goods", keywords: "travel parcel kilos disputes" },
    ],
  },
  {
    title: "Jobs",
    items: [
      { title: "Jobs", icon: Briefcase, href: "/jobs", keywords: "listings review" },
      { title: "Job sources", icon: Rss, href: "/job-sources", keywords: "feeds rss scraping" },
      { title: "Job settings", icon: SlidersHorizontal, href: "/job-settings", keywords: "channel claude anthropic scam" },
    ],
  },
  {
    title: "Directory",
    items: [
      { title: "Businesses", icon: Store, href: "/directory", keywords: "directory listings owners claims" },
      { title: "Directory cities", icon: MapPin, href: "/directory-cities", keywords: "cities" },
    ],
  },
  {
    title: "System",
    items: [
      { title: "Bot texts", icon: MessageSquare, href: "/bot-texts", keywords: "messages translations wording" },
      { title: "Settings", icon: Settings, href: "/settings", keywords: "telegram token webhook groups" },
      { title: "Audit log", icon: ScrollText, href: "/audit", keywords: "history changes" },
    ],
  },
];

const items: MenuItemProps[] = sections.flatMap((s) => s.items);

const classic: MenuItemProps[] = sections.flatMap((s) => [{ isHeader: true, title: s.title, icon: null }, ...s.items]);

export const menusConfig = {
  sections,
  mainNav: items,
  sidebarNav: {
    modern: items,
    classic,
  },
};

export type ModernNavType = (typeof menusConfig.sidebarNav.modern)[number];
export type ClassicNavType = (typeof menusConfig.sidebarNav.classic)[number];
export type MainNavType = (typeof menusConfig.mainNav)[number];
