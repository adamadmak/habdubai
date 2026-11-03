import React from "react";
import { cn } from "@/lib/utils";

const FooterLayout = ({ children, className }: { children: React.ReactNode; className?: string }) => {
  return <footer className={cn("relative border-t border-border/70 px-4 md:px-8 py-4", className)}>{children}</footer>;
};

export default FooterLayout;
