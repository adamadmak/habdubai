"use client";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Tibeb } from "@/components/Logo";
import { dict, isLocale } from "@/lib/i18n";

export default function NotFound() {
  const params = useParams<{ lang?: string }>();
  const lang = isLocale(params?.lang) ? params.lang : "en";
  const d = dict(lang);

  return (
    <div className="mx-auto max-w-xl px-4 py-24 text-center">
      <Tibeb className="mx-auto mb-10 w-40" />
      <h1 className="display text-3xl">{d.common.notFoundTitle}</h1>
      <p className="mt-3 text-muted">{d.common.notFoundText}</p>
      <Link href={`/${lang}`} className="btn btn-primary mt-8">{d.common.goHome}</Link>
    </div>
  );
}
