# Working notes for this repository

Two applications, one repository. `api/` is Laravel 13 and owns everything
that talks to Telegram, the database and Claude. `web/` is Next.js 14 on the
DashTail starter (shadcn/ui, Tailwind 3) and owns every screen: the admin
now, the Telegram Mini App later. The API serves JSON only.

The product spec is the "Habesha Dubai Bot: Phase One Spec" doc; phase one
takes no money anywhere, so there is no wallet, no escrow and no payment code.

## Settings and secrets live in `platform_settings`

The host builds Laravel's config cache at build time without the runtime
environment, so `env()` and `config()` are empty for anything set only in the
dashboard. Resolve operator values with `App\Support\RuntimeEnv::get()`
(config → `platform_settings` → process env → `.env` on disk) and edit them on
the admin's Settings page. The bot token is stored encrypted by
`TelegramBotService::configureToken()` and never returned to a page.

## Every bot message goes through `BotMessages`

Defaults in `api/resources/bot/texts.php`, in Amharic, Tigrinya and English;
an admin override in `bot_texts` wins. Button presses are matched with
`BotMessages::pressed()` across all three languages, so never compare a
pressed label to a literal. Fixed lists (emirates, trades) are in
`App\Bot\Choices`, keyed by what the database stores. The Tigrinya column was
written without a native reviewer; it is on the launch checklist.

## The webhook only records; handlers run on the queue

`TelegramWebhookController` checks the secret, stores the raw update
(`telegram_updates`, unique `update_id`, so a redelivery is harmless) and
dispatches `HandleTelegramUpdate`, which runs `App\Bot\Router` with
`WithoutOverlapping` per chat so a flow's steps stay in order. Multi-step
flows keep their place in `conversations` (`state` + `data`); `idle` means
nothing is running. Add a new flow as a class like `App\Bot\Onboarding` and a
branch in the router, not as more cases inside the router.

## Jobs: sources → dedupe → classify → score → publish or review

Sources (`App\Jobs\Sources`): RSS/Atom, HTML pages read with selectors the
admin configures (the Sources page can test a configuration before saving),
Telegram channels the bot sits in, and posts a moderator forwards to the bot.
A source that fails three runs in a row is paused. Only a summary and a link
are ever published, never a site's page.

`App\Jobs\ProcessJobListing` runs each new listing: `Deduper` first (URL,
title and company, then text similarity, so a duplicate never costs a model
call), then the `JobClassifier` (Claude when an Anthropic key is set on Job
settings, `RulesJobClassifier` otherwise or when the model fails), then
`ScamScorer`, whose score is the larger of our rules and the model's risk; a
moderator phrase or a blocked company always lifts it to the review
threshold. `JobPublisher` is the only thing that posts, holds, rejects or
closes a job, and the database status is the truth the channel card follows.

The Claude call (`ClaudeJobClassifier`) is one request per listing:
`claude-opus-5-5` by default (Job settings can change it), low effort,
JSON-schema output, the default server-side refusal fallback. Its tests run
the real SDK against a fake PSR-18 transport (`fakeTransport()` in
`tests/Pest.php`); keep the request shape covered there when you change it.

Alerts are queued on publish (`JobAlerts::queueFor`) and sent by the
scheduler at most once per fifteen minutes per member. The scheduler only
runs when something calls `POST /api/system/tick` with `TICK_TOKEN`; the host
has no cron.

## Business directory

`App\Bot\DirectoryFlow`: members browse by city → kind → a page of eight →
one business (sent as a map venue when it has a pin), search by name or
area, and suggest a business (name, city, kind, area, optional pin or Google
Maps link, optional phone and one line). A suggestion is `pending` until a
moderator approves it on the admin's Directory page; nothing a member types
is listed before that. Three reports hide an unverified listing; a verified
one stays until a moderator acts. Cities are rows in `directory_cities`
(seeded with the eight UAE cities) so a new city is data, not a deploy.
Business kinds are fixed in `Choices::BUSINESS_CATEGORIES`, and money
exchange or transfer is deliberately not one of them.

## Community: questions, assistant, announcements, moderation

- **Every Claude call goes through `App\Ai\ClaudeJson`** (model from Job
  settings, CA list, default refusal fallback, error mapping to
  `AiUnavailable` / `AiDeclined`). The container builds `ClaudeJson` and
  `ClaudeJobClassifier` by hand in `AppServiceProvider`; left to itself it
  would inject a bare Anthropic client without the stored key.
- **The assistant answers only from checked material**: the knowledge base
  and the FAQ, cached in the system prompt. It must say when it does not know
  rather than guess fees, dates or rules; keep it that way when you edit the
  prompt. No key, or Claude failing, falls back to `KnowledgeSearch`.
- **Questions** live in forum topics in the questions group
  (`QuestionsFlow`); answers are members' messages in the topic. Points: 1
  for a member's first answer, 5 for the marked solution, 10 when it becomes
  an FAQ entry. Ranks carry no rights.
- **Announcements** (`AnnouncementsFlow`, `AnnouncementPublisher`): leaders
  post only in topics granted on their member page; deaths and fundraisers
  from a leader wait for a moderator. DMs go out on the queue
  (`SendAnnouncementDms`), never inside the request.
- **Group moderation** (`GroupModerator`) runs before anything else reads a
  group message; a removed message is never recorded as an answer. Leaders,
  moderators and admins are exempt. Every removal is explained to the member
  privately. Keep new checks inside it so the Moderation page sees them.
- **Groups and channels** the bot is added to are recorded in `bot_chats`
  and listed on Settings, so ids are picked, not typed.

## Goods board: trust only, nothing carried by us

`App\Bot\GoodsFlow` (`/travel`, `/send`, `/goods`, `/mygoods`, buttons `g:…`).
No money moves through the platform and `goods_posts.price` stays null in
phase one. Rules that are easy to break:

- **Interest needs your own post on the other side.** "Interested" on a trip
  without a parcel post starts `/send` and sends the request the moment it is
  posted. So every `goods_matches` row pairs two posts, and a traveller always
  reads what is in the parcel before agreeing.
- **Nothing is shared before the owner accepts.** The request shows the
  asker's post and badge only; the `tg://user` links go out on acceptance.
- **Kilos come off the trip only when both press "We agreed"**, inside one
  transaction (`finalise`), which re-checks the room and closes the parcel.
- A trip closes after its flight date; a parcel's date is approximate, so it
  stays open three days longer (`GoodsPost::lastDay()`, scopes `live` and
  `stale`).
- `goods:chores` (hourly) expires posts, asks "did it arrive?" from the day
  after the flight between 09:00 and 21:00 Dubai time, every two days until
  answered, settles silent matches after 14 days, and reveals ratings a week
  old. Two yeses complete a match and count a deal for both; a "no" opens a
  dispute for moderators (Telegram alert plus the admin Goods page).
- Ratings stay hidden until both sides rate or seven days pass, so nobody
  rates in reply. `Rating::recount()` is the only writer of `rating_avg`.
- Two `failed` matches within a year (`GoodsMatch::blocked()`) hide the
  member's open posts and refuse new ones.
- Banned items are `GroupModerator::BANNED_ITEMS`, shared by the group
  filter and every goods post a member writes.

## Phone numbers are never stored

A member is keyed on `phone_hash` (HMAC of the digits with the app key) plus
`phone_last4` and `phone_country`. The contact card is accepted only when
`contact.user_id` equals the sender's id (`MemberRegistrar::verifyContact`);
a typed number is never accepted. A second Telegram account presenting an
existing member's number is refused and the first account is told.

## Admin auth is a Sanctum token inside next-auth

`web/lib/auth.ts` signs in against `POST /api/admin/login` and keeps the
Sanctum token in next-auth's JWT cookie; `web/lib/api.ts` adds it to every
request and signs out on a 401. Admin routes need `auth:sanctum` and the
`abilities:admin` middleware. Administrators are created with
`php artisan admin:create`; community members never sign in to the admin.

## Tests before a push

```bash
cd api && php vendor/bin/pest
```

Pest with in-memory SQLite; Telegram is faked with `Http::fake()`. Helpers in
`tests/Pest.php`: `say()`, `shareContact()`, `joinMember()`, `sentMessages()`,
`lastText()`. A new bot step or admin route gets a test beside the existing
ones. For the web app, `npx next build` is the check; there are no web tests
yet.

## Deploy one app at a time

Both apps go to AletCloud. A push auto-builds; pushing both at once leaves
both stuck in BUILDING. Push one, confirm it is live, then the other.
