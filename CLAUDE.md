# Working notes for this repository

Three applications, one repository. `api/` is Laravel 13 and owns everything
that talks to Telegram, the database and Claude; it serves JSON only. `web/` is
the admin, Next.js 14 on the DashTail starter (shadcn/ui, Tailwind 3). `site/` is
the public website, Next.js 15, which also opens inside Telegram as a Mini App.

The product spec is the "Habesha Dubai Bot: Phase One Spec" doc; phase one
takes no money anywhere, so there is no wallet, no escrow and no payment code.

## Settings and secrets live in `platform_settings`

The host builds Laravel's config cache at build time without the runtime
environment, so `env()` and `config()` are empty for anything set only in the
dashboard. Resolve operator values with `App\Support\RuntimeEnv::get()`
(config → `platform_settings` → process env → `.env` on disk) and edit them on
the admin's Settings page. The bot token is stored encrypted by
`TelegramBotService::configureToken()` and never returned to a page. Other
write-only secrets on Settings (`SettingController::SECRETS`) are stored as
`enc:` + `SecretBox`, written with `RuntimeEnv::conceal()` and decrypted by
`RuntimeEnv::get()`.

**Nothing that must outlive a deploy may depend on `APP_KEY`.** A saved bot token
became unreadable after a deploy, and every delivery from Telegram was answered
403 until someone saved it again. Secrets go through `App\Support\SecretBox`, and
anything else keyed (the phone hash, sign-in codes) uses `App\Support\StableKey`,
which comes from the database password. Never `Crypt` or `config('app.key')`.
A value that can no longer be read answers 503 at the webhook, and the Launch
page and Settings say which one to save again.

## Every bot message goes through `BotMessages`

Defaults in `api/resources/bot/texts.php`, in Amharic, Tigrinya, English and
Afaan Oromoo (`om`, Latin script, added 3 Nov 2026); an admin override in
`bot_texts` wins. Button presses are matched with `BotMessages::pressed()`
across all languages, so never compare a pressed label to a literal. Fixed
lists (emirates, trades) are in `App\Bot\Choices`, keyed by what the database
stores; `Choices::LANGUAGES` is the one list of languages. The Tigrinya and
Oromo columns were written without a native reviewer; both are on the launch
checklist.

Text kept per language in the database (`jobs_listings.summary_*`,
`faq_entries.q_*`/`a_*`, `announcements.text_*`, `announcement_topics.name_*`,
`directory_cities.name_*`) has an `_om` column too, nullable: every reader
falls back to English. Claude writes all four in one call (`Translator`,
`ClaudeJobClassifier`); a fifth language means a column per table, the
prompts, the payloads and the admin forms.

## The webhook only records; handlers run on the queue

`TelegramWebhookController` checks the secret, stores the raw update
(`telegram_updates`, unique `update_id`, so a redelivery is harmless) and
dispatches `HandleTelegramUpdate`, which runs `App\Bot\Router` under a lock
per chat so a flow's steps stay in order. A handled update is reduced to its
id and kind at once (a contact card carries a phone number);
`bot:updates-chores` re-runs one whose handler died and deletes every update
after `TelegramUpdate::KEEP_DAYS`. Edited messages are judged again by
`GroupModerator` in the community groups and ignored everywhere else. Multi-step
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
runs when something calls `POST /api/system/tick`; the host has no cron, and the
website's server does it once a minute.

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

`App\Bot\GoodsFlow` (`/travel`, `/send`, `/goods`, `/mygoods`, buttons `g:…`),
and the same rules on the website (`/[lang]/goods`, `Owner\GoodsController`,
`Public\GoodsController`), which call `GoodsFlow::refusal()`, `create()`,
`ask()`, `followUp()` and `closePost()` rather than re-implementing them;
accepting, agreeing and the check-in stay in Telegram.

No money moves through the platform. Since 3 Nov 2026 a post may state a
carry fee that the members pay each other directly (`price` + `currency`,
AED or ETB): a traveller's price per kilo, 0 for free, or a sender's offer
for the parcel; null means "to agree". A parcel is either sent by its owner
(`mode` `send`) or handed to the traveller at the other end for the member
who asked (`request`). `GET /api/public/goods/fit` shows the posts a new
one would pair with before it is made. Rules that are easy to break:

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

## The public website (`site/`) and business owners

Next.js 15 (App Router, React 19, Tailwind 4), rendered per request with the
API's answers cached for a minute; nothing is pre-rendered at build time, so
a build never needs the API. Every page lives under `/am`, `/ti`, `/en` or
`/om` (`middleware.ts`); copy is in `site/i18n/*.ts`, one typed shape for
all four. Browsers lack Tigrinya and Oromo date names, so `lib/format.ts`
carries fixed tables for them. The fixed lists (kinds of work, emirates, business kinds, languages)
come from the API's `/public/meta`, i.e. from `Choices`, so the site and the
bot never word them differently.

- **Design: "two homes, one thread", morning edition.** It follows the
  apple-design skill (in the Addis game repo, `.claude/skills/apple-design/`).
  Light first: the page is warm white over a faint aurora (`body::before`)
  with paper grain; the header and the phone tab bar are floating islands of
  frosted white (`.chrome`); frosted panels are `.glass`. The three heroes
  (front page, goods board, owners' landing) are night bands
  (`components/Dawn.tsx` → `Night`) holding white frosted panels
  (`.glass-white`, which resets every token inside it to the light set);
  every other page head is a `Dawn` band whose tint follows the hour in
  Dubai (`data-sky`). The horizon under a band is one line of ink
  (`--ink`), not a silhouette. The footer and a few islands stay `.night`.
  Dark follows the device unless the reader chose a theme
  (`components/ThemeToggle.tsx`, `data-theme` on `<html>`, applied before
  paint by the script in `layout.tsx`); every colour is a token, and the dark
  set is written twice in `globals.css` (media query and `data-theme`), so
  keep both lists the same. Gold as text is `--color-gild` (honey on paper,
  gold on night); `--color-accent` is for fills and the thread only.
  `app/manifest.ts`, `apple-icon.tsx` and `opengraph-image.tsx` make the
  site installable and give shared links a card; the locale middleware
  excludes those two generated routes. Headings are Fraunces and Noto
  Serif Ethiopic (`.display`, `.headline`); body text is the system font with
  Noto Sans Ethiopic. Places without a photo get a cover in their kind's
  colours with their initials (`components/Cover.tsx`); kinds of work and of
  place have fixed colour pairs there. Ge'ez numerals mark sections
  (`GEEZ` in `Bits.tsx`). The front page shows Dubai and Addis Ababa time,
  the Ethiopian clock and calendar (`Clocks.tsx`). Motion: the hero settles
  once, sections marked `.reveal` rise in once (`Motion.tsx`, never hiding
  what is already on screen), tiles marked `.spot` carry a light under the
  pointer, press feedback is `scale` on `:active`. Every effect has its
  reduced-motion, reduced-transparency and more-contrast fallback in
  `globals.css`. The tibeb has diamonds and no crosses: it is for everyone.
- **Owners sign in with Telegram** (`App\Directory\OwnerLogin`): the site
  shows a QR code / button; the bot asks the person who opened
  `/start login_<ref>` to confirm, then sends them a six-digit code that they
  type on the website (five wrong codes end it). The code travels from
  Telegram to the website, never the other way: that is what stops a
  forwarded sign-in link from being phished. The site's server polls and
  verifies with a secret the browser never sees and puts the owner token in
  an httpOnly cookie. Owner tokens carry only the `owner` ability
  (`EnsureBusinessOwner`), admin tokens only `admin`. Owners need not be
  members; a member's account is linked.
- **Visitors, not the website, are rate-limited.** The site's server makes
  the API calls, so it sends `X-Site-Key` (`SITE_API_KEY`, the same value on
  both apps) with the visitor's address and browser (`App\Support\ClientIp`).
  Without the key the API cannot tell visitors apart and falls back to
  generous per-address limits.
- **The host runs the API with `php artisan serve`, PHP's built-in server** (the
  format of its log lines gives it away). On its own that answers one request at
  a time, so `App\Console\Commands\ServeWithWorkers` asks for four workers;
  `/api/health` reports how many there are. Keep slow work out of requests all the
  same: the scheduler tick takes one worker for as long as a run lasts, and on a
  single worker it would start `system:tick` in a process of its own instead.
- **Environment variables on this host never reach the API.** The host
  writes its own `.env` with only `APP_KEY` and the database, and `artisan serve`
  clears everything else from the server's environment; so anything set
  on the app (SETUP_TOKEN, TICK_TOKEN, SITE_API_KEY) is invisible at runtime
  (`GET /api/system/status` shows yes/no for each). So the tick token and
  the website key are write-only fields on admin Settings, and setup accepts
  the database password from the AletCloud dashboard as `X-Setup-Token`. The
  Next.js website does receive its variables, but reads them at build time:
  change one, rebuild the site.
- **One repository, three apps.** The API, the admin and the website all
  build from `main`, so one push starts three builds on a plan that allows
  two at a time. Watch each to LIVE after a push.
- **Listings.** An owner's new listing waits for review. An edit to a live
  listing waits in `businesses.pending_changes` while the live version stays
  up; photos (base64 in `business_photos`, re-encoded by `PhotoStore`) are
  always reviewed. Approval goes through `App\Directory\BusinessPublisher`,
  which posts the listing to the channel once and tells the owner in the bot.
  Claims let an owner take over a listing the community added.
- **Links out** are built with `App\Support\PublicUrls`: `public_url` is the
  API (photo links), `site_url` the website (channel and bot buttons). Both
  are on the admin's Settings page.
- **The scheduler tick** comes from the website's server
  (`site/instrumentation.ts`), which calls `{API_URL}/system/tick` once a minute
  with nothing to set up. With no token saved the API takes one run a minute
  from anyone, which is what the minute was going to do anyway; a token saved on
  Settings closes it, and then the website needs the same value as `TICK_TOKEN`.
- **Caching:** anything cached with `Cache::remember` in the API must be
  plain arrays; the cache refuses to restore objects it does not know, and
  the in-memory store in tests hides that.
- **Owners read the API in their language.** The site sends `X-Locale`; the
  `locale` middleware on `/api/owner/*` sets it, and messages come from
  `api/lang/{en,am,ti}/owner.php` and `lang/{am,ti}/validation.php`. A new
  owner-facing message goes there in all three, never as a literal.
- **Website visits** are counted per day and path (`page_views`, via
  `POST /api/public/pageview` from the site's server), with no address,
  cookie or identifier; the admin dashboard reads `GET /api/admin/analytics`.
- **Photos** are stored once at 1600 px; `?w=480|960` on a public photo URL
  gives a cached narrower copy for cards (`PhotoStore::thumbnail`).
- **Scheduled tasks are never pinned to one minute.** A tick that arrives while
  the last still runs is refused, and the scheduler does not catch up, so
  each task runs often and does only what is due (reminders go out in the
  first run after 09:00 Dubai, guarded by `reminder_sent_at`).

## Phone numbers are never stored

A member is keyed on `phone_hash` (HMAC of the digits with `StableKey`, so a deploy never changes it) plus
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
Tokens last 14 days, the same as the session, and signing out revokes the
token (`events.signOut`). Admin sign-in is limited per email (`admin-login`),
because every attempt arrives from the admin's own server. On a fresh host,
`/api/setup/*` accepts the database password only until the first
administrator exists.

## Tests before a push

```bash
cd api && php vendor/bin/pest
```

Pest with in-memory SQLite; Telegram is faked with `Http::fake()`. Helpers in
`tests/Pest.php`: `say()`, `shareContact()`, `joinMember()`, `sentMessages()`,
`lastText()`. A new bot step or admin route gets a test beside the existing
ones. Test headers persist between requests in one test (`withHeader`); call
`flushHeaders()` before a request that must go without them. CI also runs the
suite on Postgres 16 (`api-postgres`, reported, not yet required). For the web
app and the site, `tsc --noEmit` and `next build` are the checks; there are
no browser tests yet.

## Deploy one app at a time

All three apps go to AletCloud, and one push to `main` can start a build of each.
The plan allows two at once, so a third may fail or stall: after a push read
`lastDeployedAt` and `status` for all three, and redeploy one that did not land,
alone, only once the others are live. Do not redeploy an app that is still
building from the push; that collision is how a build ends in FAILED.

## The member's home: the welcome card, the menu keyboard, My Habesha

`App\Bot\Home` ends the join flow and answers /start for a member: a picture
card (the website draws it at `GET /api/card/{lang}`, Latin words only, since
the image renderer has no Ge'ez face), the channel, the groups and the website
as inline buttons (only the ones set on Settings), then a short message that
carries the **menu keyboard** — six reply buttons (`Home::MENU`) that stand
for /jobs, /goods, /ask, /directory, /safe and /profile in any language.

The keyboard is `BotMessages::menuKeyboard($language)`. A flow that ends and
leaves the chat idle puts it back (`$this->messages->menuKeyboard(...)` where a
flow used to send `removeKeyboard()`); the Router's `menuOrNone()` does the
same after cancel and a language change, and sends nothing to someone who is
not an active member. Keep that rule for a new flow, or the menu vanishes
until the next /start.

The website's "My Habesha" (`/{lang}/account`, `GET /api/owner/home`) is the
member's own page: who they are (keys, which the page words through
`/public/meta` labels), goods, questions, saved jobs, listings. It is read
only; a deal, a question or an alert is handled where it was started. The
header's `AccountChip` learns who is signed in after mount through
`/api/auth/me`, so cached pages stay shared. Sign-in is the business owners'
"Continue with Telegram"; the same account serves both.

## The bot answers what people type, and the website opens inside Telegram

**Free text at idle.** `App\Bot\Understand` reads a message in any of the four
languages into a kind (safety, hello, thanks, jobs, places, goods) plus what it
could pick out (trade, emirate, category); `App\Bot\SmartReply` answers from the
database with one-tap buttons. Only what it does not understand goes to the Claude
assistant, which costs a call. A new word for a trade or a place goes in
`Understand`'s tables, in all four languages; an everyday phrase that merely holds
such a word ("good job", "work permit") goes in its `NOT_REQUESTS`. Someone in
trouble comes first: safety words send the card however the message is worded,
question or long story included, and a sentence goes on to the assistant as well.
The assistant can be off, out of its daily limit or down, so it must never be the
only way to the numbers. Ge'ez words match where a word starts ("አስራ" is not "ስራ").

**Commands and profile.** `App\Bot\BotProfile` is the one list of commands and
the bot's descriptions per language. `syncIfStale()` runs at the top of the
Router and only talks to Telegram when the texts, the website's address or the bot
changed (a fingerprint in `bot_profile_synced`, written only once all of it landed;
a refusal tries again five minutes later, not on every message). A member's own
chat gets their language's commands and a menu button that opens the website
(`Home::personalise`), which outranks those defaults, so after a change
`bot:updates-chores` brings each member's up to date, a few each minute
(`Home::refreshChats`). `Home::dashboard` is what /start says to someone who
already joined.

**The Mini App.** `PublicUrls::siteButton` makes a `web_app` button when the
website is on a public https address and a plain link otherwise; Telegram takes
`web_app` buttons in private chats only, so a channel card or group post must
never use it. A reply-keyboard `web_app` button carries no signed data, which is
why these are inline buttons and the menu button. Telegram puts the signed data
in the address's fragment; `site/lib/telegram-app.ts` reads it once, sends it to
`POST /api/auth/webapp` and clears it from the address, and
`TelegramBotService::webAppUser()` checks the signature (`WebAppData` HMAC, one
hour). The data counts only where Telegram's bridge is in the page
(`TelegramWebviewProxy`): in a plain tab it came in a link someone sent, and signing
in with it would put the reader in the sender's account. No third-party script loads:
messages to the app go through the same small events Telegram's own script sends. In
Telegram's web client the page sits in a frame, where the browser keeps no cookie, so
no way of signing in can work there and the page offers a tab of its own.

**The scheduler runs by itself.** See "The scheduler tick" above: the website's
server starts it, with nothing to set up. If `tick_age_seconds` on `/api/health`
is null after a deploy, check the website first.
