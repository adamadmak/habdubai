"use client";
import { useFallbackCopy } from "@/components/FallbackCopy";

export default function ErrorPage({ reset }: { error: Error; reset: () => void }) {
  const copy = useFallbackCopy();

  return (
    <div className="mx-auto max-w-xl px-4 py-24 text-center">
      <h1 className="display text-3xl">{copy?.errorTitle}</h1>
      <p className="mt-3 text-muted">{copy?.errorText}</p>
      <button type="button" onClick={reset} className="btn btn-primary mt-8">{copy?.retry}</button>
    </div>
  );
}
