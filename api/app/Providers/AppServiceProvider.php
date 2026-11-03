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
        // Every outgoing HTTPS call is verified against a real CA list, even on
        // a PHP build that ships without one (see App\Support\TrustedCas).
        \Illuminate\Support\Facades\Http::globalOptions(['verify' => \App\Support\TrustedCas::path()]);
    }
}
