<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Choices;
use App\Directory\BusinessPublisher;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Directory\PhotoStore;
use App\Models\BusinessPhoto;
use App\Models\BusinessRating;
use App\Models\BusinessReport;
use App\Models\DirectoryCity;
use App\Support\MapLocation;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The directory for moderators: suggestions from members, submissions and
 * edits from business owners, ownership claims, reports. Every decision is
 * carried out by BusinessPublisher, which also updates the channel and
 * tells the owner.
 */
class BusinessController extends Controller
{
    public function __construct(private readonly BusinessPublisher $publisher) {}

    public function index(Request $request): JsonResponse
    {
        $query = Business::query()->with(['city:id,name_en', 'submitter:id,display_name', 'owner:id,display_name,telegram_username', 'photos:id,business_id,kind,approved,updated_at'])
            ->withCount(['claims as claims_pending' => fn ($q) => $q->where('status', 'pending')])
            ->latest('id');

        $status = $request->query('status', 'pending');
        if ($status === 'changes') {
            $query->whereIn('status', ['approved', 'hidden'])->whereNotNull('changes_submitted_at')->reorder('changes_submitted_at');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        // A number, or no filter: Postgres answers "abc" for an integer column with an error (a 500).
        if (($cityId = $request->integer('city_id')) > 0) {
            $query->where('city_id', $cityId);
        }
        if (is_string($category = $request->query('category')) && $category !== '') {
            $query->where('category', $category);
        }
        if ($request->boolean('owners')) {
            $query->whereNotNull('owner_id');
        }
        if ($search = trim((string) $request->query('search', ''))) {
            Search::anyColumn($query, ['name', 'area', 'phone'], $search);
        }

        $page = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        $counts = Business::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $counts['changes'] = Business::query()->whereIn('status', ['approved', 'hidden'])->whereNotNull('changes_submitted_at')->count();
        $counts['claims'] = BusinessClaim::query()->where('status', 'pending')->count();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Business $b) => $this->payload($b))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => $counts,
            'choices' => [
                'categories' => array_map(fn (array $l) => $l['en'], Choices::BUSINESS_CATEGORIES),
                'cities' => DirectoryCity::query()->orderBy('sort')->get(['id', 'name_en', 'active']),
                'languages' => array_map(fn (array $l) => $l['en'], Choices::SPOKEN_LANGUAGES),
            ],
        ]);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load(['city', 'submitter:id,display_name,telegram_username', 'owner', 'photos:id,business_id,kind,approved,updated_at', 'reports.member:id,display_name', 'claims.owner']);

        return response()->json([
            'business' => $this->payload($business) + [
                'reports' => $business->reports->map(fn (BusinessReport $r) => ['member' => $r->member?->display_name, 'reason' => $r->reason, 'at' => $r->created_at?->toIso8601String()]),
                'claims' => $business->claims->map(fn (BusinessClaim $c) => $this->claimPayload($c))->values(),
                // Who gave what, newest first, so a moderator can see a run of fake stars and take them off.
                'ratings' => $business->ratings()->with('member:id,display_name,telegram_username,banned_at')->latest('updated_at')->limit(100)->get()
                    ->map(fn (BusinessRating $r) => [
                        'id' => $r->id,
                        'stars' => (int) $r->stars,
                        'member' => $r->member ? ['id' => $r->member->id, 'name' => $r->member->display_name, 'username' => $r->member->telegram_username, 'banned' => $r->member->banned_at !== null] : null,
                        'at' => $r->updated_at?->toIso8601String(),
                    ])->values(),
            ],
            'audit' => AuditLog::query()->where('subject_type', 'business')->where('subject_id', $business->id)->latest('created_at')->limit(30)->get(),
        ]);
    }

    /** A moderator takes one member's stars off a listing. */
    public function removeRating(Request $request, Business $business, BusinessRating $rating): JsonResponse
    {
        abort_if($rating->business_id !== $business->id, 404);

        $before = ['member_id' => $rating->member_id, 'stars' => (int) $rating->stars];
        $rating->delete();
        BusinessRating::recount($business->id);
        AuditLog::record($request->user()->id, 'directory.rating_removed', 'business', $business->id, $before);

        return response()->json(['ok' => true]);
    }

    /** Added by an admin: listed at once, and posted to the channel. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $business = Business::query()->create($data + ['status' => 'pending']);
        $this->publisher->approve($business, $request->user()->id);
        AuditLog::record($request->user()->id, 'directory.created', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))], 201);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $this->validated($request, partial: true);
        $before = $business->only(array_keys($data));
        $business->fill($data);

        // A moderator's correction wins: the owner's queued change to the same field is dropped,
        // so a later "Approve changes" cannot put the old value back.
        if (is_array($business->pending_changes)) {
            $remaining = array_diff_key($business->pending_changes, $data);
            $business->pending_changes = $remaining === [] ? null : $remaining;
            if ($remaining === [] && ! $business->photos()->where('approved', false)->exists()) {
                $business->changes_submitted_at = null;
            }
        }

        $business->save();
        if ($business->status === 'approved') {
            $this->publisher->refresh($business);
        }
        AuditLog::record($request->user()->id, 'directory.updated', 'business', $business->id, $before, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    /**
     * A photo from a moderator goes live at once: re-encoded like an owner's,
     * replacing the cover, and the channel post is refreshed with it.
     */
    public function photo(Request $request, Business $business, PhotoStore $photos): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'max:'.PhotoStore::MAX_UPLOAD_KB, 'mimetypes:image/jpeg,image/png,image/webp'],
        ], ['photo.max' => 'The photo is larger than '.intdiv(PhotoStore::MAX_UPLOAD_KB, 1024).' MB.', 'photo.mimetypes' => 'Send a JPEG, PNG or WebP photo.']);

        $photos->store($business, (string) file_get_contents($request->file('photo')->getRealPath()));
        $photos->approvePending($business);

        if ($business->status === 'approved') {
            $this->publisher->photoChanged($business);
        }
        AuditLog::record($request->user()->id, 'directory.photo_set', 'business', $business->id);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    /** Takes the cover photo down (and any photo waiting for review); the listing keeps its monogram. */
    public function removePhoto(Request $request, Business $business): JsonResponse
    {
        $business->photos()->where('kind', 'cover')->delete();

        if ($business->status === 'approved') {
            $this->publisher->photoChanged($business);
        }
        AuditLog::record($request->user()->id, 'directory.photo_removed', 'business', $business->id);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    /** Approve a suggestion or a submission, or put a hidden listing back. Clears reports that took it down. */
    public function approve(Request $request, Business $business): JsonResponse
    {
        $wasHidden = $business->status === 'hidden';
        $this->publisher->approve($business, $request->user()->id);

        if ($wasHidden) {
            BusinessReport::query()->where('business_id', $business->id)->delete();
            $business->forceFill(['reports_count' => 0])->save();
        }

        AuditLog::record($request->user()->id, 'directory.approved', 'business', $business->id);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    public function reject(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        $business->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ])->save();
        // A listing that was live leaves the channel too.
        $this->publisher->unpost($business);
        $this->publisher->rejected($business);
        AuditLog::record($request->user()->id, 'directory.rejected', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    public function hide(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        $business->forceFill(['status' => 'hidden', 'review_note' => $data['note'] ?? 'Hidden by an admin.'])->save();
        $this->publisher->unpost($business);
        AuditLog::record($request->user()->id, 'directory.hidden', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    public function destroy(Request $request, Business $business): JsonResponse
    {
        $before = $this->payload($business);
        $this->publisher->unpost($business);

        // Telegram refused to remove the channel post: deleting now would leave it up with
        // nothing pointing at it. Hide the listing and let the admin try again.
        if ($business->channel_message_id && \App\Support\JobSettings::channel() !== '') {
            $business->forceFill(['status' => 'hidden'])->save();

            return response()->json(['message' => 'The channel post could not be removed, so the listing is hidden instead. Delete the post in Telegram, then delete the listing again.'], 409);
        }

        $business->delete();
        AuditLog::record($request->user()->id, 'directory.deleted', 'business', $before['id'], $before);

        return response()->json(['ok' => true]);
    }

    public function approveChanges(Request $request, Business $business): JsonResponse
    {
        if ($business->changes_submitted_at === null) {
            throw ValidationException::withMessages(['changes' => ['There are no changes waiting on this listing.']]);
        }

        $this->publisher->approveChanges($business->load('owner'), $request->user()->id);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    public function rejectChanges(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        if ($business->changes_submitted_at === null) {
            throw ValidationException::withMessages(['changes' => ['There are no changes waiting on this listing.']]);
        }

        $this->publisher->rejectChanges($business->load('owner'), $request->user()->id, $data['note'] ?? null);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter', 'owner', 'photos']))]);
    }

    // ---- ownership claims -----------------------------------------------------------

    public function claims(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $claims = BusinessClaim::query()->with(['business.city', 'owner.member'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->oldest('id')->limit(100)->get();

        return response()->json(['data' => $claims->map(fn (BusinessClaim $c) => $this->claimPayload($c))->values()]);
    }

    public function approveClaim(Request $request, BusinessClaim $claim): JsonResponse
    {
        // Locked, so two moderators approving rival claims at once cannot both win.
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $claim): void {
            $fresh = BusinessClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $business = \App\Models\Business::query()->lockForUpdate()->findOrFail($fresh->business_id);

            if ($fresh->status !== 'pending') {
                throw ValidationException::withMessages(['claim' => ['This claim was already decided.']]);
            }
            if ($business->owner_id !== null && $business->owner_id !== $fresh->owner_id) {
                throw ValidationException::withMessages(['claim' => ['This listing already has another owner. Reject this claim, or remove that owner first.']]);
            }

            $fresh->forceFill(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
            $business->forceFill(['owner_id' => $fresh->owner_id])->save();

            // One owner per listing: the other claims on it are answered too.
            BusinessClaim::query()->where('business_id', $fresh->business_id)->whereKeyNot($fresh->id)->where('status', 'pending')
                ->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => 'Another owner was confirmed for this listing.', 'updated_at' => now()]);
        });

        $claim->refresh()->load(['business', 'owner']);
        BusinessRating::forgetOwners($claim->business);

        AuditLog::record($request->user()->id, 'directory.claim_approved', 'business', $claim->business_id, null, ['owner_id' => $claim->owner_id]);
        $this->publisher->claimDecided($claim);

        return response()->json(['claim' => $this->claimPayload($claim->fresh(['business.city', 'owner.member']))]);
    }

    public function rejectClaim(Request $request, BusinessClaim $claim): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages(['claim' => ['This claim was already decided.']]);
        }

        $claim->forceFill(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null])->save();
        AuditLog::record($request->user()->id, 'directory.claim_rejected', 'business', $claim->business_id, null, ['owner_id' => $claim->owner_id] + $data);
        $this->publisher->claimDecided($claim->load(['business', 'owner']));

        return response()->json(['claim' => $this->claimPayload($claim->fresh(['business.city', 'owner.member']))]);
    }

    // ---- helpers ------------------------------------------------------------------------

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'city_id' => [$req, 'integer', Rule::exists('directory_cities', 'id')],
            'category' => [$req, Rule::in(array_keys(Choices::BUSINESS_CATEGORIES))],
            'name' => [$req, 'string', 'min:2', 'max:120'],
            'area' => [$req, 'string', 'min:2', 'max:160'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'maps_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'telegram' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z][A-Za-z0-9_]{3,31}$/'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'about' => ['sometimes', 'nullable', 'string', 'max:1500'],
            'hours' => ['sometimes', 'nullable', 'string', 'max:160'],
            'languages' => ['sometimes', 'nullable', 'array', 'max:6'],
            'languages.*' => ['string', Rule::in(array_keys(Choices::SPOKEN_LANGUAGES))],
            'verified' => ['sometimes', 'boolean'],
        ], [], ['city_id' => 'city', 'maps_url' => 'map link']);

        foreach (['phone', 'whatsapp'] as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $phone = MapLocation::phone($data[$field]);
                if ($phone === null) {
                    throw ValidationException::withMessages([$field => ['That does not look like a phone number.']]);
                }
                $data[$field] = $phone;
            }
        }

        if (array_key_exists('telegram', $data) && filled($data['telegram'])) {
            $data['telegram'] = ltrim($data['telegram'], '@');
        }

        // A pasted maps link with coordinates fills the pin when none was given.
        if (filled($data['maps_url'] ?? null) && empty($data['latitude'])) {
            $link = MapLocation::fromLink($data['maps_url']);
            if ($link && $link['latitude'] !== null) {
                $data['latitude'] = $link['latitude'];
                $data['longitude'] = $link['longitude'];
            }
        }

        return $data;
    }

    private function payload(Business $b): array
    {
        $photos = $b->relationLoaded('photos') ? $b->photos : collect();
        $live = $photos->where('approved', true)->sortByDesc('id')->first();
        $pending = $photos->where('approved', false)->sortByDesc('id')->first();

        return [
            'id' => $b->id,
            'slug' => $b->slug,
            'name' => $b->name,
            'category' => $b->category,
            'city_id' => $b->city_id,
            'city' => $b->city?->name_en,
            'area' => $b->area,
            'latitude' => $b->latitude,
            'longitude' => $b->longitude,
            'maps_url' => $b->maps_url,
            'map_link' => $b->mapLink(),
            'phone' => $b->phone,
            'whatsapp' => $b->whatsapp,
            'telegram' => $b->telegram,
            'website' => $b->website,
            'description' => $b->description,
            'about' => $b->about,
            'hours' => $b->hours,
            'languages' => $b->languages ?? [],
            'status' => $b->status,
            'verified' => $b->verified,
            'review_note' => $b->review_note,
            'reports_count' => $b->reports_count,
            'views' => (int) $b->views,
            'rating_avg' => $b->rating_avg !== null ? (float) $b->rating_avg : null,
            'rating_count' => (int) $b->rating_count,
            'photo' => $live instanceof BusinessPhoto ? $live->url() : null,
            'pending_photo' => $pending instanceof BusinessPhoto ? $pending->url() : null,
            'pending_changes' => $b->pending_changes,
            'changes_submitted_at' => $b->changes_submitted_at?->toIso8601String(),
            'claims_pending' => (int) ($b->claims_pending ?? 0),
            'posted_to_channel' => $b->channel_message_id !== null,
            'submitted_by' => $b->submitter ? ['id' => $b->submitter->id, 'name' => $b->submitter->display_name] : null,
            'owner' => $b->owner ? ['id' => $b->owner->id, 'name' => $b->owner->display_name, 'username' => $b->owner->telegram_username] : null,
            'submitted_at' => $b->submitted_at?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    private function claimPayload(BusinessClaim $c): array
    {
        return [
            'id' => $c->id,
            'status' => $c->status,
            'note' => $c->note,
            'review_note' => $c->review_note,
            'business' => $c->business ? [
                'id' => $c->business->id, 'name' => $c->business->name, 'area' => $c->business->area,
                'city' => $c->business->city?->name_en, 'phone' => $c->business->phone, 'owner_id' => $c->business->owner_id,
            ] : null,
            'owner' => $c->owner ? [
                'id' => $c->owner->id, 'name' => $c->owner->display_name, 'username' => $c->owner->telegram_username,
                'member_id' => $c->owner->member_id, 'member_since' => $c->owner->member?->verified_at?->toIso8601String(),
            ] : null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
