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

        $this->rateLimits();
    }

    /**
     * The public website calls this API from its own server. Calls that carry
     * its key are counted per visitor (the address it forwards) or, for its own
     * cached reads, in one large bucket; anyone else is counted per address.
     */
    private function rateLimits(): void
    {
        $limit = \Illuminate\Cache\RateLimiting\Limit::class;
        $client = fn (\Illuminate\Http\Request $r): string => \App\Support\ClientIp::of($r);
        $ref = fn (\Illuminate\Http\Request $r): string => is_string($r->input('ref')) ? substr($r->input('ref'), 0, 32) : '';

        // Until SITE_API_KEY is set on both apps, every visitor arrives as the website's
        // server; the limits are then generous enough that the site is not throttled as one.
        $keyed = fn (): bool => \App\Support\RuntimeEnv::get('services.host.site_api_key', 'site_api_key', 'SITE_API_KEY') !== '';

        \Illuminate\Support\Facades\RateLimiter::for('public', function (\Illuminate\Http\Request $r) use ($limit, $keyed) {
            if (\App\Support\ClientIp::siteKeyValid($r) && ! $r->hasHeader('X-Client-Ip')) {
                return $limit::perMinute(3000)->by('site');
            }

            return $limit::perMinute($keyed() ? 120 : 1500)->by('public:'.\App\Support\ClientIp::of($r));
        });
        \Illuminate\Support\Facades\RateLimiter::for('owner-login-start', fn ($r) => $limit::perMinute($keyed() ? 10 : 30)->by('start:'.$client($r)));
        \Illuminate\Support\Facades\RateLimiter::for('owner-login-poll', fn ($r) => $limit::perMinute(45)->by('poll:'.$ref($r).':'.$client($r)));
        \Illuminate\Support\Facades\RateLimiter::for('owner-login-verify', fn ($r) => [
            $limit::perMinute(10)->by('verify:'.$ref($r)),
            $limit::perMinute(30)->by('verify-ip:'.$client($r)),
        ]);
    }
}
