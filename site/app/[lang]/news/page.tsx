import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Empty, PageHead, Pager } from "@/components/Bits";
import { EventCard, NewsCard } from "@/components/Cards";
import { Chip, ChipRow, hrefWith } from "@/components/Filters";
import { api, query, type Announcement, type Page, type Topic } from "@/lib/api";
import { dict, fill, isLocale, label } from "@/lib/i18n";

type NewsPage = Page<Announcement> & { topics: Topic[] };
type Search = { topic?: string; events?: string; page?: string };

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.news.title, description: d.news.lead, alternates: { canonical: `/${lang}/news` } };
}

export default async function NewsIndex({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<Search> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const sp = await searchParams;
  const events = sp.events === "1";
  const current = { topic: sp.topic, events: events ? "1" : undefined };
  const page = Math.max(1, Number(sp.page) || 1);

  const news = await api<NewsPage>(`/public/announcements${query({ ...current, page })}`);
  const base = `/${lang}/news`;

  return (
    <>
      <PageHead title={d.news.title} lead={d.news.lead} word="ዜና" />

      <div className="mx-auto mt-6 flex max-w-6xl flex-col gap-3 px-4">
        <ChipRow label={d.news.title}>
          <Chip href={hrefWith(base, { topic: current.topic }, {})} active={!events}>{d.news.all}</Chip>
          <Chip href={hrefWith(base, { topic: current.topic }, { events: "1" })} active={events}>{d.news.events}</Chip>
        </ChipRow>
        <ChipRow label={d.news.title}>
          <Chip href={hrefWith(base, current, { topic: undefined })} active={!current.topic}>{d.news.all}</Chip>
          {news.topics.map((t) => (
            <Chip key={t.key} href={hrefWith(base, current, { topic: t.key })} active={current.topic === t.key}>
              <span aria-hidden="true">{t.emoji}</span> {label(t.name, lang)}
            </Chip>
          ))}
        </ChipRow>
      </div>

      <div className="mx-auto mt-6 max-w-6xl px-4">
        {news.data.length === 0 ? (
          <Empty>{d.news.empty}</Empty>
        ) : events ? (
          <div className="grid gap-3 md:grid-cols-2">
            {news.data.map((a) => <EventCard key={a.id} item={a} lang={lang} />)}
          </div>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {news.data.map((a) => <NewsCard key={a.id} item={a} lang={lang} />)}
          </div>
        )}
        <Pager
          page={news.meta.page}
          last={news.meta.last_page}
          href={(p) => `${hrefWith(base, current, {})}${Object.values(current).some(Boolean) ? "&" : "?"}page=${p}`}
          labels={{ previous: d.common.previous, next: d.common.next, pageOf: fill(d.common.pageOf, { page: news.meta.page, pages: news.meta.last_page }) }}
        />
      </div>
    </>
  );
}
