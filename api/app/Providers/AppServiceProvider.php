<?php

namespace App\Providers;

use App\Ai\ClaudeJson;
use App\Jobs\Pipeline\ClaudeJobClassifier;
use App\Jobs\Pipeline\JobClassifier;
use App\Jobs\Pipeline\RulesJobClassifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Built by hand: left to the container, the optional Anthropic client
        // argument would be filled with a bare client that has neither the key
        // saved on the admin nor the CA list, and every call would fail.
        $this->app->bind(ClaudeJson::class, fn () => new ClaudeJson);
        $this->app->bind(ClaudeJobClassifier::class, fn () => new ClaudeJobClassifier);

        // Claude when an Anthropic key is set (Job settings), rules otherwise.
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
