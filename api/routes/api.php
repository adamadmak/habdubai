<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BotTextController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\JobSettingsController;
use App\Http\Controllers\Admin\JobSourceController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SystemTickController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)->name('telegram.webhook');

// The scheduler's clock: an external timer calls this every minute.
Route::match(['get', 'post'], 'system/tick', SystemTickController::class)->middleware('throttle:6,1')->name('system.tick');

// First run on a host with no shell: see SetupController.
Route::post('setup/migrate', [SetupController::class, 'migrate'])->middleware('throttle:10,1')->name('setup.migrate');
Route::post('setup/admin', [SetupController::class, 'admin'])->middleware('throttle:10,1')->name('setup.admin');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');

    Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('members/{member}/ban', [MemberController::class, 'ban'])->name('members.ban');
        Route::post('members/{member}/unban', [MemberController::class, 'unban'])->name('members.unban');

        Route::get('bot-texts', [BotTextController::class, 'index'])->name('bot-texts.index');
        Route::put('bot-texts/{key}', [BotTextController::class, 'update'])->name('bot-texts.update');

        Route::get('settings', [SettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/telegram', [SettingController::class, 'telegram'])->name('settings.telegram');
        Route::post('settings/webhook', [SettingController::class, 'webhook'])->name('settings.webhook');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('job-sources', [JobSourceController::class, 'index'])->name('job-sources.index');
        Route::post('job-sources', [JobSourceController::class, 'store'])->name('job-sources.store');
        Route::post('job-sources/test', [JobSourceController::class, 'test'])->middleware('throttle:20,1')->name('job-sources.test');
        Route::put('job-sources/{source}', [JobSourceController::class, 'update'])->name('job-sources.update');
        Route::delete('job-sources/{source}', [JobSourceController::class, 'destroy'])->name('job-sources.destroy');
        Route::post('job-sources/{source}/run', [JobSourceController::class, 'run'])->middleware('throttle:20,1')->name('job-sources.run');

        Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::patch('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::post('jobs/{job}/approve', [JobController::class, 'approve'])->name('jobs.approve');
        Route::post('jobs/{job}/reject', [JobController::class, 'reject'])->name('jobs.reject');
        Route::post('jobs/{job}/close', [JobController::class, 'close'])->name('jobs.close');

        Route::get('job-settings', [JobSettingsController::class, 'show'])->name('job-settings.show');
        Route::put('job-settings', [JobSettingsController::class, 'update'])->name('job-settings.update');
    });
});
