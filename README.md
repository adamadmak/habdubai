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

Two AletCloud apps, pushed one at a time. The API's bot token and other
secrets live in `platform_settings` and are set on the admin's Settings page,
never in environment variables (the host builds the config cache without the
runtime environment).
