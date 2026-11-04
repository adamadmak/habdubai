<?php

namespace App\Bot;

use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\GoodsMatch;
use App\Models\GoodsPost;
use App\Models\Job;
use App\Models\JobFilter;
use App\Models\Member;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Services\TelegramBotService;
use App\Support\PublicUrls;
use App\Support\RuntimeEnv;

/**
 * A member's home in the bot: the welcome card when the join flow ends, what
 * is new for them on /start, the channel, the groups and the website as buttons,
 * and the menu keyboard that stands for the commands most people never type.
 * The keyboard itself is built by BotMessages, so every flow can put it back
 * when it ends.
 *
 * In a private chat the website opens inside Telegram, already signed in: see
 * PublicUrls::siteButton and the website's TelegramEntry.
 */
class Home
{
    /** Menu button → the command it stands for, in the order the keyboard shows them. */
    public const MENU = [
        'btn_menu_jobs' => 'jobs',
        'btn_menu_goods' => 'goods',
        'btn_menu_ask' => 'ask',
        'btn_menu_places' => 'directory',
        'btn_menu_safe' => 'safe',
        'btn_menu_profile' => 'profile',
    ];

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
        private readonly BotProfile $profile,
    ) {}

    /**
     * The end of the join flow: a picture card carrying the welcome and the
     * links as buttons, then the menu. Two messages, because Telegram puts an
     * inline keyboard and a reply keyboard on different messages. Job alerts
     * start here, from the work they just told us they do.
     */
    public function welcome(Member $member, int|string $chatId): void
    {
        $language = $member->language;
        $filter = JobFilter::forMember($member);

        $caption = $this->messages->text('welcome_card', $language, ['name' => e($member->display_name)]);
        // What the alerts really are: someone coming back has the settings they left with.
        $trades = $filter->alerts_on ? array_map(fn (string $key) => Choices::label(Choices::TRADES, $key, $language), $filter->categories ?? []) : [];
        if ($trades !== []) {
            $caption .= "\n\n".$this->messages->text('welcome_alerts', $language, ['trades' => e(implode(', ', $trades))]);
        }

        $buttons = $this->links($language);
        if ($buttons === []) {
            $caption .= "\n\n".$this->messages->text('links_pending', $language);
        }

        $markup = $buttons === [] ? null : ['inline_keyboard' => $buttons];
        $card = $this->cardUrl($language);
        $sent = false;

        if ($card !== null) {
            try {
                $this->telegram->sendPhotoById($chatId, $card, $caption, $markup);
                $sent = true;
            } catch (\Throwable) {
                // Telegram could not fetch the picture (the website is down, or not public yet): the words still go.
            }
        }

        if (! $sent) {
            $this->telegram->sendText($chatId, $caption, $markup);
        }

        $this->telegram->sendText($chatId, $this->messages->text('menu_hint', $language), $this->messages->menuKeyboard($language));
        $this->personalise($member);
    }

    /** /start from a member who is already in: the greeting with the menu, then what is new for them. */
    public function welcomeBack(Member $member, Conversation $conversation): void
    {
        $this->telegram->sendText($conversation->chat_id, $this->messages->text('returning', $conversation->language, [
            'name' => e($member->display_name),
            'since' => $member->verified_at->format('M Y'),
        ]), $this->messages->menuKeyboard($conversation->language));

        $this->dashboard($member);
        $this->personalise($member);
    }

    /** What is new for this member, in numbers worth acting on, with the way to each. */
    public function dashboard(Member $member): void
    {
        $l = $member->language;
        $filter = JobFilter::forMember($member);

        $jobs = Job::query()->where('status', 'published')->where('published_at', '>=', now()->subDays(7))
            ->latest('published_at')->limit(200)->get()->filter(fn (Job $job) => $filter->matches($job))->count();
        $board = GoodsPost::query()->where('status', 'open')->count();
        $deals = GoodsMatch::query()->whereIn('status', GoodsMatch::LIVE)
            ->where(fn ($q) => $q->where('owner_id', $member->id)->orWhere('requester_id', $member->id))->count();
        $answered = Question::query()->where('member_id', $member->id)->where('status', 'open')->whereHas('replies')->count();
        $event = Announcement::query()->where('status', 'published')->whereNotNull('event_date')
            ->whereDate('event_date', '>=', now('Asia/Dubai')->toDateString())->orderBy('event_date')->first();

        $lines = [$this->messages->text('home_title', $l), ''];
        $lines[] = $jobs > 0 ? $this->messages->text('home_jobs', $l, ['count' => $jobs]) : $this->messages->text('home_jobs_none', $l);
        if ($deals > 0) {
            $lines[] = $this->messages->text('home_deals', $l, ['count' => $deals]);
        }
        if ($board > 0) {
            $lines[] = $this->messages->text('home_goods', $l, ['count' => $board]);
        }
        if ($answered > 0) {
            $lines[] = $this->messages->text('home_answers', $l, ['count' => $answered]);
        }
        if ($event !== null) {
            $lines[] = $this->messages->text('home_event', $l, ['title' => e(str($event->text($l))->squish()->limit(60)->toString()), 'date' => $event->event_date->format('j M')]);
        }

        $rows = [];
        if (($site = PublicUrls::site($l)) !== '') {
            $rows[] = [PublicUrls::siteButton($this->messages->button('btn_open_site', $l), $site)];
        }
        $rows[] = [$this->button('btn_home_jobs', $l, 'jf:latest'), $this->button('btn_home_alerts', $l, 'h:alerts')];
        $rows[] = [$this->button('btn_home_questions', $l, 'h:myq'), $this->button('btn_home_goods', $l, 'h:mine')];

        $this->telegram->sendText($member->chat_id, implode("\n", $lines), ['inline_keyboard' => $rows]);
    }

    /** The buttons under /profile. */
    public function profileKeyboard(string $language): array
    {
        $rows = [];
        if (($page = PublicUrls::site($language.'/account')) !== '') {
            $rows[] = [PublicUrls::siteButton($this->messages->button('btn_my_page', $language), $page)];
        }
        $rows[] = [$this->button('btn_home_alerts', $language, 'h:alerts'), $this->button('btn_profile_language', $language, 'h:lang')];

        return ['inline_keyboard' => $rows];
    }

    /**
     * This member's command menu and Open button, in their language rather than
     * whatever their Telegram app happens to be set to. A courtesy: a refusal
     * changes nothing else.
     */
    public function personalise(Member $member): void
    {
        $chat = (int) $member->chat_id;
        if ($chat === 0) {
            return;
        }

        $this->telegram->setCommands($this->profile->commands($member->language), $chat);

        if (PublicUrls::siteIsPublic()) {
            $this->telegram->setMenuButton($chat, $this->messages->button('btn_menu_open', $member->language), PublicUrls::site($member->language));
        }
    }

    /**
     * A chat's own menu outranks the bot's defaults, so after the website's address or
     * the words change, each joined member's is brought up to date, a few each minute.
     */
    public function refreshChats(int $limit = 30): int
    {
        $after = PlatformSetting::valueFor(BotProfile::CHATS_CURSOR_KEY);
        if ($after === null || $after === '') {
            return 0;
        }

        $members = Member::query()->where('id', '>', (int) $after)->whereNotNull('chat_id')->whereNotNull('agreed_rules_at')->whereNull('left_at')->whereNull('banned_at')
            ->orderBy('id')->limit($limit)->get();

        $members->each(fn (Member $member) => $this->personalise($member));
        PlatformSetting::put(BotProfile::CHATS_CURSOR_KEY, $members->count() < $limit ? null : (string) $members->last()->id);

        return $members->count();
    }

    /** The command a pressed menu label stands for, in any language; null for other text. */
    public function commandFor(?string $text): ?string
    {
        foreach (self::MENU as $key => $command) {
            if ($this->messages->pressed($key, $text)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * The links as buttons: the website first, then the member's own page, the
     * channel and the two groups. Only the ones that are set.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function links(string $language): array
    {
        $rows = [];
        $setting = fn (string $key): string => RuntimeEnv::get(null, $key, null);

        if (($site = PublicUrls::site($language)) !== '') {
            $rows[] = [PublicUrls::siteButton($this->messages->button('btn_open_site', $language), $site)];
        }

        $second = [];
        if (($page = PublicUrls::site($language.'/account')) !== '') {
            $second[] = PublicUrls::siteButton($this->messages->button('btn_my_page', $language), $page);
        }
        if (($channel = $setting('telegram_channel_link')) !== '') {
            $second[] = ['text' => $this->messages->button('btn_open_channel', $language), 'url' => $channel];
        }
        if ($second !== []) {
            $rows[] = $second;
        }

        $groups = [];
        if (($questions = $setting('telegram_questions_group_link')) !== '') {
            $groups[] = ['text' => $this->messages->button('btn_open_questions', $language), 'url' => $questions];
        }
        if (($goods = $setting('telegram_goods_group_link')) !== '') {
            $groups[] = ['text' => $this->messages->button('btn_open_goods', $language), 'url' => $goods];
        }
        if ($groups !== []) {
            $rows[] = $groups;
        }

        return $rows;
    }

    /** The welcome picture the website draws for this language; null until the website has a public https address. */
    public function cardUrl(string $language): ?string
    {
        return PublicUrls::siteIsPublic() ? PublicUrls::site('api/card/'.$language) : null;
    }

    private function button(string $key, string $language, string $data): array
    {
        return ['text' => $this->messages->button($key, $language), 'callback_data' => $data];
    }
}
