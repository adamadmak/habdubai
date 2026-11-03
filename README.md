# Habesha Dubai

A free Telegram community for Habesha in Dubai: verified members, jobs fetched
automatically and posted in Amharic and Tigrinya, a questions group,
announcements, and a trust-only board for carrying goods between Dubai and
Ethiopia. Nothing here takes or moves money.

Two applications in one repository:

- `api/` — Laravel. The Telegram webhook, queue workers, Postgres, and the JSON
  API the admin and the Mini App call. No Blade pages.
- `web/` — Next.js (DashTail starter). The admin dashboard now, the Telegram
  Mini App later.

## Local setup

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan admin:create you@example.com secret
cd web && npm install && cp .env.example .env.local && npm run dev
```

The API listens on http://localhost:8000 (`php artisan serve`); the web app on
http://localhost:3000 and calls the API with the URL in `NEXT_PUBLIC_API_URL`.

## Tests

```bash
cd api && php vendor/bin/pest
```

Pest with an in-memory SQLite database. Telegram is faked with `Http::fake()`,
so nothing reaches the network.

## Deploy

Two AletCloud apps from this one GitHub repository, pushed one at a time (two
builds at once leave both stuck):

| App | Root | Framework | Env it needs |
| --- | --- | --- | --- |
| `habesha-api` | `api` | Laravel | `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `TRUSTED_PROXIES=*`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=array`, `SETUP_TOKEN`, `TICK_TOKEN`, plus the database variables the host injects |
| `habesha` (web) | `web` | Next.js | `NEXT_PUBLIC_API_URL`, `NEXTAUTH_URL`, `NEXTAUTH_SECRET` |

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

The host runs no cron and no queue worker either. An external timer (the
same kind that drives the betting sites) must call the tick once a minute;
it runs `schedule:run`, which fetches due job sources, drains the queue,
sends job alerts and closes stale jobs:

```bash
curl -X POST https://<api>/api/system/tick -H "X-Tick-Token: $TICK_TOKEN"
```

Telegram updates do not wait for the tick: they run right after the
webhook's response, in the same process. The bot token and the other
operator values live in `platform_settings` and are set on the admin's
Settings page, never in environment variables (the host builds the config
cache without the runtime environment).
