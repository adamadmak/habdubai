<?php

namespace App\Jobs\Sources;

use App\Models\JobSource;
use RuntimeException;

/**
 * A Telegram channel or group the bot is a member of. Nothing is polled:
 * Telegram pushes each post to the webhook, and the router hands it to
 * JobIngest. Moderator forwards use the built-in "forwarded" source.
 */
class TelegramSource implements SourceFetcher
{
    /** Words that say a post is about work. Only a text or link post needs them: a photo counts as a job. */
    private const JOB_WORDS = '/hiring|\bhire\b|vacanc|\bjobs?\b|career|recruit|\bposition|\brequired\b|\bneeded\b|\bwanted\b|\bapply\b|\bcv\b|salary|ሥራ|ስራ|ተፈላጊ|ይፈለጋል|ቅጥር|ደመወዝ|ደሞዝ|hojii/iu';

    /** Who shared a link, which these parameters would otherwise publish. */
    private const TRACKING = '/^(utm_|rcm$|trk|ref|fbclid|gclid|igsh|si$|src$|source$)/i';

    public function fetch(JobSource $source): array
    {
        throw new RuntimeException('Telegram sources are pushed by Telegram; there is nothing to fetch.');
    }

    /** One post (channel post, group message or forward) as a listing. */
    public static function listing(array $message, string $keyPrefix): ?RawListing
    {
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (mb_strlen($text) < 30) {
            return null;
        }

        $url = self::postLink($message);
        $firstLine = RawListing::clean(strtok($text, "\n") ?: $text, 300);
        $photo = self::photoFileId($message);

        return new RawListing(
            externalKey: mb_substr($keyPrefix.':'.($url ?? sha1($text)), 0, 191),
            text: RawListing::clean($text, 8000) ?? $text,
            url: $url,
            title: $firstLine,
            photoFileIds: $photo !== null ? [$photo] : [],
        );
    }

    /**
     * A post by someone we trust to post jobs: an admin of our own channel, or a moderator
     * forwarding one. A photo needs no words, a link speaks for itself through its preview, and
     * plain text has to mention work, so "Welcome everyone" does not become a job. #nojob opts out.
     */
    public static function trustedPost(array $message, string $keyPrefix): ?RawListing
    {
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (preg_match('/#nojob\b/iu', $text)) {
            return null;
        }

        $photo = self::photoFileId($message);
        $link = self::externalLink($message);
        $words = trim(preg_replace('#https?://\S+#iu', '', $text) ?? $text);
        $preview = $link !== null && $photo === null ? LinkPreview::read($link) : null;

        if ($photo === null) {
            $known = $words.' '.($preview['title'] ?? '').' '.($preview['description'] ?? '').' '.$link;

            if (! preg_match(self::JOB_WORDS, $known) || ($link === null && mb_strlen($words) < 30)) {
                return null;
            }
        }

        $previewText = trim(($preview['title'] ?? '')."\n".($preview['description'] ?? ''));
        $body = trim($words."\n".$previewText);
        $firstLine = $words !== '' ? (strtok($words, "\n") ?: $words) : ($preview ? LinkPreview::headline($preview) : null);
        $url = $link ?? self::postLink($message);
        $id = $url ?? sha1($text.($photo ?? ''));

        return new RawListing(
            externalKey: mb_substr($keyPrefix.':'.$id, 0, 191),
            text: RawListing::clean($body, 8000) ?? '',
            url: $url,
            title: RawListing::clean($firstLine, 300),
            photoFileIds: $photo !== null ? [$photo] : [],
            photoUrl: $preview['image'] ?? null,
            mediaGroup: isset($message['media_group_id']) ? mb_substr((string) $message['media_group_id'], 0, 64) : null,
            websiteOnly: true,
        );
    }

    /** The largest size of a photo, or an image sent as a file. */
    public static function photoFileId(array $message): ?string
    {
        $sizes = $message['photo'] ?? [];

        if (is_array($sizes) && $sizes !== []) {
            return (string) (end($sizes)['file_id'] ?? '') ?: null;
        }

        $document = $message['document'] ?? null;

        return is_array($document) && str_starts_with((string) ($document['mime_type'] ?? ''), 'image/')
            ? ((string) ($document['file_id'] ?? '') ?: null)
            : null;
    }

    /** The first link to a page elsewhere, without what says who shared it. Telegram's own links are not one. */
    public static function externalLink(array $message): ?string
    {
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        $found = [];

        foreach ($message['entities'] ?? $message['caption_entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') === 'text_link' && isset($entity['url'])) {
                $found[] = (string) $entity['url'];
            }
        }

        if (preg_match_all('#https?://[^\s<>"\']+#iu', $text, $matches)) {
            $found = [...$found, ...$matches[0]];
        }

        foreach ($found as $link) {
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));

            if ($host !== '' && ! preg_match('/(^|\.)(t\.me|telegram\.me)$/', $host)) {
                return self::withoutTracking(rtrim($link, '.,)'));
            }
        }

        return null;
    }

    public static function configRules(): array
    {
        return [
            'config.chat_id' => ['required', 'string', 'max:32'],
        ];
    }

    private static function postLink(array $message): ?string
    {
        $chat = $message['forward_origin']['chat'] ?? $message['forward_from_chat'] ?? $message['chat'] ?? [];
        $messageId = $message['forward_origin']['message_id'] ?? $message['forward_from_message_id'] ?? $message['message_id'] ?? null;
        $username = $chat['username'] ?? null;

        return $username && $messageId ? 'https://t.me/'.$username.'/'.$messageId : null;
    }

    private static function withoutTracking(string $link): string
    {
        $query = (string) parse_url($link, PHP_URL_QUERY);

        if ($query === '') {
            return $link;
        }

        $kept = array_filter(explode('&', html_entity_decode($query)), fn (string $pair) => ! preg_match(self::TRACKING, explode('=', $pair)[0]));
        $base = strtok($link, '?');

        return $kept === [] ? (string) $base : $base.'?'.implode('&', $kept);
    }
}
