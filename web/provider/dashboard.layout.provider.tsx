"use client";
import React from "react";
import { motion, useReducedMotion } from "framer-motion";
import { usePathname } from "next/navigation";
import Header from "@/components/partials/header";
import Sidebar from "@/components/partials/sidebar";
import Footer from "@/components/partials/footer";
import MobileSidebar from "@/components/partials/sidebar/mobile-sidebar";
import HeaderSearch from "@/components/header-search";
import LayoutLoader from "@/components/layout-loader";
import { cn, getDynamicPath, isLocationMatch } from "@/lib/utils";
import { menusConfig } from "@/config/menus";
import { useSidebar } from "@/store";
import { useMounted } from "@/hooks/use-mounted";

/** One layout: the navy sidebar on the left (a drawer below 1280 px), the header, the page. */
const DashBoardLayoutProvider = ({ children, trans }: { children: React.ReactNode; trans: any }) => {
  const { collapsed } = useSidebar();
  const [open, setOpen] = React.useState(false);
  const location = usePathname();
  const mounted = useMounted();
  const reduceMotion = useReducedMotion();

  // Ctrl/Cmd+K opens "Go to a page" from anywhere.
  React.useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setOpen((value) => !value);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // The tab says which page this is ("Jobs · Habesha Dubai"), so several open tabs stay apart.
  React.useEffect(() => {
    const path = getDynamicPath(location);
    const page = menusConfig.mainNav.find((item) => isLocationMatch(item.href, path));
    document.title = page ? `${page.title} · Habesha Dubai` : "Habesha Dubai admin";
  }, [location]);

  if (!mounted) {
    return <LayoutLoader />;
  }

  return (
    <>
      <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[10000] focus:rounded-md focus:bg-card focus:px-4 focus:py-2 focus:shadow-lift">
        Skip to content
      </a>
      <Header handleOpenSearch={() => setOpen(true)} trans={trans} />
      <Sidebar trans={trans} />

      <div
        className={cn("content-wrapper transition-[margin] duration-150", {
          "ltr:xl:ml-[256px] rtl:xl:mr-[256px]": !collapsed,
          "ltr:xl:ml-[72px] rtl:xl:mr-[72px]": collapsed,
        })}
      >
        <div className="pt-6 px-4 md:px-8 pb-10 page-min-height">
          <motion.div
            key={location}
            initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: reduceMotion ? 0.12 : 0.22, ease: [0.2, 0.8, 0.2, 1] }}
          >
            <main id="main" className="mx-auto max-w-[1760px]">{children}</main>
          </motion.div>
          <MobileSidebar trans={trans} />
          <HeaderSearch open={open} setOpen={setOpen} />
        </div>
      </div>
      <Footer handleOpenSearch={() => setOpen(true)} />
    </>
  );
};

export default DashBoardLayoutProvider;
