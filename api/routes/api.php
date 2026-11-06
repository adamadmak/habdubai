<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BotTextController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DirectoryCityController;
use App\Http\Controllers\Admin\EmployerReportController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GoodsController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\JobSettingsController;
use App\Http\Controllers\Admin\JobSourceController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Owner\AuthController as OwnerAuth;
use App\Http\Controllers\Owner\BusinessController as OwnerBusinesses;
use App\Http\Controllers\Owner\GoodsController as OwnerGoods;
use App\Http\Controllers\Owner\HomeController as OwnerHome;
use App\Http\Controllers\Public\AnnouncementController as PublicAnnouncements;
use App\Http\Controllers\Public\DirectoryController as PublicDirectory;
use App\Http\Controllers\Public\GoodsController as PublicGoods;
use App\Http\Controllers\Public\HomeController as PublicHome;
use App\Http\Controllers\Public\JobController as PublicJobs;
use App\Http\Controllers\Public\MediaController as PublicMedia;
use App\Http\Controllers\Public\PageViewController as PublicPageViews;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SystemTickController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Numeric ids are 1 to 18 digits: a longer one is a 404 here, not an
 * out-of-range error from the database (a 500). Route model bindings follow
 * the same rule.
 */
Route::patterns(array_fill_keys(['job', 'announcement', 'photo', 'business', 'member', 'source', 'city', 'question', 'faq', 'article', 'topic', 'post', 'match', 'report', 'claim'], '[0-9]{1,18}'));

Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)->name('telegram.webhook');

// The scheduler's clock: an external timer calls this every minute.
Route::match(['get', 'post'], 'system/tick', SystemTickController::class)->middleware('throttle:6,1')->name('system.tick');

// Which settings the running API can see, as yes/no (no values).
Route::get('system/status', \App\Http\Controllers\SystemStatusController::class)->middleware('throttle:20,1')->name('system.status');

// For uptime monitors: up, database, scheduler, queue. Throttled inside (60 a minute), so a
// database outage still answers 503 rather than failing in the rate limiter.
Route::get('health', \App\Http\Controllers\HealthController::class)->name('health');

// First run on a host with no shell: see SetupController.
Route::post('setup/migrate', [SetupController::class, 'migrate'])->middleware('throttle:10,1')->name('setup.migrate');
Route::post('setup/admin', [SetupController::class, 'admin'])->middleware('throttle:10,1')->name('setup.admin');

// The public website reads these (rate limits: AppServiceProvider::rateLimits).
Route::prefix('public')->name('public.')->middleware('throttle:public')->group(function (): void {
    Route::get('home', [PublicHome::class, 'home'])->name('home');
    Route::get('meta', [PublicHome::class, 'meta'])->name('meta');
    Route::get('jobs', [PublicJobs::class, 'index'])->name('jobs');
    Route::get('jobs/{job}', [PublicJobs::class, 'show'])->name('jobs.show');
    Route::get('jobs/{job}/apply', [PublicJobs::class, 'apply'])->name('jobs.apply');
    Route::get('directory', [PublicDirectory::class, 'facets'])->name('directory');
    Route::get('businesses', [PublicDirectory::class, 'index'])->name('businesses');
    Route::post('businesses/{slug}/view', [PublicDirectory::class, 'view'])->where('slug', '[a-z0-9-]{1,170}')->name('businesses.view');
    Route::get('businesses/{slug}', [PublicDirectory::class, 'show'])->where('slug', '[a-z0-9-]{1,170}')->name('businesses.show');
    Route::get('announcements', [PublicAnnouncements::class, 'index'])->name('announcements');
    Route::get('announcements/{announcement}', [PublicAnnouncements::class, 'show'])->name('announcements.show');
    Route::get('announcements/{announcement}/photo', [PublicMedia::class, 'announcement'])->name('announcements.photo');
    Route::get('photos/{photo}', [PublicMedia::class, 'photo'])->name('photo');
    Route::get('job-photos/{photo}', [PublicMedia::class, 'jobPhoto'])->name('job-photo');
    Route::get('photos/{photo}/preview', [PublicMedia::class, 'preview'])->middleware('signed:relative')->name('photo.preview');
    // The goods board: open trips and parcels, and what a post would match before it is made.
    Route::get('goods', [PublicGoods::class, 'index'])->name('goods');
    Route::get('goods/fit', [PublicGoods::class, 'fit'])->name('goods.fit');
    Route::get('goods/{post}', [PublicGoods::class, 'show'])->name('goods.show');
    // A page seen on the website, sent by its server: counted per day and path, nobody identified.
    Route::post('pageview', [PublicPageViews::class, 'store'])->name('pageview');
});

// Business owners: "Continue with Telegram", then their own listings.
Route::prefix('owner')->name('owner.')->middleware('locale')->group(function (): void {
    Route::post('login/start', [OwnerAuth::class, 'start'])->middleware('throttle:owner-login-start')->name('login.start');
    Route::post('login/poll', [OwnerAuth::class, 'poll'])->middleware('throttle:owner-login-poll')->name('login.poll');
    Route::post('login/verify', [OwnerAuth::class, 'verify'])->middleware('throttle:owner-login-verify')->name('login.verify');
    Route::post('login/webapp', [OwnerAuth::class, 'webapp'])->middleware('throttle:owner-webapp')->name('login.webapp');

    Route::middleware(['auth:sanctum', 'abilities:owner', 'owner', 'throttle:120,1'])->group(function (): void {
        Route::get('me', [OwnerAuth::class, 'me'])->name('me');
        // The member's own page on the website: who they are here and what they have going on.
        Route::get('home', OwnerHome::class)->name('home');
        Route::post('logout', [OwnerAuth::class, 'logout'])->name('logout');
        Route::get('choices', [OwnerBusinesses::class, 'choices'])->name('choices');
        Route::get('businesses', [OwnerBusinesses::class, 'index'])->name('businesses.index');
        Route::post('businesses', [OwnerBusinesses::class, 'store'])->name('businesses.store');
        Route::get('businesses/{business}', [OwnerBusinesses::class, 'show'])->name('businesses.show');
        Route::put('businesses/{business}', [OwnerBusinesses::class, 'update'])->name('businesses.update');
        Route::delete('businesses/{business}', [OwnerBusinesses::class, 'destroy'])->name('businesses.destroy');
        Route::post('businesses/{business}/photo', [OwnerBusinesses::class, 'photo'])->name('businesses.photo');
        Route::post('businesses/{business}/claim', [OwnerBusinesses::class, 'claim'])->name('businesses.claim');
        // Open positions a business posts itself.
        Route::get('jobs', [\App\Http\Controllers\Owner\JobController::class, 'index'])->name('jobs.index');
        Route::post('jobs', [\App\Http\Controllers\Owner\JobController::class, 'store'])->middleware('throttle:10,1')->name('jobs.store');
        Route::post('jobs/{job}/close', [\App\Http\Controllers\Owner\JobController::class, 'close'])->middleware('throttle:30,1')->name('jobs.close');
        // Members' stars for a place.
        Route::get('directory/{slug}/rating', [\App\Http\Controllers\Owner\BusinessRatingController::class, 'show'])->where('slug', '[a-z0-9-]{1,170}')->name('directory.rating');
        Route::post('directory/{slug}/rating', [\App\Http\Controllers\Owner\BusinessRatingController::class, 'store'])->where('slug', '[a-z0-9-]{1,170}')->middleware('throttle:30,1')->name('directory.rate');
        // The goods board for members: same rules as the bot. Accepting and agreeing stay in Telegram.
        Route::get('goods', [OwnerGoods::class, 'index'])->name('goods.index');
        Route::post('goods', [OwnerGoods::class, 'store'])->middleware('throttle:20,1')->name('goods.store');
        Route::post('goods/{post}/close', [OwnerGoods::class, 'close'])->name('goods.close');
        Route::post('goods/{post}/interest', [OwnerGoods::class, 'interest'])->middleware('throttle:30,1')->name('goods.interest');
        Route::post('goods/deals/{match}/delivered', [OwnerGoods::class, 'delivered'])->middleware('throttle:30,1')->name('goods.deals.delivered');
        Route::post('goods/deals/{match}/rate', [OwnerGoods::class, 'rate'])->middleware('throttle:30,1')->name('goods.deals.rate');
    });
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login');

    Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('readiness', \App\Http\Controllers\Admin\ReadinessController::class)->middleware('throttle:30,1')->name('readiness');
        Route::get('analytics', AnalyticsController::class)->name('analytics');

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

        Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::post('businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::put('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::delete('businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
        Route::post('businesses/{business}/photo', [BusinessController::class, 'photo'])->name('businesses.photo');
        Route::delete('businesses/{business}/photo', [BusinessController::class, 'removePhoto'])->name('businesses.photo.remove');
        Route::post('businesses/{business}/approve', [BusinessController::class, 'approve'])->name('businesses.approve');
        Route::post('businesses/{business}/reject', [BusinessController::class, 'reject'])->name('businesses.reject');
        Route::post('businesses/{business}/hide', [BusinessController::class, 'hide'])->name('businesses.hide');
        Route::delete('businesses/{business}/ratings/{rating}', [BusinessController::class, 'removeRating'])->name('businesses.ratings.remove');
        Route::post('businesses/{business}/changes/approve', [BusinessController::class, 'approveChanges'])->name('businesses.changes.approve');
        Route::post('businesses/{business}/changes/reject', [BusinessController::class, 'rejectChanges'])->name('businesses.changes.reject');
        Route::get('business-claims', [BusinessController::class, 'claims'])->name('business-claims.index');
        Route::post('business-claims/{claim}/approve', [BusinessController::class, 'approveClaim'])->name('business-claims.approve');
        Route::post('business-claims/{claim}/reject', [BusinessController::class, 'rejectClaim'])->name('business-claims.reject');

        Route::get('directory-cities', [DirectoryCityController::class, 'index'])->name('directory-cities.index');
        Route::post('directory-cities', [DirectoryCityController::class, 'store'])->name('directory-cities.store');
        Route::put('directory-cities/{city}', [DirectoryCityController::class, 'update'])->name('directory-cities.update');
        Route::delete('directory-cities/{city}', [DirectoryCityController::class, 'destroy'])->name('directory-cities.destroy');

        Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
        Route::get('questions/{question}', [QuestionController::class, 'show'])->name('questions.show');
        Route::post('questions/{question}/solve', [QuestionController::class, 'solve'])->name('questions.solve');
        Route::post('questions/{question}/promote', [QuestionController::class, 'promote'])->name('questions.promote');

        Route::get('faq', [FaqController::class, 'index'])->name('faq.index');
        Route::post('faq', [FaqController::class, 'storeFaq'])->name('faq.store');
        Route::put('faq/{faq}', [FaqController::class, 'updateFaq'])->name('faq.update');
        Route::delete('faq/{faq}', [FaqController::class, 'destroyFaq'])->name('faq.destroy');
        Route::post('knowledge', [FaqController::class, 'storeArticle'])->name('knowledge.store');
        Route::put('knowledge/{article}', [FaqController::class, 'updateArticle'])->name('knowledge.update');
        Route::delete('knowledge/{article}', [FaqController::class, 'destroyArticle'])->name('knowledge.destroy');

        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('announcements/{announcement}/approve', [AnnouncementController::class, 'approve'])->name('announcements.approve');
        Route::post('announcements/{announcement}/reject', [AnnouncementController::class, 'reject'])->name('announcements.reject');
        Route::post('announcement-topics', [AnnouncementController::class, 'storeTopic'])->name('announcement-topics.store');
        Route::put('announcement-topics/{topic}', [AnnouncementController::class, 'updateTopic'])->name('announcement-topics.update');
        Route::match(['get', 'put'], 'members/{member}/leader-topics', [AnnouncementController::class, 'leaderTopics'])->name('members.leader-topics');

        Route::get('moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::post('moderation/resolve', [ModerationController::class, 'resolve'])->name('moderation.resolve');

        Route::get('goods/posts', [GoodsController::class, 'posts'])->name('goods.posts');
        Route::post('goods/posts/{post}/hide', [GoodsController::class, 'hide'])->name('goods.posts.hide');
        Route::get('goods/matches', [GoodsController::class, 'matches'])->name('goods.matches');
        Route::post('goods/matches/{match}/resolve', [GoodsController::class, 'resolve'])->name('goods.matches.resolve');

        Route::get('employer-reports', [EmployerReportController::class, 'index'])->name('employer-reports.index');
        Route::put('employer-reports/{report}', [EmployerReportController::class, 'update'])->name('employer-reports.update');

        Route::get('job-settings', [JobSettingsController::class, 'show'])->name('job-settings.show');
        Route::put('job-settings', [JobSettingsController::class, 'update'])->name('job-settings.update');
    });
});
