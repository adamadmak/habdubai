# Habesha Dubai

A free Telegram community for Habesha in Dubai: verified members, jobs fetched
automatically and posted in Amharic and Tigrinya, a questions group,
announcements, and a trust-only board for carrying goods between Dubai and
Ethiopia. Nothing here takes or moves money.

Three applications in one repository:

- `api/` — Laravel. The Telegram webhook, queue workers, Postgres, and the JSON
  API the admin and the Mini App call. No Blade pages.
- `web/` — Next.js (DashTail starter). The admin dashboard.
- `site/` — Next.js 15 and Tailwind 4. The public website: jobs, the business
  directory, news and events in Amharic, Tigrinya and English, and the
  portal where business owners sign in with Telegram and manage listings.

## Local setup

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan admin:create you@example.com secret
cd web && npm install && cp .env.example .env.local && npm run dev
cd site && npm install && cp .env.example .env.local && npm run dev
```

The API listens on http://localhost:8000 (`php artisan serve`); the web app on
http://localhost:3000 and calls the API with the URL in `NEXT_PUBLIC_API_URL`;
the public website on http://localhost:3001 (`API_URL`, `SITE_URL`).

To talk to the bot from a phone during development, paste a bot token on the
admin's Settings page (it is stored encrypted; the webhook step is skipped on
localhost), then poll Telegram instead of waiting for a webhook:

```bash
cd api && php artisan bot:poll
```

It refuses to run while the bot has a webhook registered, so it can never
take a live bot offline. Use a separate test bot from BotFather for local work.

## Tests

```bash
cd api && php vendor/bin/pest
```

Pest with an in-memory SQLite database. Telegram is faked with `Http::fake()`,
so nothing reaches the network.

## Deploy

Three AletCloud apps from this one GitHub repository, pushed one at a time (two
builds at once leave both stuck):

| App | Root | Framework | Env it needs |
| --- | --- | --- | --- |
| `habesha-api` | `api` | Laravel | `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `TRUSTED_PROXIES=*`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=array`, `SETUP_TOKEN`, `TICK_TOKEN`, `SITE_API_KEY`, `SITE_URL`, plus the database variables the host injects. Variables are read at build time only: rebuild after changing one |
| `habesha` (web) | `web` | Next.js | `NEXT_PUBLIC_API_URL`, `NEXTAUTH_URL`, `NEXTAUTH_SECRET` |
| `habuae-site` (site) | `site` | Next.js | `API_URL` (the API with `/api`), `SITE_URL` (its own address), `SITE_API_KEY` (same as the API), and `TICK_URL` + `TICK_TOKEN` so it drives the API scheduler |

The host has no shell, so the first run goes through two token-guarded
routes (`SetupController`), with `X-Setup-Token: <SETUP_TOKEN>`:

```bash
curl -X POST https://<api>/api/setup/migrate -H "X-Setup-Token: $SETUP_TOKEN"
```

```bash
curl -X POST https://<api>/api/setup/admin -H "X-Setup-Token: $SETUP_TOKEN" -H "Content-Type: application/json" -d '{"email":"you@example.com","password":"a-long-password"}'
```

The admin route works only while there is no administrator; migrations can
be re-run after every deploy that adds one.

The host runs no cron and no queue worker either. The public website calls
the tick once a minute by itself (`site/instrumentation.ts`, from `API_URL`);
any other timer may do it instead. With a token saved on Settings it needs
`X-Tick-Token` (`TICK_TOKEN` on the website); with none it takes one run a
minute from anyone. The tick
runs `system:tick`, which runs `schedule:run`: it fetches due job sources,
drains the queue, sends job alerts and closes stale jobs:

```bash
curl -X POST https://<api>/api/system/tick
```

Telegram updates do not wait for the tick: they run right after the
webhook's response, in the same process. The bot token and the other
operator values live in `platform_settings` and are set on the admin's
Settings page, never in environment variables (the host builds the config
cache without the runtime environment).
