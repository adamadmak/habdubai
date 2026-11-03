"use client";
import React from "react";
import BrandMark from "@/components/brand-mark";

const LayoutLoader = () => {
  return (
    <div className="h-screen flex items-center justify-center flex-col gap-3" role="status" aria-live="polite">
      <BrandMark className="h-12 w-12 motion-safe:animate-pulse" />
      <span className="text-sm text-default-500">Opening the admin…</span>
    </div>
  );
};

export default LayoutLoader;
