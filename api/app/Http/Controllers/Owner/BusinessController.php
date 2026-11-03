<?php

namespace App\Http\Controllers\Owner;

use App\Bot\Choices;
use App\Directory\BusinessPublisher;
use App\Directory\PhotoStore;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\BusinessOwner;
use App\Models\DirectoryCity;
use App\Support\MapLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * An owner's own listings. A new listing waits for a moderator. An edit to
 * a listing that is not live yet replaces it and goes back in the queue; an
 * edit to a live listing waits in `pending_changes` while the live version
 * stays up. Photos are always reviewed.
 */
class BusinessController extends Controller
{
    public function choices(): JsonResponse
    {
        return response()->json([
            'categories' => Choices::BUSINESS_CATEGORIES,
            'cities' => DirectoryCity::listed()->map(fn (DirectoryCity $c) => [
                'id' => $c->id, 'key' => $c->key, 'name' => ['en' => $c->name_en, 'am' => $c->name_am, 'ti' => $c->name_ti],
            ])->values(),
            'languages' => Choices::SPOKEN_LANGUAGES,
            'limits' => ['listings' => BusinessOwner::MAX_LISTINGS, 'pending' => BusinessOwner::MAX_PENDING, 'photo_kb' => PhotoStore::MAX_UPLOAD_KB],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();

        return response()->json([
            'owner' => $owner->payload(),
            'businesses' => $owner->businesses()->with(['city', 'photos'])->latest('id')->get()->map(fn (Business $b) => $this->payload($b))->values(),
            'claims' => $owner->claims()->with('business:id,name,slug,status')->latest('id')->get()->map(fn (BusinessClaim $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'note' => $c->note,
                'review_note' => $c->review_note,
                'business' => $c->business ? ['id' => $c->business->id, 'name' => $c->business->name, 'slug' => $c->business->slug] : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        return response()->json(['business' => $this->payload($business->load(['city', 'photos']))]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();

        if ($owner->businesses()->count() >= BusinessOwner::MAX_LISTINGS) {
            throw ValidationException::withMessages(['name' => ['You already have '.BusinessOwner::MAX_LISTINGS.' listings, the most one account can manage.']]);
        }
        if ($owner->businesses()->where('status', 'pending')->count() >= BusinessOwner::MAX_PENDING) {
            throw ValidationException::withMessages(['name' => ['You have '.BusinessOwner::MAX_PENDING.' listings waiting for review. Add more once they are checked.']]);
        }

        $data = $this->validated($request);
        $this->refuseDuplicate($data);
        $data += $this->pin($data['maps_url']);

        $business = Business::query()->create($data + [
            'owner_id' => $owner->id,
            'submitted_by' => $owner->member_id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        AuditLog::record(null, 'directory.owner_submitted', 'business', $business->id, null, ['owner_id' => $owner->id]);

        return response()->json(['business' => $this->payload($business->load(['city', 'photos']))], 201);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $data = $this->validated($request);
        $this->refuseDuplicate($data, $business->id);
        // The pin follows the map link; a pin a moderator placed stays while the link is unchanged.
        if ($data['maps_url'] !== $business->maps_url) {
            $data += $this->pin($data['maps_url']);
        }

        if (in_array($business->status, ['pending', 'rejected'], true)) {
            // Not live yet: the edit replaces the submission, which goes back in the queue.
            $business->fill($data);
            $business->forceFill(['status' => 'pending', 'submitted_at' => now(), 'review_note' => null])->save();
        } else {
            $changes = $this->changes($business, $data);
            $hasPhoto = $business->photos()->where('approved', false)->exists();
            $business->forceFill([
                'pending_changes' => $changes === [] ? null : $changes,
                'changes_submitted_at' => ($changes !== [] || $hasPhoto) ? now() : null,
            ])->save();
        }

        AuditLog::record(null, 'directory.owner_edited', 'business', $business->id, null, ['owner_id' => $business->owner_id]);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'photos']))]);
    }

    public function photo(Request $request, Business $business, PhotoStore $photos): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $request->validate([
            'photo' => ['required', 'file', 'max:'.PhotoStore::MAX_UPLOAD_KB, 'mimetypes:image/jpeg,image/png,image/webp'],
        ], ['photo.max' => 'The photo is larger than 8 MB.', 'photo.mimetypes' => 'Use a JPEG, PNG or WebP photo.']);

        $photos->store($business, (string) file_get_contents($request->file('photo')->getRealPath()));

        if ($business->status === 'approved' || $business->status === 'hidden') {
            $business->forceFill(['changes_submitted_at' => now()])->save();
        }

        return response()->json(['business' => $this->payload($business->fresh(['city', 'photos']))]);
    }

    /** Withdraw a listing that is not live, or take a live one down. */
    public function destroy(Request $request, Business $business, BusinessPublisher $publisher): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        if (in_array($business->status, ['pending', 'rejected'], true)) {
            $business->delete();
            AuditLog::record(null, 'directory.owner_withdrew', 'business', $business->id);

            return response()->json(['ok' => true, 'deleted' => true]);
        }

        $business->forceFill(['status' => 'hidden', 'review_note' => 'Taken down by the owner.', 'pending_changes' => null, 'changes_submitted_at' => null])->save();
        $publisher->unpost($business);
        AuditLog::record(null, 'directory.owner_unlisted', 'business', $business->id);

        return response()->json(['ok' => true, 'deleted' => false, 'business' => $this->payload($business->fresh(['city', 'photos']))]);
    }

    /** "This listing is mine": for listings the community added before the owner arrived. */
    public function claim(Request $request, Business $business): JsonResponse
    {
        /** @var BusinessOwner $owner */
        $owner = $request->user();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        abort_unless($business->status === 'approved', 404);

        if ($business->owner_id !== null) {
            throw ValidationException::withMessages(['note' => [$business->owner_id === $owner->id ? 'This listing is already yours.' : 'This listing already has an owner. If that is wrong, contact the moderators.']]);
        }
        if ($owner->claims()->where('status', 'pending')->count() >= 3) {
            throw ValidationException::withMessages(['note' => ['You have 3 claims waiting. Wait until they are checked.']]);
        }

        $claim = BusinessClaim::query()->where('business_id', $business->id)->where('owner_id', $owner->id)->first();
        if ($claim !== null) {
            throw ValidationException::withMessages(['note' => [$claim->status === 'pending' ? 'Your claim is already waiting for a moderator.' : 'A moderator already decided on your claim.']]);
        }

        $claim = BusinessClaim::query()->create(['business_id' => $business->id, 'owner_id' => $owner->id, 'note' => $data['note'] ?? null, 'status' => 'pending']);
        AuditLog::record(null, 'directory.claimed', 'business', $business->id, null, ['owner_id' => $owner->id, 'claim_id' => $claim->id]);

        return response()->json(['claim' => ['id' => $claim->id, 'status' => $claim->status]], 201);
    }

    // ---- helpers ------------------------------------------------------------------

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->owner_id === $request->user()->id, 404);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Choices::BUSINESS_CATEGORIES))],
            'city_id' => ['required', 'integer', Rule::exists('directory_cities', 'id')->where('active', true)],
            'area' => ['required', 'string', 'min:2', 'max:160'],
            'maps_url' => ['nullable', 'url:http,https', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'telegram' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z][A-Za-z0-9_]{3,31}$/'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'description' => ['nullable', 'string', 'max:300'],
            'about' => ['nullable', 'string', 'max:1500'],
            'hours' => ['nullable', 'string', 'max:160'],
            'languages' => ['nullable', 'array', 'max:6'],
            'languages.*' => ['string', Rule::in(array_keys(Choices::SPOKEN_LANGUAGES))],
        ], [
            'telegram.regex' => 'A Telegram username is 5 to 32 letters, digits or underscores.',
        ], ['city_id' => 'city', 'maps_url' => 'map link']);

        foreach (['phone', 'whatsapp'] as $field) {
            if (filled($data[$field] ?? null)) {
                $phone = MapLocation::phone($data[$field]);
                if ($phone === null) {
                    throw ValidationException::withMessages([$field => ['That does not look like a phone number.']]);
                }
                $data[$field] = $phone;
            } else {
                $data[$field] = null;
            }
        }

        $data['telegram'] = filled($data['telegram'] ?? null) ? ltrim($data['telegram'], '@') : null;
        $data['languages'] = array_values(array_unique($data['languages'] ?? []));

        foreach (['maps_url', 'website', 'description', 'about', 'hours'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }
        $data['name'] = trim($data['name']);
        $data['area'] = trim($data['area']);

        return $data;
    }

    /** A pasted maps link with coordinates places the pin; no link, no pin. */
    private function pin(?string $mapsUrl): array
    {
        $link = $mapsUrl !== null ? MapLocation::fromLink($mapsUrl) : null;

        return $link && $link['latitude'] !== null
            ? ['latitude' => $link['latitude'], 'longitude' => $link['longitude']]
            : ['latitude' => null, 'longitude' => null];
    }

    private function refuseDuplicate(array $data, ?int $exceptId = null): void
    {
        $exists = Business::query()
            ->where('city_id', $data['city_id'])
            ->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])
            ->whereIn('status', ['pending', 'approved', 'hidden'])
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();

        if ($exists !== null) {
            throw ValidationException::withMessages(['name' => [
                $exists->status === 'approved' && $exists->owner_id === null
                    ? 'This business is already in the directory. Open its page and claim it instead.'
                    : 'A business with this name is already listed in this city.',
            ]]);
        }
    }

    /** The fields the owner changed on a live listing. */
    private function changes(Business $business, array $data): array
    {
        $changes = [];

        foreach (Business::OWNER_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $now = $business->{$field};
            $new = $data[$field];
            $same = is_array($new) || is_array($now)
                ? array_values((array) $now) === array_values((array) $new)
                : (string) ($now ?? '') === (string) ($new ?? '');
            if (! $same) {
                $changes[$field] = $new;
            }
        }

        return $changes;
    }

    private function payload(Business $b): array
    {
        $cover = $b->cover();
        $pending = $b->photos->where('approved', false)->sortByDesc('id')->first();

        return [
            'id' => $b->id,
            'slug' => $b->slug,
            'status' => $b->status,
            'review_note' => $b->review_note,
            'name' => $b->name,
            'category' => $b->category,
            'city_id' => $b->city_id,
            'city' => $b->city ? ['en' => $b->city->name_en, 'am' => $b->city->name_am, 'ti' => $b->city->name_ti] : null,
            'area' => $b->area,
            'maps_url' => $b->maps_url,
            'phone' => $b->phone,
            'whatsapp' => $b->whatsapp,
            'telegram' => $b->telegram,
            'website' => $b->website,
            'description' => $b->description,
            'about' => $b->about,
            'hours' => $b->hours,
            'languages' => $b->languages ?? [],
            'verified' => $b->verified,
            'photo' => $cover?->url(),
            'pending_photo' => $pending?->url(),
            'pending_changes' => $b->pending_changes,
            'changes_in_review' => $b->changes_submitted_at !== null,
            'views' => (int) $b->views,
            'submitted_at' => $b->submitted_at?->toIso8601String(),
            'updated_at' => $b->updated_at?->toIso8601String(),
        ];
    }
}
