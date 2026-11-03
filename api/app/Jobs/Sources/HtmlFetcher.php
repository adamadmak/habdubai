<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A listing page read with CSS selectors the admin configures:
 *
 *   url        the search or listing page
 *   item       selector for one listing on that page
 *   title      selector inside the item (its text is the title)
 *   link       selector inside the item whose href is the listing URL
 *              (defaults to the title element, then the first <a>)
 *   company, location, snippet   optional selectors inside the item
 *
 * Sites change their markup without notice, so a source that finds nothing
 * counts as a failed run; three in a row pause it and an admin is told.
 * Only the summary and a link are ever published, never the page itself.
 */
class HtmlFetcher implements SourceFetcher
{
    public function fetch(JobSource $source): array
    {
        $url = (string) $source->cfg('url');

        return $this->parse(HttpPage::get($url), $url, $source->config ?? []);
    }

    /** @return array<int, RawListing> */
    public function parse(string $html, string $pageUrl, array $config): array
    {
        $crawler = new Crawler($html, $pageUrl);

        try {
            $items = $crawler->filter((string) $config['item']);
        } catch (\Throwable $e) {
            throw new RuntimeException('The item selector is not valid CSS: '.$e->getMessage());
        }

        $limit = max(1, (int) ($config['limit'] ?? 50));
        $listings = [];

        $items->each(function (Crawler $item) use (&$listings, $config, $pageUrl, $limit): void {
            if (count($listings) >= $limit) {
                return;
            }

            $title = RawListing::clean($this->text($item, $config['title'] ?? null), 300);
            $href = $this->href($item, $config['link'] ?? null, $config['title'] ?? null);
            $link = HttpPage::absolute($href, $pageUrl);
            $company = RawListing::clean($this->text($item, $config['company'] ?? null), 200);
            $location = RawListing::clean($this->text($item, $config['location'] ?? null), 200);
            $snippet = RawListing::clean($this->text($item, $config['snippet'] ?? null) ?? $item->text(''), 4000);

            if ($title === null) {
                return;
            }

            $listings[] = new RawListing(
                externalKey: mb_substr($link ?? sha1($title.'|'.$company), 0, 191),
                text: trim(implode("\n", array_filter([$title, $company, $location, $snippet]))),
                url: $link,
                title: $title,
                company: $company,
                location: $location,
            );
        });

        if ($listings === []) {
            throw new RuntimeException('No listings matched the item and title selectors. The site may have changed its page.');
        }

        return $listings;
    }

    private function text(Crawler $item, ?string $selector): ?string
    {
        if ($selector === null || trim($selector) === '') {
            return null;
        }

        try {
            $node = $item->filter($selector);
        } catch (\Throwable) {
            return null;
        }

        return $node->count() > 0 ? $node->first()->text('') : null;
    }

    private function href(Crawler $item, ?string $linkSelector, ?string $titleSelector): ?string
    {
        foreach ([$linkSelector, $titleSelector, 'a'] as $selector) {
            if ($selector === null || trim($selector) === '') {
                continue;
            }

            try {
                $node = $item->filter($selector);
            } catch (\Throwable) {
                continue;
            }

            if ($node->count() === 0) {
                continue;
            }

            $href = $node->first()->attr('href');
            if ($href === null) {
                $anchor = $node->first()->filter('a');
                $href = $anchor->count() > 0 ? $anchor->first()->attr('href') : null;
            }

            if ($href) {
                return $href;
            }
        }

        // The item itself may be the anchor.
        return $item->nodeName() === 'a' ? $item->attr('href') : null;
    }

    public static function configRules(): array
    {
        return [
            'config.url' => ['required', 'url', 'max:500'],
            'config.item' => ['required', 'string', 'max:200'],
            'config.title' => ['required', 'string', 'max:200'],
            'config.link' => ['nullable', 'string', 'max:200'],
            'config.company' => ['nullable', 'string', 'max:200'],
            'config.location' => ['nullable', 'string', 'max:200'],
            'config.snippet' => ['nullable', 'string', 'max:200'],
            'config.limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
