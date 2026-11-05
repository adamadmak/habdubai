import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Send } from "lucide-react";
import { GEEZ, PageHead } from "@/components/Bits";
import { api, type Meta } from "@/lib/api";
import { dict, isLocale } from "@/lib/i18n";
import { alternatesFor } from "@/lib/site";

// Built on the first visit and kept, instead of being rebuilt for every visitor. No build ever needs the API: the list is empty.
export function generateStaticParams() {
  return [];
}

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.about.title, description: d.about.lead, alternates: alternatesFor(lang, `/about`) };
}

export default async function About({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const meta = await api<Meta>("/public/meta", { revalidate: 300 }).catch(() => null);

  return (
    <>
    <PageHead title={d.about.title} lead={d.about.lead} word="ሰላም" />
    {/* Two columns from a laptop up: the story and how to reach us on the left, the rules beside them. */}
    <article className="mx-auto max-w-page px-4 pt-6 lg:grid lg:grid-cols-2 lg:grid-rows-[auto_1fr] lg:gap-x-16 lg:px-8 xl:gap-x-24">
      <div className="lg:col-start-1">
        {d.about.body.map((p, i) => <p key={p.slice(0, 20)} className={`mt-6 max-w-[70ch] leading-relaxed ${i === 0 ? "text-[1.1875rem]" : "text-[1.0625rem] text-muted"}`}>{p}</p>)}
      </div>

      <section id="rules" className="mt-12 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-6">
        <h2 className="headline text-[1.875rem]">{d.about.rulesTitle}</h2>
        <ol className="mt-5 flex flex-col gap-2.5">
          {d.about.rules.map((rule, i) => (
            <li key={rule} className="card flex items-start gap-3.5 p-4">
              <span className="geez-mark size-8 text-[0.9375rem]" aria-hidden="true">{GEEZ[i]}</span>
              <span className="pt-0.5">{rule}</span>
            </li>
          ))}
        </ol>
      </section>

      <section className="mt-12 lg:col-start-1">
        <h2 className="headline text-[1.875rem]">{d.about.contactTitle}</h2>
        <p className="mt-2 text-muted">{d.about.contactText}</p>
        {meta?.bot.link && (
          <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-primary mt-5">
            <Send className="size-4" aria-hidden="true" /> {d.home.joinCta}
          </a>
        )}
      </section>
    </article>
    </>
  );
}
