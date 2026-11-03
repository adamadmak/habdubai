<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\DirectoryCity;
use App\Support\MapLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Business::query()->with(['city:id,name_en', 'submitter:id,display_name'])->latest('id');

        $status = $request->query('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($cityId = $request->query('city_id')) {
            $query->where('city_id', $cityId);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('area', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%'));
        }

        $page = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Business $b) => $this->payload($b))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => Business::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'choices' => [
                'categories' => array_map(fn (array $l) => $l['en'], Choices::BUSINESS_CATEGORIES),
                'cities' => DirectoryCity::query()->orderBy('sort')->get(['id', 'name_en', 'active']),
            ],
        ]);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load(['city', 'submitter:id,display_name,telegram_username', 'reports.member:id,display_name']);

        return response()->json([
            'business' => $this->payload($business) + [
                'reports' => $business->reports->map(fn (BusinessReport $r) => ['member' => $r->member?->display_name, 'reason' => $r->reason, 'at' => $r->created_at?->toIso8601String()]),
            ],
            'audit' => AuditLog::query()->where('subject_type', 'business')->where('subject_id', $business->id)->latest('created_at')->limit(30)->get(),
        ]);
    }

    /** Added by an admin: listed at once. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $business = Business::query()->create($data + ['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        AuditLog::record($request->user()->id, 'directory.created', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter']))], 201);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $this->validated($request, partial: true);
        $before = $business->only(array_keys($data));
        $business->fill($data)->save();
        AuditLog::record($request->user()->id, 'directory.updated', 'business', $business->id, $before, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter']))]);
    }

    /** Approve a suggestion, or put a hidden listing back. Clears reports that took it down. */
    public function approve(Request $request, Business $business): JsonResponse
    {
        $wasHidden = $business->status === 'hidden';
        $business->forceFill([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        if ($wasHidden) {
            BusinessReport::query()->where('business_id', $business->id)->delete();
            $business->forceFill(['reports_count' => 0])->save();
        }

        AuditLog::record($request->user()->id, 'directory.approved', 'business', $business->id);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter']))]);
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
        AuditLog::record($request->user()->id, 'directory.rejected', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter']))]);
    }

    public function hide(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        $business->forceFill(['status' => 'hidden', 'review_note' => $data['note'] ?? 'Hidden by an admin.'])->save();
        AuditLog::record($request->user()->id, 'directory.hidden', 'business', $business->id, null, $data);

        return response()->json(['business' => $this->payload($business->fresh(['city', 'submitter']))]);
    }

    public function destroy(Request $request, Business $business): JsonResponse
    {
        $before = $this->payload($business);
        $business->delete();
        AuditLog::record($request->user()->id, 'directory.deleted', 'business', $before['id'], $before);

        return response()->json(['ok' => true]);
    }

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
            'maps_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'telegram' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z][A-Za-z0-9_]{3,31}$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'verified' => ['sometimes', 'boolean'],
        ], [], ['city_id' => 'city', 'maps_url' => 'map link']);

        if (array_key_exists('phone', $data) && filled($data['phone'])) {
            $phone = MapLocation::phone($data['phone']);
            if ($phone === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone' => ['That does not look like a phone number.']]);
            }
            $data['phone'] = $phone;
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
        return [
            'id' => $b->id,
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
            'telegram' => $b->telegram,
            'description' => $b->description,
            'status' => $b->status,
            'verified' => $b->verified,
            'review_note' => $b->review_note,
            'reports_count' => $b->reports_count,
            'submitted_by' => $b->submitter ? ['id' => $b->submitter->id, 'name' => $b->submitter->display_name] : null,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
