<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SystemTickController;
use App\Models\Job;
use App\Models\JobAlert;
use App\Models\JobSource;
use App\Models\Member;
use App\Support\JobSettings;
use Illuminate\Support\Facades\Cache;
use App\Models\TelegramUpdate;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(TelegramBotService $telegram): JsonResponse
    {
        $today = now()->startOfDay();

        return response()->json([
            'members' => [
                'total' => Member::query()->count(),
                'active' => Member::query()->whereNotNull('agreed_rules_at')->whereNull('banned_at')->whereNull('left_at')->count(),
                'onboarding' => Member::query()->whereNull('agreed_rules_at')->count(),
                'verified_today' => Member::query()->where('verified_at', '>=', $today)->count(),
                'needs_review' => Member::query()->where('needs_review', true)->whereNull('banned_at')->count(),
                'banned' => Member::query()->whereNotNull('banned_at')->count(),
                'left' => Member::query()->whereNotNull('left_at')->count(),
            ],
            'by_emirate' => Member::query()->whereNotNull('agreed_rules_at')->selectRaw('emirate, count(*) as n')->groupBy('emirate')->pluck('n', 'emirate'),
            'by_language' => Member::query()->whereNotNull('agreed_rules_at')->selectRaw('language, count(*) as n')->groupBy('language')->pluck('n', 'language'),
            'updates' => [
                'today' => TelegramUpdate::query()->where('received_at', '>=', $today)->count(),
                'failed' => TelegramUpdate::query()->whereNotNull('error')->whereNull('processed_at')->count(),
            ],
            'bot' => [
                'configured' => $telegram->configured(),
                'username' => $telegram->botUsername(),
            ],
            'jobs' => [
                'published_today' => Job::query()->where('published_at', '>=', $today)->count(),
                'open' => Job::query()->where('status', 'published')->count(),
                'review' => Job::query()->where('status', 'review')->count(),
                'duplicates_today' => Job::query()->where('status', 'duplicate')->where('created_at', '>=', $today)->count(),
                'alerts_queued' => JobAlert::query()->whereNull('sent_at')->count(),
                'classifier' => JobSettings::anthropicKey() !== '' ? 'claude' : 'rules',
                'channel' => JobSettings::channel() !== '',
            ],
            'sources' => [
                'active' => JobSource::query()->where('active', true)->whereNull('paused_at')->count(),
                'paused' => JobSource::query()->whereNotNull('paused_at')->count(),
                'failing' => JobSource::query()->where('active', true)->whereNull('paused_at')->where('failures_in_row', '>', 0)->count(),
            ],
            'directory' => [
                'listed' => \App\Models\Business::query()->where('status', 'approved')->count(),
                'pending' => \App\Models\Business::query()->where('status', 'pending')->count(),
                'hidden' => \App\Models\Business::query()->where('status', 'hidden')->count(),
            ],
            'tick' => Cache::get(SystemTickController::LAST_TICK_KEY),
        ]);
    }
}
