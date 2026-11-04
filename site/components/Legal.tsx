import Link from "next/link";
import { GEEZ, PageHead } from "./Bits";
import { formatDate } from "@/lib/format";
import { fill, type Dictionary } from "@/lib/i18n";
import type { Locale } from "@/lib/locales";
import { LEGAL_UPDATED } from "@/lib/site";

type Doc = Dictionary["privacy"];

/** The privacy notice or the terms: short numbered sections, the date on top, a link to the other one at the foot. */
export function LegalPage({ lang, d, doc, other }: { lang: Locale; d: Dictionary; doc: Doc; other: "privacy" | "terms" }) {
  return (
    <>
      <PageHead title={doc.title} lead={doc.lead} />
      <article className="mx-auto max-w-page px-4 pt-6 lg:px-8">
        <p className="text-sm text-muted">
          <time dateTime={LEGAL_UPDATED}>{fill(d.legal.updated, { date: formatDate(LEGAL_UPDATED, lang) })}</time>
        </p>
        {/* Short sections read down one column and then the next on a wide screen; a section never splits across them. */}
        <div className="mt-10 lg:columns-2 lg:gap-16 xl:gap-24">
          {doc.sections.map((section, i) => (
            <section key={section.title} className="mb-10 break-inside-avoid">
              <h2 className="headline flex items-center gap-3 text-[1.5rem] md:text-[1.75rem]">
                <span className="geez-mark size-8 text-[0.9375rem]" aria-hidden="true">{GEEZ[i]}</span>
                {section.title}
              </h2>
              {section.body.map((paragraph) => (
                <p key={paragraph.slice(0, 32)} className="mt-3 max-w-[70ch] text-[1.0625rem] leading-relaxed text-muted">{paragraph}</p>
              ))}
            </section>
          ))}
        </div>
        <p className="mt-2 text-sm">
          <Link href={`/${lang}/${other}`} className="font-semibold text-primary">
            {other === "privacy" ? d.legal.privacyLink : d.legal.termsLink}
          </Link>
          <span className="text-muted" aria-hidden="true"> · </span>
          <Link href={`/${lang}/about#rules`} className="font-semibold text-primary">{d.footer.rules}</Link>
        </p>
      </article>
    </>
  );
}
