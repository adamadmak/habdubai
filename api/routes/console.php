<?php

use Illuminate\Support\Facades\Schedule;

/*
 * This host has no cron and no long-running worker. An external timer calls
 * POST /api/system/tick once a minute (SystemTickController), which runs
 * `schedule:run`. Order matters: due sources are queued first, then the
 * worker drains the queue within the tick's budget.
 */

Schedule::command('jobs:fetch-due')->everyMinute()->withoutOverlapping(5);

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=0')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('jobs:send-alerts')->everyFiveMinutes()->withoutOverlapping(5);

// A tick that arrives while the previous one still runs is refused, and the scheduler
// never runs a missed minute later. So nothing below is pinned to one minute of the day:
// each task runs often and does only what is due.
Schedule::command('jobs:close-stale')->everyFifteenMinutes()->withoutOverlapping(30);

Schedule::command('questions:close-quiet')->everyFifteenMinutes()->withoutOverlapping(30);

// Unpins every hour; event reminders go out in the first run after 09:00 in Dubai.
Schedule::command('announcements:chores --reminders')->hourly()->withoutOverlapping(10);

Schedule::command('goods:chores')->everyTenMinutes()->withoutOverlapping(10);

Schedule::command('bot:updates-chores')->everyMinute()->withoutOverlapping(5);

// Sign-ins that ran out: a website session lasts sixty days and each new one adds a row.
Schedule::command('sanctum:prune-expired --hours=24')->hourly()->withoutOverlapping(10);
