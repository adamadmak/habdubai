"use client";
import { Inter } from "next/font/google";
import { ThemeProvider } from "next-themes";
import { cn } from "@/lib/utils";
import { Toaster as ReactToaster } from "@/components/ui/toaster";
import { Toaster } from "react-hot-toast";
import { SonnToaster } from "@/components/ui/sonner";

const inter = Inter({ subsets: ["latin"], display: "swap" });

/**
 * The brand's colours are fixed tokens in globals.scss; the template's theme
 * switcher (colour themes, radius, layouts) is gone, so nothing here reads it.
 */
const Providers = ({ children }: { children: React.ReactNode }) => {
  return (
    <body className={cn("dash-tail-app", inter.className)}>
      <ThemeProvider attribute="class" enableSystem={false} defaultTheme="light">
        <div className="h-full">
          {children}
          <ReactToaster />
        </div>
        <Toaster
          position="top-right"
          toastOptions={{
            duration: 4500,
            className: "!rounded-xl !shadow-lift !text-sm !text-default-900 !bg-card !border !border-border",
            error: { duration: 7000 },
          }}
        />
        <SonnToaster />
      </ThemeProvider>
    </body>
  );
};

export default Providers;
