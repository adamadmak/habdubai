<?php

namespace App\Providers;

use App\Jobs\Pipeline\ClaudeJobClassifier;
use App\Jobs\Pipeline\JobClassifier;
use App\Jobs\Pipeline\RulesJobClassifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Claude when an Anthropic key is set (Configuration → Jobs), rules otherwise.
        $this->app->bind(JobClassifier::class, fn ($app) => ClaudeJobClassifier::configured()
            ? $app->make(ClaudeJobClassifier::class)
            : $app->make(RulesJobClassifier::class));
    }

    public function boot(): void
    {
        //
    }
}
