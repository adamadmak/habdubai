"use client";
import Link from "@/components/AppLink";
import { useFallbackCopy } from "@/components/FallbackCopy";
import { Tibeb } from "@/components/Logo";

export default function NotFound() {
  const copy = useFallbackCopy();

  return (
    <div className="mx-auto max-w-xl px-4 py-24 text-center">
      <Tibeb className="mx-auto mb-10 w-40" />
      <h1 className="display text-3xl">{copy?.notFoundTitle}</h1>
      <p className="mt-3 text-muted">{copy?.notFoundText}</p>
      <Link href={`/${copy?.lang ?? "en"}`} className="btn btn-primary mt-8">{copy?.goHome}</Link>
    </div>
  );
}
