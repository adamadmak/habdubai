<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
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
        ]);
    }
}
