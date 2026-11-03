"use client";
import React from "react";
import { useRouter } from "next/navigation";
import { CornerDownLeft } from "lucide-react";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { menusConfig } from "@/config/menus";

/** "Go to a page": every admin page, grouped like the sidebar. Ctrl/Cmd+K opens it. */
const HeaderSearch = ({ open, setOpen }: { open: boolean; setOpen: (open: boolean) => void }) => {
  const router = useRouter();

  const go = (href: string) => {
    setOpen(false);
    router.push(href);
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent size="lg" className="p-0 overflow-hidden" hiddenCloseIcon>
        <DialogTitle className="sr-only">Go to a page</DialogTitle>
        <Command className="rounded-lg">
          <CommandInput placeholder="Go to a page…" className="h-14 text-base" inputWrapper="px-4 border-border" />
          <CommandList className="max-h-[420px] p-2">
            <CommandEmpty>No page matches. Try “jobs”, “members” or “settings”.</CommandEmpty>
            {menusConfig.sections.map((section) => (
              <CommandGroup
                key={section.title}
                heading={section.title}
                className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:pb-1 [&_[cmdk-group-heading]]:pt-2 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:tracking-[0.12em] [&_[cmdk-group-heading]]:text-default-500"
              >
                {section.items.map((item) => {
                  const Icon = item.icon;
                  return (
                    <CommandItem
                      key={item.href}
                      value={`${item.title} ${item.keywords ?? ""}`}
                      onSelect={() => go(item.href ?? "/dashboard")}
                      className="group flex items-center gap-3 rounded-md px-2 py-2 text-sm text-default-800 aria-selected:bg-accent aria-selected:text-accent-foreground cursor-pointer"
                    >
                      <Icon className="h-4 w-4 text-default-500 group-aria-selected:text-primary" aria-hidden="true" />
                      <span className="flex-1">{item.title}</span>
                      <CornerDownLeft className="h-3.5 w-3.5 opacity-0 group-aria-selected:opacity-60" aria-hidden="true" />
                    </CommandItem>
                  );
                })}
              </CommandGroup>
            ))}
          </CommandList>
        </Command>
      </DialogContent>
    </Dialog>
  );
};

export default HeaderSearch;
