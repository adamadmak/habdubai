"use client";
import { useParams } from "next/navigation";
import { dict, isLocale } from "@/lib/i18n";

export default function ErrorPage({ reset }: { error: Error; reset: () => void }) {
  const params = useParams<{ lang?: string }>();
  const d = dict(isLocale(params?.lang) ? params.lang : "en");

  return (
    <div className="mx-auto max-w-xl px-4 py-24 text-center">
      <h1 className="display text-3xl">{d.common.errorTitle}</h1>
      <p className="mt-3 text-muted">{d.common.errorText}</p>
      <button type="button" onClick={reset} className="btn btn-primary mt-8">{d.common.retry}</button>
    </div>
  );
}
