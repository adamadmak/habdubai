"use client";
import { useSession, signOut } from "next-auth/react";
import Link from "next/link";
import { Icon } from "@iconify/react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const initials = (name: string) =>
  name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "A";

const ProfileInfo = () => {
  const { data: session } = useSession();
  const name = session?.user?.name ?? "Admin";
  const email = session?.user?.email ?? "";

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild className="cursor-pointer">
        <button type="button" className="h-9 w-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-semibold ring-2 ring-card shadow-sm" aria-label={`Account: ${name}`}>
          {initials(name)}
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-56 p-0" align="end">
        <DropdownMenuLabel className="p-3">
          <div className="text-sm font-medium text-default-800">{name}</div>
          <div className="text-xs text-default-600">{email}</div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <Link href="/settings">
          <DropdownMenuItem className="flex items-center gap-2 text-sm font-medium text-default-600 px-3 py-1.5 cursor-pointer">
            <Icon icon="heroicons:cog-6-tooth" className="w-4 h-4" />
            Settings
          </DropdownMenuItem>
        </Link>
        <DropdownMenuSeparator className="mb-0" />
        <DropdownMenuItem onSelect={() => signOut({ callbackUrl: "/auth/login" })} className="flex items-center gap-2 text-sm font-medium text-default-600 my-1 px-3 cursor-pointer">
          <Icon icon="heroicons:power" className="w-4 h-4" />
          Sign out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export default ProfileInfo;
