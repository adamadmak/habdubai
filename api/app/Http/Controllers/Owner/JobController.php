<?php

namespace App\Http\Controllers\Owner;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Jobs\Pipeline\Deduper;
use App\Jobs\Pipeline\JobIngest;
use App\Jobs\Pipeline\JobPublisher;
use App\Jobs\Sources\RawListing;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\Job;
use App\Models\JobRaw;
use App\Models\JobSource;
use App\Support\MapLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A business posts its own open positions. Only an owner with an approved
 * listing can, and the post goes through the same pipeline as every other job:
 * scam score, translation, then publish or hold (ProcessJobListing). The form
 * asks only what the listing cannot answer; where the job is comes from the listing.
 */
class JobController extends Controller
{
    /** Positions one owner may have open or in review at once, and may post in a day. */
    public const MAX_OPEN = 10;

    public const MAX_PER_DAY = 5;

    public function index(Request $request): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();
        $ids = $owner->businesses()->pluck('id');

        // Just posted and not through the pipeline yet: shown as being checked, so it never seems lost.
        $checking = JobRaw::query()->whereIn('business_id', $ids)->whereNull('processed_at')->latest('id')->get()
            ->map(fn (JobRaw $raw) => ['id' => null, 'title' => $raw->title, 'company' => $raw->company, 'status' => 'review', 'reason' => null, 'clicks' => 0, 'posted_at' => $raw->fetched_at?->toIso8601String()]);

        $jobs = Job::query()->whereIn('business_id', $ids)->whereIn('status', ['pending', 'review', 'published', 'closed', 'rejected'])
            ->where(fn ($q) => $q->whereNull('review_reason')->orWhere('review_reason', '!=', Job::WITHDRAWN))
            ->latest('id')->limit(50)->get()
            ->map(fn (Job $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->company,
                'status' => in_array($job->status, ['pending', 'review'], true) ? 'review' : $job->status,
                // Only a moderator's refusal. What the scam rules saw is not explained to the person they caught.
                'reason' => $job->status === 'rejected' && $job->reviewed_by ? $job->review_reason : null,
                'clicks' => (int) $job->clicks,
                'posted_at' => ($job->published_at ?? $job->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'jobs' => $checking->concat($jobs)->values(),
            'businesses' => $owner->businesses()->where('status', 'approved')->orderBy('name')->get()->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'verified' => (bool) $b->verified,
                'contact' => $b->whatsapp ?? ($b->telegram ? '@'.$b->telegram : $b->phone),
            ])->values(),
            'waiting' => $owner->businesses()->where('status', 'pending')->exists(),
        ]);
    }

    public function store(Request $request, JobIngest $ingest): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();

        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Choices::TRADES))],
            'description' => ['required', 'string', 'min:20', 'max:1500'],
            'salary' => ['nullable', 'integer', 'min:100', 'max:200000'],
            'accommodation' => ['nullable', 'boolean'],
            'visa' => ['nullable', 'boolean'],
            'contact' => ['required', 'string', 'max:60'],
        ]);

        /** @var Business|null $business */
        $business = $owner->businesses()->with('city')->whereKey($data['business_id'])->first();
        if ($business === null || $business->status !== 'approved') {
            throw ValidationException::withMessages(['business_id' => [__('owner.job_no_listing')]]);
        }

        $contact = $this->contactLink($data['contact']);
        $title = RawListing::clean($data['title'], 120) ?? '';
        $this->refuseOverLimits($owner, $business, $title);

        $text = $title."\n".trim($data['description']);
        $stored = $ingest->storeOne(JobSource::businessOwners(), new RawListing(
            externalKey: 'business-'.$business->id.'-'.Str::uuid()->toString(),
            text: $text,
            title: $title,
            company: $business->name,
            location: $business->area,
        ), $business->id, [
            'category' => $data['category'],
            'emirate' => $this->emirateOf($business),
            'area' => $business->area ? mb_substr($business->area, 0, 80) : null,
            'salary' => $data['salary'] ?? null,
            'accommodation' => (bool) ($data['accommodation'] ?? false),
            'visa' => (bool) ($data['visa'] ?? false),
            'contact' => $contact,
        ]);

        AuditLog::record(null, 'jobs.owner_posted', 'business', $business->id, null, ['owner_id' => $owner->id, 'raw_id' => $stored?->id]);

        return response()->json(['ok' => true, 'verified' => (bool) $business->verified], 201);
    }

    /** The position is filled, or a post still in review is taken back. */
    public function close(Request $request, Job $job, JobPublisher $publisher): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();
        abort_unless($job->business_id !== null && $owner->businesses()->whereKey($job->business_id)->exists(), 404);

        if ($job->status === 'published') {
            $publisher->close($job);
        } elseif (in_array($job->status, ['pending', 'review'], true)) {
            // It was never public, and closing would put it on the website as a closed job.
            $job->forceFill(['status' => 'rejected', 'review_reason' => Job::WITHDRAWN])->save();
        }

        AuditLog::record(null, 'jobs.owner_closed', 'job', $job->id, null, ['owner_id' => $owner->id]);

        return response()->json(['ok' => true]);
    }

    /** A WhatsApp number or a Telegram username, as the link that opens that chat. */
    private function contactLink(string $contact): string
    {
        $contact = trim($contact);

        if (preg_match('#^(?:https?://)?t(?:elegram)?\.me/([A-Za-z][A-Za-z0-9_]{4,31})/?$#', $contact, $m)
            || preg_match('/^@?([A-Za-z][A-Za-z0-9_]{4,31})$/', $contact, $m)) {
            return 'https://t.me/'.$m[1];
        }

        $phone = MapLocation::phone($contact);
        if ($phone === null) {
            throw ValidationException::withMessages(['contact' => [__('owner.job_contact')]]);
        }

        return 'https://wa.me/'.ltrim($phone, '+');
    }

    private function refuseOverLimits(BusinessOwner $owner, Business $business, string $title): void
    {
        $ids = $owner->businesses()->pluck('id');
        $unprocessed = JobRaw::query()->whereIn('business_id', $ids)->whereNull('processed_at');

        $open = Job::query()->whereIn('business_id', $ids)->whereIn('status', ['pending', 'review', 'published'])->count() + (clone $unprocessed)->count();
        if ($open >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['title' => [__('owner.job_limit', ['n' => self::MAX_OPEN])]]);
        }

        if (JobRaw::query()->whereIn('business_id', $ids)->where('fetched_at', '>=', now()->subDay())->count() >= self::MAX_PER_DAY) {
            throw ValidationException::withMessages(['title' => [__('owner.job_daily', ['n' => self::MAX_PER_DAY])]]);
        }

        $fingerprint = Deduper::fingerprint($title, $business->name);
        $repeated = Job::query()->where('business_id', $business->id)->where('fingerprint', $fingerprint)->whereIn('status', ['pending', 'review', 'published'])->exists()
            || (clone $unprocessed)->where('business_id', $business->id)->get(['title', 'company'])
                ->contains(fn (JobRaw $raw) => Deduper::fingerprint($raw->title, $raw->company) === $fingerprint);
        if ($repeated) {
            throw ValidationException::withMessages(['title' => [__('owner.job_duplicate')]]);
        }
    }

    /** A job is where the business is. Cities that are not an emirate of their own belong to one. */
    private function emirateOf(Business $business): string
    {
        return match ($business->city?->key) {
            'dubai' => 'dubai',
            'sharjah' => 'sharjah',
            'abu-dhabi', 'al-ain' => 'abu_dhabi',
            'ajman' => 'ajman',
            default => 'other_uae',
        };
    }
}
