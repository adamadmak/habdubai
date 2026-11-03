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

Schedule::command('jobs:close-stale')->dailyAt('03:15')->timezone('Asia/Dubai')->withoutOverlapping(30);

Schedule::command('questions:close-quiet')->dailyAt('03:30')->timezone('Asia/Dubai')->withoutOverlapping(30);
