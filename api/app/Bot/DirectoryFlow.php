<?php

namespace App\Bot;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\Conversation;
use App\Models\DirectoryCity;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\MapLocation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * The Habesha business directory in the bot.
 *
 * Browsing is one message edited in place: city → kind of business → a page
 * of eight → one business as a map pin with its details. Suggesting a
 * business is a short flow (name, city, kind, area, pin, phone, one line)
 * that ends in the moderators' queue; nothing a member types is listed
 * before a moderator approves it.
 */
class DirectoryFlow
{
    public const PER_PAGE = 8;

    public const STATES = ['biz_name', 'biz_city', 'biz_category', 'biz_area', 'biz_pin', 'biz_phone', 'biz_description', 'biz_confirm'];

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    // ---- browsing -----------------------------------------------------------

    public function cities(Member $member, ?int $editMessageId = null): void
    {
        $l = $member->language;
        $counts = Business::query()->listed()->selectRaw('city_id, count(*) as n')->groupBy('city_id')->pluck('n', 'city_id');

        $buttons = DirectoryCity::listed()->map(fn (DirectoryCity $c) => [
            'text' => $c->name($l).' ('.($counts[$c->id] ?? 0).')',
            'callback_data' => 'd:city:'.$c->id,
        ])->all();

        $rows = array_chunk($buttons, 2);
        $rows[] = [$this->btn('btn_dir_search', $l, 'd:search'), $this->btn('btn_dir_add', $l, 'd:add')];

        $this->show($member, $this->messages->text('dir_pick_city', $l), ['inline_keyboard' => $rows], $editMessageId);
    }

    public function categories(Member $member, DirectoryCity $city, ?int $editMessageId = null): void
    {
        $l = $member->language;
        $counts = Business::query()->listed()->where('city_id', $city->id)
            ->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');

        $back = [$this->btn('btn_dir_cities', $l, 'd:cities'), $this->btn('btn_dir_add', $l, 'd:add')];

        if ($counts->isEmpty()) {
            $this->show($member, $this->messages->text('dir_city_empty', $l, ['city' => e($city->name($l))]), ['inline_keyboard' => [$back]], $editMessageId);

            return;
        }

        $buttons = [];
        foreach (Choices::BUSINESS_CATEGORIES as $key => $labels) {
            if (($counts[$key] ?? 0) > 0) {
                $buttons[] = ['text' => ($labels[$l] ?? $labels['en']).' ('.$counts[$key].')', 'callback_data' => 'd:cat:'.$city->id.':'.$key.':1'];
            }
        }

        $rows = array_chunk($buttons, 2);
        $rows[] = $back;

        $this->show($member, $this->messages->text('dir_pick_category', $l, ['city' => e($city->name($l))]), ['inline_keyboard' => $rows], $editMessageId);
    }

    public function listing(Member $member, DirectoryCity $city, string $category, int $page, ?int $editMessageId = null): void
    {
        $l = $member->language;
        $query = Business::query()->listed()->where('city_id', $city->id)->where('category', $category)
            ->orderByDesc('verified')->orderBy('name');

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $pages));
        $businesses = $query->with('city')->forPage($page, self::PER_PAGE)->get();

        $title = $this->messages->text('dir_list_title', $l, [
            'category' => Choices::label(Choices::BUSINESS_CATEGORIES, $category, $l),
            'city' => $city->name($l),
        ]);

        $text = $this->listText($member, e($title).($pages > 1 ? ' · '.$page.'/'.$pages : ''), $businesses, ($page - 1) * self::PER_PAGE);

        $rows = $this->numberRows($businesses, ($page - 1) * self::PER_PAGE);
        $nav = [];
        if ($page > 1) {
            $nav[] = $this->btn('btn_dir_prev', $l, 'd:cat:'.$city->id.':'.$category.':'.($page - 1));
        }
        if ($page < $pages) {
            $nav[] = $this->btn('btn_dir_next', $l, 'd:cat:'.$city->id.':'.$category.':'.($page + 1));
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = [$this->btn('btn_dir_back', $l, 'd:city:'.$city->id)];

        $this->show($member, $text, ['inline_keyboard' => $rows], $editMessageId);
    }

    /** One business: a map pin with its details when we have a pin, a text card otherwise. */
    public function card(Member $member, Business $business): void
    {
        $l = $member->language;
        $business->loadMissing('city');

        $site = $business->slug ? \App\Support\PublicUrls::site($l.'/directory/'.$business->slug) : '';
        $keyboard = ['inline_keyboard' => array_values(array_filter([
            [['text' => $this->messages->button('btn_dir_map', $l), 'url' => $business->mapLink()]],
            // The full page: photo, hours, languages and every way to reach them.
            $site !== '' ? [['text' => $this->messages->button('btn_dir_open_site', $l), 'url' => $site]] : null,
            [$this->btn('btn_dir_report', $l, 'd:rep:'.$business->id)],
        ]))];

        if ($business->hasPin()) {
            try {
                $this->telegram->sendVenue($member->chat_id, $business->latitude, $business->longitude, $business->name, $this->address($business, $l));
            } catch (\Throwable) {
                // A venue the client refuses is still worth showing as text.
            }
        }

        $this->telegram->sendText($member->chat_id, $this->cardText($business, $l), $keyboard);
    }

    public function askSearch(Member $member, Conversation $conversation): void
    {
        $conversation->moveTo('dir_search');
        $this->telegram->sendText($member->chat_id, $this->messages->text('dir_search_ask', $member->language));
    }

    public function search(Member $member, Conversation $conversation, string $text): void
    {
        $l = $member->language;
        $query = trim($text);

        if (mb_strlen($query) < 2) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('dir_search_short', $l));

            return;
        }

        $conversation->reset();

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($query)).'%';
        $businesses = Business::query()->listed()->with('city')
            ->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(area) like ?', [$like]))
            ->orderByDesc('verified')->orderBy('name')->limit(self::PER_PAGE)->get();

        if ($businesses->isEmpty()) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('dir_search_none', $l, ['query' => e($query)]),
                ['inline_keyboard' => [[$this->btn('btn_dir_add', $l, 'd:add'), $this->btn('btn_dir_cities', $l, 'd:cities')]]]);

            return;
        }

        $title = e($this->messages->text('dir_search_title', $l, ['query' => $query]));
        $rows = $this->numberRows($businesses, 0);
        $rows[] = [$this->btn('btn_dir_cities', $l, 'd:cities')];

        $this->telegram->sendText($member->chat_id, $this->listText($member, $title, $businesses, 0), ['inline_keyboard' => $rows]);
    }

    /** A directory button (data starts with "d:"). */
    public function button(Member $member, Conversation $conversation, Callback $cb): void
    {
        $p = $cb->parts();
        $l = $member->language;

        switch ($p[1] ?? 'cities') {
            case 'city':
                $city = DirectoryCity::query()->find((int) ($p[2] ?? 0));
                $city ? $this->categories($member, $city, $cb->messageId) : $this->cities($member, $cb->messageId);
                break;
            case 'cat':
                $city = DirectoryCity::query()->find((int) ($p[2] ?? 0));
                $category = $p[3] ?? '';
                if ($city && array_key_exists($category, Choices::BUSINESS_CATEGORIES)) {
                    $this->listing($member, $city, $category, (int) ($p[4] ?? 1), $cb->messageId);
                } else {
                    $this->cities($member, $cb->messageId);
                }
                break;
            case 'b':
                $business = Business::query()->listed()->find((int) ($p[2] ?? 0));
                if (! $business) {
                    $this->telegram->answerCallback($cb->id, $this->messages->text('dir_gone', $l), alert: true);

                    return;
                }
                $this->card($member, $business);
                break;
            case 'rep':
                $this->report($member, $cb, (int) ($p[2] ?? 0));

                return;
            case 'search':
                $this->askSearch($member, $conversation);
                break;
            case 'add':
                $this->beginSuggest($member, $conversation);
                break;
            default:
                $this->cities($member, $cb->messageId);
        }

        $this->telegram->answerCallback($cb->id);
    }

    private function report(Member $member, Callback $cb, int $businessId): void
    {
        $l = $member->language;
        $business = Business::query()->find($businessId);

        if (! $business || $business->status !== 'approved') {
            $this->telegram->answerCallback($cb->id, $this->messages->text('dir_gone', $l), alert: true);

            return;
        }

        try {
            BusinessReport::query()->create(['member_id' => $member->id, 'business_id' => $business->id, 'reason' => 'wrong', 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            $this->telegram->answerCallback($cb->id, $this->messages->text('dir_already_reported', $l), alert: true);

            return;
        }

        $count = BusinessReport::query()->where('business_id', $business->id)->count();
        $business->forceFill(['reports_count' => $count])->save();

        // A verified business needs a moderator to take it down; reports alone do not.
        if ($count >= Business::HIDE_THRESHOLD && ! $business->verified) {
            $business->forceFill(['status' => 'hidden', 'review_note' => $count.' members reported wrong information.'])->save();
            AuditLog::record(null, 'directory.hidden_by_reports', 'business', $business->id, null, ['reports' => $count]);
        }

        $this->telegram->answerCallback($cb->id, $this->messages->text('dir_reported', $l), alert: true);
    }

    // ---- suggesting a business ----------------------------------------------

    public function beginSuggest(Member $member, Conversation $conversation): void
    {
        $l = $member->language;
        $pending = Business::query()->where('submitted_by', $member->id)->where('status', 'pending')->count();

        if ($pending >= Business::MAX_PENDING_PER_MEMBER) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('biz_too_many', $l, ['count' => $pending]));

            return;
        }

        $conversation->forceFill(['state' => 'biz_name', 'data' => []])->save();
        $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_name', $l), $this->cancelOnly($l));
    }

    public function handle(Member $member, Conversation $conversation, Incoming $in): void
    {
        $l = $member->language;
        $text = trim((string) $in->text);
        $skip = $this->messages->pressed('btn_skip', $text);

        switch ($conversation->state) {
            case 'biz_name':
                if (mb_strlen($text) < 2 || mb_strlen($text) > 80 || $this->hasLink($text)) {
                    $this->say($member, 'biz_name_invalid');

                    return;
                }
                $conversation->moveTo('biz_city', ['name' => $text]);
                $this->askCity($member);

                return;

            case 'biz_city':
                $city = DirectoryCity::fromLabel($text);
                if (! $city) {
                    $this->askCity($member);

                    return;
                }
                $conversation->moveTo('biz_category', ['city_id' => $city->id]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_category', $l),
                    $this->telegram->replyKeyboard([...Choices::rows(Choices::BUSINESS_CATEGORIES, $l), [$this->messages->button('btn_cancel', $l)]]));

                return;

            case 'biz_category':
                $category = Choices::keyFromLabel(Choices::BUSINESS_CATEGORIES, $text);
                if (! $category) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_category', $l),
                        $this->telegram->replyKeyboard([...Choices::rows(Choices::BUSINESS_CATEGORIES, $l), [$this->messages->button('btn_cancel', $l)]]));

                    return;
                }
                if ($this->isDuplicate((string) $conversation->recall('name'), (int) $conversation->recall('city_id'))) {
                    $conversation->reset();
                    $this->say($member, 'biz_duplicate', [], $this->telegram->removeKeyboard());

                    return;
                }
                $conversation->moveTo('biz_area', ['category' => $category]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_area', $l), $this->cancelOnly($l));

                return;

            case 'biz_area':
                if (mb_strlen($text) < 3 || mb_strlen($text) > 150 || $this->hasLink($text)) {
                    $this->say($member, 'biz_area_invalid');

                    return;
                }
                $conversation->moveTo('biz_pin', ['area' => $text]);
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_pin', $l), $this->skipKeyboard($l));

                return;

            case 'biz_pin':
                $pin = $in->location();
                if ($pin && MapLocation::valid($pin['latitude'], $pin['longitude'])) {
                    $conversation->moveTo('biz_phone', ['latitude' => $pin['latitude'], 'longitude' => $pin['longitude'], 'maps_url' => null]);
                } elseif ($skip) {
                    $conversation->moveTo('biz_phone');
                } elseif ($link = MapLocation::fromLink($text)) {
                    $conversation->moveTo('biz_phone', $link);
                } else {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('biz_pin_invalid', $l), $this->skipKeyboard($l));

                    return;
                }
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_phone', $l), $this->skipKeyboard($l));

                return;

            case 'biz_phone':
                if (! $skip) {
                    $phone = MapLocation::phone($text);
                    if ($phone === null) {
                        $this->telegram->sendText($member->chat_id, $this->messages->text('biz_phone_invalid', $l), $this->skipKeyboard($l));

                        return;
                    }
                    $conversation->remember('phone', $phone);
                }
                $conversation->moveTo('biz_description');
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_description', $l), $this->skipKeyboard($l));

                return;

            case 'biz_description':
                if (! $skip) {
                    if (mb_strlen($text) > 250 || $this->hasLink($text) || $text === '') {
                        $this->telegram->sendText($member->chat_id, $this->messages->text('biz_description_invalid', $l), $this->skipKeyboard($l));

                        return;
                    }
                    $conversation->remember('description', $text);
                }
                $conversation->moveTo('biz_confirm');
                $preview = $this->draft($conversation);
                $this->telegram->sendText($member->chat_id, $this->messages->text('biz_confirm', $l, ['card' => $this->cardText($preview, $l)]),
                    $this->telegram->replyKeyboard([[$this->messages->button('btn_send', $l), $this->messages->button('btn_cancel', $l)]]));

                return;

            case 'biz_confirm':
                if (! $this->messages->pressed('btn_send', $text)) {
                    $this->telegram->sendText($member->chat_id, $this->messages->text('biz_confirm', $l, ['card' => $this->cardText($this->draft($conversation), $l)]),
                        $this->telegram->replyKeyboard([[$this->messages->button('btn_send', $l), $this->messages->button('btn_cancel', $l)]]));

                    return;
                }
                $business = $this->draft($conversation);
                $business->forceFill(['status' => 'pending', 'submitted_by' => $member->id])->save();
                AuditLog::record(null, 'directory.suggested', 'business', $business->id, null, ['member_id' => $member->id, 'name' => $business->name]);
                $conversation->reset();
                $this->say($member, 'biz_submitted', [], $this->telegram->removeKeyboard());

                return;
        }
    }

    // ---- rendering ----------------------------------------------------------

    public function cardText(Business $b, string $l): string
    {
        $b->loadMissing('city');

        $lines = ['<b>'.e($b->name).'</b>'.($b->verified ? '  '.$this->messages->text('dir_verified', $l) : '')];
        $lines[] = e(Choices::label(Choices::BUSINESS_CATEGORIES, $b->category, $l));
        $lines[] = '📍 '.e($this->address($b, $l));
        if ($b->phone) {
            $lines[] = '📞 '.e($b->phone);
        }
        if ($b->telegram) {
            $lines[] = '💬 @'.e(ltrim($b->telegram, '@'));
        }
        if ($b->description) {
            $lines[] = '';
            $lines[] = e($b->description);
        }

        return implode("\n", $lines);
    }

    private function address(Business $b, string $l): string
    {
        return trim($b->area.($b->city ? ', '.$b->city->name($l) : ''));
    }

    /** @param Collection<int, Business> $businesses */
    private function listText(Member $member, string $title, Collection $businesses, int $offset): string
    {
        $l = $member->language;
        $lines = ['🏪 <b>'.$title.'</b>'];

        foreach ($businesses->values() as $i => $b) {
            $lines[] = '';
            $lines[] = ($offset + $i + 1).'. <b>'.e($b->name).'</b>'.($b->verified ? ' ✅' : '');
            $lines[] = '📍 '.e($this->address($b, $l)).($b->phone ? ' · 📞 '.e($b->phone) : '');
        }

        $lines[] = '';
        $lines[] = $this->messages->text('dir_list_footer', $l);

        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    /** @param Collection<int, Business> $businesses */
    private function numberRows(Collection $businesses, int $offset): array
    {
        $buttons = $businesses->values()->map(fn (Business $b, int $i) => ['text' => (string) ($offset + $i + 1), 'callback_data' => 'd:b:'.$b->id])->all();

        return array_chunk($buttons, 4);
    }

    private function draft(Conversation $conversation): Business
    {
        $d = $conversation->data ?? [];

        return new Business([
            'city_id' => $d['city_id'] ?? null,
            'category' => $d['category'] ?? 'other',
            'name' => $d['name'] ?? '',
            'area' => $d['area'] ?? '',
            'latitude' => $d['latitude'] ?? null,
            'longitude' => $d['longitude'] ?? null,
            'maps_url' => $d['maps_url'] ?? null,
            'phone' => $d['phone'] ?? null,
            'description' => $d['description'] ?? null,
        ]);
    }

    private function isDuplicate(string $name, int $cityId): bool
    {
        $norm = fn (string $v) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($v)) ?? '');

        return Business::query()->where('city_id', $cityId)->whereIn('status', ['pending', 'approved', 'hidden'])->get(['name'])
            ->contains(fn (Business $b) => $norm($b->name) === $norm($name));
    }

    private function hasLink(string $text): bool
    {
        return (bool) preg_match('/(https?:|www\.|t\.me\/)/i', $text);
    }

    private function askCity(Member $member): void
    {
        $l = $member->language;
        $names = DirectoryCity::listed()->map(fn (DirectoryCity $c) => $c->name($l))->all();
        $rows = array_chunk($names, 2);
        $rows[] = [$this->messages->button('btn_cancel', $l)];

        $this->telegram->sendText($member->chat_id, $this->messages->text('biz_ask_city', $l), $this->telegram->replyKeyboard($rows, oneTime: false));
    }

    private function cancelOnly(string $l): array
    {
        return $this->telegram->replyKeyboard([[$this->messages->button('btn_cancel', $l)]], oneTime: false);
    }

    private function skipKeyboard(string $l): array
    {
        return $this->telegram->replyKeyboard([[$this->messages->button('btn_skip', $l), $this->messages->button('btn_cancel', $l)]], oneTime: false);
    }

    private function say(Member $member, string $key, array $vars = [], ?array $markup = null): void
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text($key, $member->language, $vars), $markup);
    }

    private function show(Member $member, string $text, array $keyboard, ?int $editMessageId): void
    {
        if ($editMessageId && $this->telegram->editText($member->chat_id, $editMessageId, $text, $keyboard)) {
            return;
        }

        $this->telegram->sendText($member->chat_id, $text, $keyboard);
    }

    private function btn(string $key, string $language, string $data): array
    {
        return ['text' => $this->messages->button($key, $language), 'callback_data' => $data];
    }
}
