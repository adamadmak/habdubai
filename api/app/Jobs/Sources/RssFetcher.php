<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;
use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;

/**
 * RSS 2.0 and Atom feeds. Many job sites publish one, and RSS bridges turn a
 * public Telegram channel or a search page into one; this is the preferred
 * kind of source because a feed is meant to be read by machines.
 */
class RssFetcher implements SourceFetcher
{
    public function fetch(JobSource $source): array
    {
        $url = (string) $source->cfg('url');

        return $this->parse(HttpPage::get($url), $url, (int) $source->cfg('limit', 50));
    }

    /** @return array<int, RawListing> */
    public function parse(string $xml, string $feedUrl, int $limit = 50): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            throw new RuntimeException('The feed is not valid RSS or Atom XML.');
        }

        $items = [];

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = $this->fromRss($item, $feedUrl);
            }
        } elseif (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $items[] = $this->fromAtom($entry, $feedUrl);
            }
        } else {
            throw new RuntimeException('The feed has no items.');
        }

        return array_slice(array_values(array_filter($items)), 0, max(1, $limit));
    }

    private function fromRss(SimpleXMLElement $item, string $feedUrl): ?RawListing
    {
        $link = HttpPage::absolute((string) $item->link, $feedUrl);
        $title = RawListing::clean((string) $item->title, 300);
        $content = (string) ($item->children('content', true)->encoded ?? '');
        $text = RawListing::clean($content !== '' ? $content : (string) $item->description, 8000);
        $guid = trim((string) $item->guid) ?: $link ?: $title;

        if ($guid === null || ($title === null && $text === null)) {
            return null;
        }

        return new RawListing(
            externalKey: mb_substr($guid, 0, 191),
            text: trim(($title ?? '')."\n".($text ?? '')),
            url: $link,
            title: $title,
            company: RawListing::clean((string) ($item->children('dc', true)->creator ?? ''), 200),
            location: null,
            postedAt: $this->date((string) $item->pubDate),
        );
    }

    private function fromAtom(SimpleXMLElement $entry, string $feedUrl): ?RawListing
    {
        $link = null;
        foreach ($entry->link as $candidate) {
            $rel = (string) $candidate['rel'];
            if ($rel === '' || $rel === 'alternate') {
                $link = HttpPage::absolute((string) $candidate['href'], $feedUrl);
                break;
            }
        }

        $title = RawListing::clean((string) $entry->title, 300);
        $text = RawListing::clean((string) ($entry->content ?: $entry->summary), 8000);
        $id = trim((string) $entry->id) ?: $link ?: $title;

        if ($id === null || ($title === null && $text === null)) {
            return null;
        }

        return new RawListing(
            externalKey: mb_substr($id, 0, 191),
            text: trim(($title ?? '')."\n".($text ?? '')),
            url: $link,
            title: $title,
            company: RawListing::clean((string) ($entry->author->name ?? ''), 200),
            postedAt: $this->date((string) ($entry->published ?: $entry->updated)),
        );
    }

    private function date(string $value): ?CarbonImmutable
    {
        try {
            return trim($value) === '' ? null : CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function configRules(): array
    {
        return [
            'config.url' => ['required', 'url', 'max:500'],
            'config.limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
