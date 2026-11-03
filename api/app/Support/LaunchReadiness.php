<?php

namespace App\Support;

use App\Models\AnnouncementTopic;
use App\Models\Business;
use App\Models\FaqEntry;
use App\Models\JobSource;
use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Services\TelegramBotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The launch checklist: everything that has to be true before members are
 * invited, checked against the running API, each with what is wrong and
 * where in the admin to put it right. Values are never shown, only whether
 * they are set and sound; Telegram is asked about the bot, the webhook and
 * the bot's rights in each chat.
 *
 * A blocker failing means not ready. The score weighs blockers 3, important
 * items 2 and the rest 1.
 */
final class LaunchReadiness
{
    public const WEIGHTS = ['blocker' => 3, 'important' => 2, 'nice' => 1];

    /** A tick older than this means the scheduler has stopped. */
    public const TICK_MAX_SECONDS = 180;

    public const QUEUE_LIMIT = 200;

    /** A webhook delivery error younger than this still counts. */
    public const WEBHOOK_ERROR_MINUTES = 30;

    /** What getMe said, kept ten minutes per token. */
    public const GETME_SECONDS = 600;

    /** Rights the bot needs in a community group: removing messages, muting and removing members. */
    private const GROUP_RIGHTS = ['can_delete_messages' => 'delete messages', 'can_restrict_members' => 'ban users'];

    public function __construct(private readonly TelegramBotService $telegram) {}

    /** @return array{score: int, ready: bool, items: array<int, array{key: string, label: string, ok: bool, severity: string, detail: string, fix: string, href: ?string}>} */
    public function report(): array
    {
        $items = [];

        foreach ($this->checks() as [$key, $label, $severity, $href, $check]) {
            try {
                [$ok, $detail, $fix] = $check();
            } catch (\Throwable $e) {
                report($e);
                [$ok, $detail, $fix] = [false, 'The check itself failed; the API log has the details.', 'Look at the API log, then check again.'];
            }

            $items[] = [
                'key' => $key,
                'label' => $label,
                'ok' => (bool) $ok,
                'severity' => $severity,
                'detail' => (string) $detail,
                'fix' => $ok ? '' : (string) $fix,
                'href' => $href,
            ];
        }

        $total = array_sum(array_map(fn (array $i) => self::WEIGHTS[$i['severity']], $items));
        $passed = array_sum(array_map(fn (array $i) => $i['ok'] ? self::WEIGHTS[$i['severity']] : 0, $items));

        return [
            'score' => $total > 0 ? (int) round(100 * $passed / $total) : 0,
            'ready' => ! collect($items)->contains(fn (array $i) => $i['severity'] === 'blocker' && ! $i['ok']),
            'items' => $items,
        ];
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: ?string, 4: \Closure(): array{0: bool, 1: string, 2: string}}> */
    private function checks(): array
    {
        return [
            ['bot_token', 'Telegram bot token', 'blocker', '/settings', fn () => $this->botToken()],
            ['webhook', 'Telegram webhook', 'blocker', '/settings', fn () => $this->webhook()],
            ['public_url', 'Public address of the API', 'blocker', '/settings', fn () => $this->publicUrl()],
            ['site_url', 'Website address', 'important', '/settings', fn () => $this->siteUrl()],
            ['scheduler', 'Scheduler ticking', 'blocker', '/settings', fn () => $this->scheduler()],
            ['tick_token', 'Tick token', 'blocker', '/settings', fn () => $this->secret('tick_token', 'services.host.tick_token', 'TICK_TOKEN', 32, 'Tick token')],
            ['site_api_key', 'Website key', 'important', '/settings', fn () => $this->secret('site_api_key', 'services.host.site_api_key', 'SITE_API_KEY', 24, 'Website key')],
            ['jobs_channel', 'Jobs channel', 'blocker', '/job-settings', fn () => $this->jobsChannel()],
            ['questions_group', 'Questions group', 'blocker', '/settings', fn () => $this->group(Community::questionsGroup(), 'questions group', 'telegram_questions_group_id', topics: true)],
            ['goods_group', 'Goods group', 'important', '/settings', fn () => $this->group(Community::goodsGroup(), 'goods group', 'telegram_goods_group_id', topics: false)],
            ['job_sources', 'Job sources', 'important', '/job-sources', fn () => $this->jobSources()],
            ['anthropic_key', 'Claude (Anthropic) key', 'important', '/job-settings', fn () => $this->anthropic()],
            ['admin_user', 'An administrator account', 'blocker', null, fn () => $this->adminUser()],
            ['support_username', 'Support contact', 'important', '/settings', fn () => $this->supportUsername()],
            ['safety_phones', 'Safety phone numbers', 'nice', '/settings', fn () => $this->safetyPhones()],
            ['knowledge', 'Assistant knowledge', 'nice', '/faq', fn () => $this->knowledge()],
            ['migrations', 'Database up to date', 'blocker', null, fn () => $this->migrations()],
            ['queue_backlog', 'Queue keeping up', 'important', null, fn () => $this->queue()],
            ['failed_updates', 'Telegram updates handled', 'important', '/dashboard', fn () => $this->failedUpdates()],
            ['directory', 'Business directory', 'nice', '/directory', fn () => $this->directory()],
            ['announcement_topics', 'Announcement topics', 'important', '/announcements', fn () => $this->announcementTopics()],
            ['debug_off', 'Error details hidden', 'blocker', null, fn () => $this->debugOff()],
        ];
    }

    // ---- Telegram ----------------------------------------------------------------

    /** @return array{0: bool, 1: string, 2: string} */
    private function botToken(): array
    {
        if (! $this->telegram->configured()) {
            return [false, 'No bot token is saved, so the bot cannot send or receive anything.', 'Paste the token from @BotFather on Settings and save it.'];
        }

        $me = $this->me();
        if (isset($me['error'])) {
            return [false, 'Telegram refused the saved token: '.$me['error'], 'Get a fresh token from @BotFather (/token) and save it on Settings.'];
        }

        return [true, 'Signed in to Telegram as @'.$me['username'].'.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function webhook(): array
    {
        if (! $this->telegram->configured()) {
            return [false, 'There is no bot token yet, so there is no webhook.', 'Save the bot token on Settings first; the webhook is registered with it.'];
        }

        try {
            $info = $this->telegram->getWebhookInfo(quick: true);
        } catch (\RuntimeException $e) {
            return [false, 'Telegram could not be asked about the webhook: '.str($e->getMessage())->limit(160).'.', 'Check the bot token on Settings, then check again.'];
        }
        $url = (string) ($info['url'] ?? '');
        $base = rtrim(RuntimeEnv::get(null, 'public_url', 'APP_URL', (string) config('app.url')), '/');

        if ($url === '') {
            return [false, 'No webhook is registered: Telegram is not sending messages to this API.', 'Set the public address on Settings, then press "Register webhook".'];
        }

        // The path carries the webhook secret: compare it, never show it.
        if ($base === '' || ! str_starts_with($url, $base.'/api/telegram/webhook/')) {
            $host = (string) parse_url($url, PHP_URL_HOST);

            return [false, 'Telegram sends updates to '.($host !== '' ? $host : 'another address').', not to this API.', 'Check the public address on Settings, then press "Register webhook" again.'];
        }

        $pending = (int) ($info['pending_update_count'] ?? 0);
        $errorAt = isset($info['last_error_date']) ? CarbonImmutable::createFromTimestamp((int) $info['last_error_date']) : null;
        if ($errorAt !== null && $errorAt->greaterThan(now()->subMinutes(self::WEBHOOK_ERROR_MINUTES))) {
            $minutes = max(1, (int) $errorAt->diffInMinutes(now(), true));

            return [false, 'Telegram could not deliver an update '.$minutes.' minute(s) ago: '.str((string) ($info['last_error_message'] ?? 'no reason given'))->limit(160).'. '.$pending.' update(s) waiting.',
                'Make sure the API is up and answering (GET /api/health). Telegram retries by itself; this passes '.self::WEBHOOK_ERROR_MINUTES.' minutes after the last failed delivery.'];
        }

        return [true, 'Registered to this API'.($pending > 0 ? '; '.$pending.' update(s) waiting to be delivered.' : ', nothing waiting.'), ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function jobsChannel(): array
    {
        $channel = JobSettings::channel();
        if ($channel === '') {
            return [false, 'No jobs channel is set: jobs are published on the website only, and announcements reach no channel.', 'Add the bot to the channel as an administrator, then enter the channel on Job settings.'];
        }

        return $this->botIsAdmin($channel, 'jobs channel', rights: ['can_post_messages' => 'post messages']);
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function group(string $chatId, string $name, string $setting, bool $topics): array
    {
        if ($chatId === '') {
            return [false, 'No '.$name.' is set.', 'Add the bot to the '.$name.' as an administrator, then pick the group on Settings.'];
        }

        $rights = self::GROUP_RIGHTS + ($topics ? ['can_manage_topics' => 'manage topics'] : []);
        [$ok, $detail, $fix] = $this->botIsAdmin($chatId, $name, $rights);
        if (! $ok || ! $topics) {
            return [$ok, $detail, $fix];
        }

        $chat = $this->telegram->getChat($chatId);
        if (empty($chat['is_forum'])) {
            return [false, 'The '.$name.' has Topics turned off, so each question cannot get its own topic.', 'In Telegram, open the group\'s settings and turn on Topics.'];
        }

        return [true, $detail.' Topics are on.', ''];
    }

    /**
     * @param  array<string, string>  $rights  Telegram's permission flag => what the operator sees in Telegram
     * @return array{0: bool, 1: string, 2: string}
     */
    private function botIsAdmin(string $chatId, string $name, array $rights): array
    {
        $me = $this->me();
        if (isset($me['error']) || ! isset($me['id'])) {
            return [false, 'The bot token is missing or refused, so the '.$name.' cannot be checked.', 'Fix the bot token on Settings first.'];
        }

        try {
            $member = $this->telegram->getChatMember($chatId, (int) $me['id']);
        } catch (\RuntimeException $e) {
            return [false, 'Telegram cannot find the '.$name.' or the bot is not in it ('.str($e->getMessage())->limit(120).').', 'Add the bot to the '.$name.' as an administrator, then check the id on Settings.'];
        }

        $status = (string) ($member['status'] ?? '');
        if (! in_array($status, ['administrator', 'creator'], true)) {
            return [false, 'The bot is in the '.$name.' but is not an administrator ('.($status !== '' ? $status : 'unknown').').', 'In Telegram, make the bot an administrator of the '.$name.'.'];
        }

        $missing = $status === 'creator' ? [] : array_values(array_filter($rights, fn (string $label, string $flag) => empty($member[$flag]), ARRAY_FILTER_USE_BOTH));
        if ($missing !== []) {
            return [false, 'The bot is an administrator of the '.$name.' but may not '.implode(', ', $missing).'.', 'In Telegram, edit the bot\'s administrator rights in the '.$name.' and allow: '.implode(', ', $missing).'.'];
        }

        return [true, 'The bot is an administrator of the '.$name.'.', ''];
    }

    /** getMe, kept ten minutes per token (a success only: a fixed token is seen at once). */
    private function me(): array
    {
        if (! $this->telegram->configured()) {
            return ['error' => 'no token'];
        }

        $key = 'readiness.getme.'.$this->telegram->tokenFingerprint();
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['id'])) {
            return $cached;
        }

        try {
            $result = $this->telegram->getMe();
        } catch (\RuntimeException $e) {
            return ['error' => str($e->getMessage())->limit(160)->toString()];
        }

        // Plain values only: the cache will not restore an object it does not know.
        $me = ['id' => (int) ($result['id'] ?? 0), 'username' => (string) ($result['username'] ?? '')];
        if ($me['id'] > 0) {
            Cache::put($key, $me, self::GETME_SECONDS);
        }

        return $me;
    }

    // ---- addresses and secrets ----------------------------------------------------------

    /** @return array{0: bool, 1: string, 2: string} */
    private function publicUrl(): array
    {
        $url = rtrim(RuntimeEnv::get(null, 'public_url', null), '/');

        if ($url === '') {
            return [false, 'The API\'s public address is not set: the webhook and photo links need it.', 'Enter the API\'s https address on Settings.'];
        }
        if (($problem = $this->addressProblem($url)) !== null) {
            return [false, 'The API\'s public address '.$problem.'.', 'Enter the API\'s public https address on Settings.'];
        }

        return [true, 'Set to '.$url.'.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function siteUrl(): array
    {
        $url = rtrim(PublicUrls::site(), '/');

        if ($url === '') {
            return [false, 'The website\'s address is not set: bot and channel buttons cannot link to it, and owner sign-in has no address to show.', 'Enter the website\'s https address on Settings.'];
        }
        if (($problem = $this->addressProblem($url)) !== null) {
            return [false, 'The website\'s address '.$problem.'.', 'Enter the website\'s public https address on Settings.'];
        }

        return [true, 'Set to '.$url.'.', ''];
    }

    /** Why an address cannot be the public one, or null when it can. */
    private function addressProblem(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            ! str_starts_with(strtolower($url), 'https://') => 'is not https',
            $host === '' => 'is not a valid address',
            in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'], true)
                || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.test') => 'points at this computer, which Telegram and visitors cannot reach',
            default => null,
        };
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function secret(string $setting, string $config, string $env, int $minLength, string $name): array
    {
        $value = RuntimeEnv::get($config, $setting, $env);

        if ($value === '') {
            return [false, $name.' is not set.', 'Generate a long random value, save it on Settings, and set the same value on the website ('.$env.'), then rebuild the website.'];
        }
        if (strlen($value) < $minLength) {
            return [false, $name.' is shorter than '.$minLength.' characters, so the API ignores it.', 'Save a value of at least '.$minLength.' random characters on Settings and the same on the website ('.$env.').'];
        }

        return [true, $name.' is set.', ''];
    }

    // ---- the API's own state ---------------------------------------------------------------

    /** @return array{0: bool, 1: string, 2: string} */
    private function scheduler(): array
    {
        $age = SystemPulse::tickAgeSeconds();
        $fix = 'The website\'s server calls POST /api/system/tick every minute when TICK_URL and TICK_TOKEN are set on it, and the token matches the one on Settings. Set both on the website and rebuild it.';

        if ($age === null) {
            return [false, 'No scheduler tick has ever arrived: job fetching, alerts, the queue and retries are not running.', $fix];
        }
        if ($age >= self::TICK_MAX_SECONDS) {
            return [false, 'The last tick was '.$this->ago($age).' ago; the scheduler has stopped.', $fix];
        }

        return [true, 'The last tick was '.$this->ago($age).' ago.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function migrations(): array
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return [false, 'The database has never been migrated.', 'Redeploy the API (migrations run on deploy), or call POST /api/setup/migrate with the setup token.'];
        }

        // The same files `migrate` would run: the app's own and any a package registered.
        $files = array_keys($migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths())));
        $pending = array_values(array_diff($files, $migrator->getRepository()->getRan()));

        if ($pending !== []) {
            return [false, count($pending).' migration(s) not applied yet, the newest '.end($pending).'.', 'Redeploy the API (migrations run on deploy), or call POST /api/setup/migrate with the setup token.'];
        }

        return [true, 'All '.count($files).' migrations are applied.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function queue(): array
    {
        $pending = SystemPulse::queuePending();

        if ($pending >= self::QUEUE_LIMIT) {
            return [false, $pending.' jobs are waiting on the queue.', 'The queue drains on every tick. A long queue means ticks are failing or stopped: check the scheduler item and the API log.'];
        }

        return [true, $pending.' job(s) waiting on the queue.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function failedUpdates(): array
    {
        $failed = SystemPulse::failedUpdates24h();

        if ($failed > 0) {
            return [false, $failed.' message(s) or button press(es) from members in the last 24 hours failed to be handled.', 'Read the API log around those times; the dashboard counts them too. Members whose message failed may need to send it again.'];
        }

        return [true, 'Every update in the last 24 hours was handled.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function debugOff(): array
    {
        if (config('app.debug')) {
            return [false, 'Debug mode is on: an error page shows code, paths and settings to anyone.', 'Remove APP_DEBUG=true from the API\'s build environment and rebuild it.'];
        }

        return [true, 'Debug mode is off; errors show a plain message.', ''];
    }

    // ---- content and people -------------------------------------------------------------------

    /** @return array{0: bool, 1: string, 2: string} */
    private function jobSources(): array
    {
        $sources = JobSource::query()->where('key', '!=', 'forwarded')->get(['active', 'paused_at']);
        $running = $sources->filter(fn (JobSource $s) => $s->active && $s->paused_at === null)->count();
        $paused = $sources->filter(fn (JobSource $s) => $s->paused_at !== null)->count();

        if ($running === 0) {
            return [false, 'No job source is running'.($paused > 0 ? ' ('.$paused.' paused after failures)' : '').': only jobs moderators forward to the bot will appear.', 'Add an RSS feed, a web page or a Telegram channel on Job sources, or save a paused one again to resume it.'];
        }

        return [true, $running.' source(s) running'.($paused > 0 ? ', '.$paused.' paused' : '').'.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function anthropic(): array
    {
        if (JobSettings::anthropicKey() === '') {
            return [false, 'No Anthropic key: jobs are sorted by simple rules, and the assistant only searches the FAQ.', 'Save an Anthropic API key on Job settings.'];
        }

        return [true, 'A key is saved; jobs and the assistant use '.JobSettings::model().'.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function adminUser(): array
    {
        $count = User::query()->count();

        return $count > 0
            ? [true, $count.' administrator account(s).', '']
            : [false, 'There is no administrator account.', 'Create one with POST /api/setup/admin, or php artisan admin:create.'];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function supportUsername(): array
    {
        if (RuntimeEnv::get(null, 'support_telegram_username', null) === '') {
            return [false, 'No support username: banned members and the website are told to contact "the admin" with no way to reach them.', 'Enter the Telegram username members should write to on Settings.'];
        }

        return [true, 'Members are pointed to the support username.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function safetyPhones(): array
    {
        $missing = array_keys(array_filter([
            'consulate' => Community::consulatePhone() === '',
            'MoHRE' => Community::mohrePhone() === '',
        ]));

        if ($missing !== []) {
            return [false, '/safe does not show the '.implode(' or the ', $missing).' number.', 'Enter the numbers on Settings (safety section).'];
        }

        return [true, '/safe shows the consulate and MoHRE numbers.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function knowledge(): array
    {
        $articles = KnowledgeArticle::query()->where('active', true)->count();
        $faq = FaqEntry::query()->where('active', true)->count();

        if ($articles + $faq < 3) {
            return [false, 'The assistant has '.$articles.' article(s) and '.$faq.' FAQ entr'.($faq === 1 ? 'y' : 'ies').' to answer from; with so little it mostly says it does not know.', 'Add knowledge articles or FAQ entries on FAQ and knowledge (at least three).'];
        }

        return [true, $articles.' article(s) and '.$faq.' FAQ entr'.($faq === 1 ? 'y' : 'ies').' to answer from.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function directory(): array
    {
        $listed = Business::query()->where('status', 'approved')->count();

        if ($listed < 5) {
            return [false, 'Only '.$listed.' business(es) are listed; the directory looks empty to the first visitors.', 'Add or approve at least five businesses on Businesses.'];
        }

        return [true, $listed.' businesses are listed.', ''];
    }

    /** @return array{0: bool, 1: string, 2: string} */
    private function announcementTopics(): array
    {
        $active = AnnouncementTopic::query()->where('active', true)->count();

        if ($active === 0) {
            return [false, 'There is no active announcement topic: /announce and /topics have nothing to offer.', 'Add or switch on topics on Announcements.'];
        }

        return [true, $active.' active topic(s).', ''];
    }

    private function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 120 => $seconds.' seconds',
            $seconds < 7200 => intdiv($seconds, 60).' minutes',
            $seconds < 172800 => intdiv($seconds, 3600).' hours',
            default => intdiv($seconds, 86400).' days',
        };
    }
}
