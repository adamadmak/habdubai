<?php

namespace App\Bot;

use App\Jobs\Pipeline\JobCard;
use App\Models\Business;
use App\Models\DirectoryCity;
use App\Models\Job;
use App\Models\JobFilter;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\PublicUrls;
use App\Support\Search;
use App\Support\TelegramText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The bot answering what it understood (see Understand): the jobs, the places,
 * the goods board or the safety numbers a member asked for, straight away. When
 * there is nothing yet it offers to say when there is. Whatever it cannot place
 * goes on to the assistant, and every answer here offers that way out too.
 */
class SmartReply
{
    /** Common words a place's name may contain, which alone are nobody searching for it. */
    private const NOT_NAMES = ['yes', 'yeah', 'yep', 'nope', 'okay', 'good', 'fine', 'nice', 'great', 'thanks', 'thank', 'sorry', 'very', 'much', 'more', 'not', 'you', 'your', 'she', 'him', 'her', 'they', 'them', 'this', 'that', 'who', 'all', 'but', 'with', 'from', 'have', 'has', 'was', 'will', 'just'];

    /** Lets "ask the assistant instead" carry the words of the message it sits under. */
    private string $asked = '';

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly Understand $understand,
        private readonly JobCard $card,
        private readonly JobsFlow $jobs,
        private readonly DirectoryFlow $directory,
        private readonly GoodsFlow $goods,
        private readonly SafetyFlow $safety,
        private readonly AssistantFlow $assistant,
        private readonly Home $home,
    ) {}

    /** True when it answered. False leaves the text for the assistant. */
    public function answer(Member $member, string $text): bool
    {
        $understood = $this->understand->read($text);

        // The numbers go at once, and whatever else is wrong with the message cannot stop them.
        // A sentence about what is happening to them goes on to the assistant as well.
        if ($understood->kind === 'safety') {
            $this->safety->card($member);

            return $understood->direct;
        }

        if (! $understood->direct) {
            return false;
        }

        $this->asked = $text;

        return match ($understood->kind) {
            'hello' => $this->hello($member),
            'thanks' => $this->thanks($member),
            'goods' => $this->goods($member),
            'jobs' => $this->findJobs($member, $understood),
            'places' => $this->findPlaces($member, $understood),
            default => $this->findByName($member, $understood),
        };
    }

    /** "Ask the assistant instead": the words of the message the button sat under, once. */
    public function askAssistant(Member $member, string $ticket): void
    {
        $text = $ticket !== '' ? Cache::pull(self::askKey($member, $ticket)) : null;

        if (is_string($text) && $text !== '') {
            $this->assistant->ask($member, $text);

            return;
        }

        $this->telegram->sendText($member->chat_id, $this->messages->text('find_ask_again', $member->language));
    }

    /** "Tell me when one comes in": this trade joins the member's alerts, and alerts are on. */
    public function alertFor(Member $member, string $trade): void
    {
        if (! isset(Choices::TRADES[$trade])) {
            return;
        }

        $filter = JobFilter::forMember($member);
        $categories = $filter->categories ?? [];
        // An empty list already means every kind of work: nothing to add.
        if ($categories !== [] && ! in_array($trade, $categories, true)) {
            $categories[] = $trade;
        }
        $filter->forceFill(['categories' => array_values($categories), 'alerts_on' => true])->save();

        $l = $member->language;
        $reply = $this->messages->text('find_alert_set', $l, ['what' => e(Choices::label(Choices::TRADES, $trade, $l))]);

        // Their own choice of emirates still applies: the promise says so rather than overreach.
        $areas = array_map(fn (string $emirate) => Choices::label(Choices::JOB_EMIRATES, $emirate, $l), $filter->emirates ?? []);
        if ($areas !== []) {
            $reply .= "\n".$this->messages->text('find_alert_areas', $l, ['areas' => e(implode(', ', $areas))]);
        }

        $this->telegram->sendText($member->chat_id, $reply);
    }

    /** Hello back, with what is new for them: nothing here is worth a call to Claude. */
    private function hello(Member $member): bool
    {
        $this->home->dashboard($member);

        return true;
    }

    private function thanks(Member $member): bool
    {
        $this->telegram->sendText($member->chat_id, $this->messages->text('find_thanks', $member->language), $this->messages->menuKeyboard($member->language));

        return true;
    }

    private function goods(Member $member): bool
    {
        $this->goods->menu($member);

        return true;
    }

    private function findJobs(Member $member, Understood $u): bool
    {
        if ($u->trade === null && $u->emirate === null) {
            $this->jobs->menu($member);

            return true;
        }

        $l = $member->language;
        $what = e(implode(' · ', array_filter([
            $u->trade ? Choices::label(Choices::TRADES, $u->trade, $l) : null,
            $u->emirate ? Choices::label(Choices::JOB_EMIRATES, $u->emirate, $l) : null,
        ])));

        $found = Job::query()->with('source')->where('status', 'published')
            ->when($u->trade, fn ($q) => $q->where('category', $u->trade))
            ->when($u->emirate, fn ($q) => $q->where('emirate', $u->emirate))
            ->latest('published_at')->limit(5)->get();

        $rows = [];
        // One trade can join their alerts at a tap; an emirate alone is chosen in their alert settings.
        $rows[] = [$u->trade !== null
            ? ['text' => $this->messages->button('btn_find_alert', $l), 'callback_data' => 'h:alert:'.$u->trade]
            : ['text' => $this->messages->button('btn_home_alerts', $l), 'callback_data' => 'h:alerts']];
        $page = PublicUrls::site($l.'/jobs?'.http_build_query(array_filter(['category' => $u->trade, 'emirate' => $u->emirate])));
        if ($found->isNotEmpty() && $page !== '') {
            $rows[] = [PublicUrls::siteButton($this->messages->button('btn_find_all_site', $l), $page)];
        }
        if ($u->mayBeTrouble) {
            $rows[] = [['text' => $this->messages->button('btn_find_safe', $l), 'callback_data' => 'h:safe']];
        }
        $rows[] = [$this->askInstead($member)];

        if ($found->isEmpty()) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('find_jobs_none', $l, ['what' => $what]), ['inline_keyboard' => $rows]);

            return true;
        }

        $body = [$this->messages->text('find_jobs_title', $l, ['what' => $what])];
        $n = 0;
        foreach ($found as $job) {
            $body[] = '';
            $body[] = $this->card->entry($job, $l, ++$n);
        }

        $this->telegram->sendText($member->chat_id, TelegramText::clip(implode("\n", $body), 4000), ['inline_keyboard' => $rows]);

        return true;
    }

    private function findPlaces(Member $member, Understood $u): bool
    {
        $l = $member->language;
        $city = $u->cityKey ? DirectoryCity::listed()->firstWhere('key', $u->cityKey) : null;
        $what = e(trim(implode(' · ', array_filter([
            $u->category ? Choices::label(Choices::BUSINESS_CATEGORIES, $u->category, $l) : null,
            $city?->name($l),
        ]))));

        $base = fn () => Business::query()->listed()->with('city')
            ->when($u->category, fn ($q) => $q->where('category', $u->category))
            ->when($city, fn ($q) => $q->where('city_id', $city->id))
            ->bestRatedFirst()->orderByDesc('verified')->orderBy('name')->limit(5);

        // "restaurant deira": the area narrows it down when it can; a word that matches nothing does not empty the list.
        $found = $u->words !== [] ? Search::anyColumn($base(), ['name', 'area', 'description'], implode(' ', $u->words))->get() : collect();
        if ($found->isEmpty()) {
            $found = $base()->get();
        }

        if ($found->isEmpty()) {
            $this->telegram->sendText($member->chat_id, $this->messages->text('find_places_none', $l, ['what' => $what]), ['inline_keyboard' => [
                [['text' => $this->messages->button('btn_dir_add', $l), 'callback_data' => 'd:add']],
                [$this->askInstead($member)],
            ]]);

            return true;
        }

        $this->showPlaces($member, $what, $found, array_filter(['category' => $u->category, 'city' => $u->cityKey]));

        return true;
    }

    /** Words the bot does not know may be the name of a place: "Dukem", "Selam Cargo". */
    private function findByName(Member $member, Understood $u): bool
    {
        $words = array_values(array_filter($u->words, fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, self::NOT_NAMES, true)));

        // "ok", "yes", "thank you": every word gone means nobody named anything.
        if ($words === [] || count($words) !== count($u->words) || count($words) > 4) {
            return false;
        }

        $name = implode(' ', $words);
        $found = Search::anyColumn(Business::query()->listed()->with('city'), ['name'], mb_substr($name, 0, 80))
            ->bestRatedFirst()->orderByDesc('verified')->orderBy('name')->limit(20)->get()
            // Where a word of the name starts: "book" finds Book Corner, "ore" does not find Store.
            ->filter(fn (Business $b): bool => self::startsAWord($b->name, $words))->take(5)->values();

        if ($found->isEmpty()) {
            return false;
        }

        $this->showPlaces($member, e($name), $found, ['q' => $name]);

        return true;
    }

    /** @param array<int, string> $words */
    private static function startsAWord(string $name, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'/iu', $name) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Business>  $found
     * @param  array<string, string>  $query  the same search on the website
     */
    private function showPlaces(Member $member, string $what, Collection $found, array $query): void
    {
        $l = $member->language;
        $rows = $this->directory->numberRows($found, 0);
        $page = PublicUrls::site($l.'/directory'.($query !== [] ? '?'.http_build_query($query) : ''));
        if ($page !== '') {
            $rows[] = [PublicUrls::siteButton($this->messages->button('btn_find_all_site', $l), $page)];
        }
        $rows[] = [$this->askInstead($member)];

        $this->telegram->sendText($member->chat_id, $this->directory->listText($member, $what, $found, 0), ['inline_keyboard' => $rows]);
    }

    /** The button holds a ticket for these words, so pressing it later still asks what this message was about. */
    private function askInstead(Member $member): array
    {
        $ticket = Str::random(8);
        Cache::put(self::askKey($member, $ticket), $this->asked, now()->addMinutes(30));

        return ['text' => $this->messages->button('btn_find_ask_ai', $member->language), 'callback_data' => 'h:ai:'.$ticket];
    }

    private static function askKey(Member $member, string $ticket): string
    {
        return 'smart-ask:'.$member->id.':'.$ticket;
    }
}
