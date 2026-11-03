import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Plus } from "lucide-react";
import { Empty, PageHead, Pager } from "@/components/Bits";
import { BusinessCard, countLabel } from "@/components/Cards";
import { BusinessIcon } from "@/components/CategoryIcon";
import { Chip, ChipRow, hrefWith, SearchBox } from "@/components/Filters";
import { api, query, type Business, type Facets, type Meta, type Page } from "@/lib/api";
import { dict, fill, isLocale, label, pick } from "@/lib/i18n";

type Search = { q?: string; city?: string; category?: string; page?: string };

export async function generateMetadata({ params }: { params: Promise<{ lang: string }> }): Promise<Metadata> {
  const { lang } = await params;
  if (!isLocale(lang)) return {};
  const d = dict(lang);
  return { title: d.directory.title, description: d.directory.lead, alternates: { canonical: `/${lang}/directory` } };
}

export default async function DirectoryIndex({ params, searchParams }: { params: Promise<{ lang: string }>; searchParams: Promise<Search> }) {
  const { lang } = await params;
  if (!isLocale(lang)) notFound();
  const d = dict(lang);
  const sp = await searchParams;
  const current = { q: sp.q?.slice(0, 80), city: sp.city, category: sp.category };
  const page = Math.max(1, Number(sp.page) || 1);

  const [list, facets, meta] = await Promise.all([
    api<Page<Business>>(`/public/businesses${query({ ...current, page })}`),
    api<Facets>("/public/directory", { revalidate: 60 }),
    api<Meta>("/public/meta", { revalidate: 300 }),
  ]);

  const base = `/${lang}/directory`;
  const cities = facets.cities.filter((c) => c.count > 0 || c.key === current.city);
  const kinds = facets.categories.filter((c) => c.count > 0 || c.key === current.category);

  return (
    <>
      <PageHead title={d.directory.title} lead={d.directory.lead}>
        <Link href={`/${lang}/business`} className="btn btn-line mt-5">
          <Plus className="size-4" aria-hidden="true" /> {d.directory.addCta}
        </Link>
      </PageHead>

      <div className="mx-auto mt-6 flex max-w-6xl flex-col gap-3 px-4">
        <SearchBox action={base} q={current.q} placeholder={d.directory.searchPlaceholder} keep={{ city: current.city, category: current.category }} labels={{ search: d.common.search, clear: d.common.clear }} />
        {cities.length > 1 && (
          <ChipRow label={d.directory.allCities}>
            <Chip href={hrefWith(base, current, { city: undefined })} active={!current.city}>{d.directory.allCities}</Chip>
            {cities.map((c) => (
              <Chip key={c.key} href={hrefWith(base, current, { city: c.key })} active={current.city === c.key}>
                {pick(c.name, lang)} <span className="count">{c.count}</span>
              </Chip>
            ))}
          </ChipRow>
        )}
        <ChipRow label={d.directory.allKinds}>
          <Chip href={hrefWith(base, current, { category: undefined })} active={!current.category}>{d.directory.allKinds}</Chip>
          {kinds.map((c) => (
            <Chip key={c.key} href={hrefWith(base, current, { category: c.key })} active={current.category === c.key}>
              <BusinessIcon category={c.key} className="size-4" />
              {label(c.name, lang)} <span className="count">{c.count}</span>
            </Chip>
          ))}
        </ChipRow>
      </div>

      <div className="mx-auto mt-6 max-w-6xl px-4">
        <p className="mb-3 text-sm text-muted" aria-live="polite">{countLabel(list.meta.total, d.directory.countOne, d.directory.count, lang)}</p>
        {list.data.length === 0 ? (
          <Empty>
            {d.directory.empty}{" "}
            <Link href={`/${lang}/business`} className="font-semibold text-primary">{d.directory.addCta}</Link>
          </Empty>
        ) : (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {list.data.map((b) => <BusinessCard key={b.id} business={b} lang={lang} meta={meta} />)}
          </div>
        )}
        <Pager
          page={list.meta.page}
          last={list.meta.last_page}
          href={(p) => `${hrefWith(base, current, {})}${Object.values(current).some(Boolean) ? "&" : "?"}page=${p}`}
          labels={{ previous: d.common.previous, next: d.common.next, pageOf: fill(d.common.pageOf, { page: list.meta.page, pages: list.meta.last_page }) }}
        />
      </div>
    </>
  );
}
