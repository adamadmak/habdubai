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
