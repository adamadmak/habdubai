"use client";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import BrandMark from "@/components/brand-mark";

/** The admin's 404: no such page, and the way back. */
const ErrorBlock = () => {
  return (
    <div className="min-h-screen flex items-center justify-center p-6">
      <div className="max-w-md text-center">
        <BrandMark className="mx-auto h-14 w-14" />
        <div className="mt-6 font-display text-[64px] leading-none font-semibold text-default-900">404</div>
        <h1 className="mt-3 text-xl font-semibold text-default-900">There is no such page</h1>
        <p className="mt-2 text-sm text-default-600">The link may be old, or the page was renamed. Every admin page is in the sidebar, or press Ctrl K to find one.</p>
        <Button asChild className="mt-8" size="lg">
          <Link href="/dashboard">Go to the dashboard</Link>
        </Button>
      </div>
    </div>
  );
};

export default ErrorBlock;
