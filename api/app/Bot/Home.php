<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\Member;
use App\Services\TelegramBotService;
use App\Support\PublicUrls;
use App\Support\RuntimeEnv;

/**
 * A member's home in the bot: the welcome card when the join flow ends, the
 * welcome back on /start, the channel, the groups and the website as
 * buttons, and the menu keyboard that stands for the commands most people
 * never type. The keyboard itself is built by BotMessages, so every flow can
 * put it back when it ends.
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
    ) {}

    /**
     * The end of the join flow: a picture card carrying the welcome and the
     * links as buttons, then the menu. Two messages, because Telegram puts an
     * inline keyboard and a reply keyboard on different messages.
     */
    public function welcome(Member $member, int|string $chatId): void
    {
        $language = $member->language;
        $caption = $this->messages->text('welcome_card', $language, ['name' => e($member->display_name)]);
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
    }

    /** /start from a member who is already in: the greeting, with the menu. */
    public function welcomeBack(Member $member, Conversation $conversation): void
    {
        $this->telegram->sendText($conversation->chat_id, $this->messages->text('returning', $conversation->language, [
            'name' => e($member->display_name),
            'since' => $member->verified_at->format('M Y'),
        ]), $this->messages->menuKeyboard($conversation->language));
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
     * The links as buttons: the channel, the two groups, the website and the
     * member's own page on it. Only the ones that are set.
     *
     * @return array<int, array<int, array{text: string, url: string}>>
     */
    public function links(string $language): array
    {
        $rows = [];
        $setting = fn (string $key): string => RuntimeEnv::get(null, $key, null);

        if (($channel = $setting('telegram_channel_link')) !== '') {
            $rows[] = [['text' => $this->messages->button('btn_open_channel', $language), 'url' => $channel]];
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

        if (($site = PublicUrls::site()) !== '') {
            $rows[] = [
                ['text' => $this->messages->button('btn_open_site', $language), 'url' => $site.'/'.$language],
                ['text' => $this->messages->button('btn_my_page', $language), 'url' => $site.'/'.$language.'/account'],
            ];
        }

        return $rows;
    }

    /** The welcome picture the website draws for this language; null until the website has a public https address. */
    public function cardUrl(string $language): ?string
    {
        $site = PublicUrls::site();
        $host = (string) parse_url($site, PHP_URL_HOST);

        if (! str_starts_with($site, 'https://') || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        return $site.'/api/card/'.$language;
    }
}
