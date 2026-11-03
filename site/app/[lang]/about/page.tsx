import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Send } from "lucide-react";
import { Tibeb } from "@/components/Logo";
import { api, type Meta } from "@/lib/api";
import { dict, isLocale } from "@/lib/i18n";

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.about.title, description: d.about.lead, alternates: { canonical: `/${lang}/about` } };
}

export default async function About({ params }: { params: Promise<{ lang: string }> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const meta = await api<Meta>("/public/meta", { revalidate: 300 }).catch(() => null);

  return (
    <article className="mx-auto max-w-3xl px-4 pt-8 md:pt-12">
      <h1 className="display text-[2rem] md:text-[2.75rem]">{d.about.title}</h1>
      <p className="mt-3 text-[1.125rem] text-muted">{d.about.lead}</p>
      <Tibeb className="my-8 w-32 rounded-sm" />
      {d.about.body.map((p) => <p key={p.slice(0, 20)} className="mt-4 text-[1.0625rem] leading-relaxed">{p}</p>)}

      <section id="rules" className="mt-12">
        <h2 className="title text-2xl">{d.about.rulesTitle}</h2>
        <ol className="mt-5 flex flex-col gap-2.5">
          {d.about.rules.map((rule, i) => (
            <li key={rule} className="card flex items-start gap-3.5 p-4">
              <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary-soft text-sm font-bold text-primary tabular">{i + 1}</span>
              <span className="pt-0.5">{rule}</span>
            </li>
          ))}
        </ol>
      </section>

      <section className="mt-12">
        <h2 className="title text-2xl">{d.about.contactTitle}</h2>
        <p className="mt-2 text-muted">{d.about.contactText}</p>
        {meta?.bot.link && (
          <a href={meta.bot.link} target="_blank" rel="noopener noreferrer" className="btn btn-primary mt-5">
            <Send className="size-4" aria-hidden="true" /> {d.home.joinCta}
          </a>
        )}
      </section>
    </article>
  );
}
