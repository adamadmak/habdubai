<?php

namespace App\Console\Commands;

use App\Jobs\Pipeline\JobAlerts;
use Illuminate\Console\Command;

class SendJobAlerts extends Command
{
    protected $signature = 'jobs:send-alerts';

    protected $description = 'Send each member their queued job alerts, at most once every fifteen minutes';

    public function handle(JobAlerts $alerts): int
    {
        $this->info('Sent '.$alerts->sendDue().' alert message(s).');

        return self::SUCCESS;
    }
}
