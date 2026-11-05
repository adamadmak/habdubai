import type { CSSProperties } from "react";
import Link from "@/components/AppLink";
import type { Announcement } from "@/lib/api";
import { relative } from "@/lib/format";
import { label, pick, pickLang, type Locale } from "@/lib/i18n";
import { firstLine, newsParts } from "./Cards";

const TOPIC_TINT: Record<string, string> = {
  safety: "#d40511",
  consulate: "#15803d",
  platform: "#266ef3",
  travel: "#c2570a",
  events: "#b98900",
};

/** Cards in one pass, so the strip never runs out on a very wide screen. */
const MIN_CARDS = 8;
/** Below this the strip stands still: a few cards circling looks like a mistake. */
const MIN_TO_MOVE = 3;

function Card({ item, lang, hidden }: { item: Announcement; lang: Locale; hidden: boolean }) {
  const text = pick(item.text, lang);
  const key = item.topic?.key ?? "";
  return (
    <Link
      href={`/${lang}/news/${item.id}`}
      className="kind-tile news-marquee-card lift gap-2 p-4"
      style={{ "--tint": TOPIC_TINT[key] ?? "#191919" } as CSSProperties}
      {...(hidden ? { tabIndex: -1, "aria-hidden": true } : {})}
    >
      <span className="kind-tile-icon flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wide">
        <span aria-hidden="true">{item.topic?.emoji}</span>
        <span className="truncate">{label(item.topic?.name, lang)}</span>
      </span>
      <span className="line-clamp-3 font-bold leading-snug" lang={pickLang(item.text, lang)}>
        {newsParts(text).head ?? firstLine(text, 110)}
      </span>
      <span className="mt-auto text-xs text-muted">{relative(item.published_at, lang)}</span>
    </Link>
  );
}

/**
 * News as one strip across the page. On a device with a pointer it drifts along and stops
 * while pointed at or focused. On a phone, or with reduced motion, it is a plain row to swipe:
 * a moving card is hard to tap. Pure CSS; the copies are hidden from those devices and screen readers.
 */
export function NewsMarquee({ items, lang }: { items: Announcement[]; lang: Locale }) {
  const moves = items.length >= MIN_TO_MOVE;
  const extra: Announcement[] = [];
  if (moves) {
    while (items.length + extra.length < MIN_CARDS) extra.push(...items);
  }
  const pass = items.length + extra.length;
  const seconds = pass * 7;

  return (
    <div className={`news-marquee ${moves ? "news-marquee-moves" : ""}`} style={{ "--marquee-seconds": `${seconds}s` } as CSSProperties}>
      <div className="news-marquee-track">
        {items.map((item) => <Card key={item.id} item={item} lang={lang} hidden={false} />)}
        {moves && (
          <span className="news-marquee-copy" aria-hidden="true">
            {extra.map((item, i) => <Card key={`fill-${i}`} item={item} lang={lang} hidden />)}
            {[...items, ...extra].map((item, i) => <Card key={`copy-${i}`} item={item} lang={lang} hidden />)}
          </span>
        )}
      </div>
    </div>
  );
}
