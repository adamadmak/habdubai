import React from "react";
import { useSidebar } from "@/store";
import { cn } from "@/lib/utils";
import { useMounted } from "@/hooks/use-mounted";
import FooterLayout from "./footer-layout";

const Footer = (_: { handleOpenSearch?: () => void }) => {
  const { collapsed } = useSidebar();
  const mounted = useMounted();
  if (!mounted) return null;

  return (
    <FooterLayout
      className={cn({
        "ltr:xl:ml-[256px] rtl:xl:mr-[256px]": !collapsed,
        "ltr:xl:ml-[72px] rtl:xl:mr-[72px]": collapsed,
      })}
    >
      <div className="mx-auto flex max-w-[1760px] flex-wrap items-center justify-between gap-2 text-xs text-default-500">
        <p>© {new Date().getFullYear()} Habesha Dubai · Admin</p>
        <p>Phase one: no money moves through the platform.</p>
      </div>
    </FooterLayout>
  );
};

export default Footer;
