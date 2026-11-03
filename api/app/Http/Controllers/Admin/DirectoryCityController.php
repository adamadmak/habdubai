<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DirectoryCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DirectoryCityController extends Controller
{
    public function index(): JsonResponse
    {
        $cities = DirectoryCity::query()->withCount([
            'businesses as listed_count' => fn ($q) => $q->where('status', 'approved'),
            'businesses as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ])->orderBy('sort')->orderBy('name_en')->get();

        return response()->json(['data' => $cities]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['key'] = $this->uniqueKey($data['name_en']);

        $city = DirectoryCity::query()->create($data);
        AuditLog::record($request->user()->id, 'directory.city_created', 'directory_city', $city->id, null, $data);

        return response()->json(['city' => $city], 201);
    }

    public function update(Request $request, DirectoryCity $city): JsonResponse
    {
        $data = $this->validated($request);
        $before = $city->only(array_keys($data));
        $city->fill($data)->save();
        AuditLog::record($request->user()->id, 'directory.city_updated', 'directory_city', $city->id, $before, $data);

        return response()->json(['city' => $city]);
    }

    /** Only an empty city can be deleted; one with businesses is turned off instead. */
    public function destroy(Request $request, DirectoryCity $city): JsonResponse
    {
        abort_if($city->businesses()->exists(), 422, 'This city has businesses. Turn it off instead, or move them first.');

        $before = $city->toArray();
        $city->delete();
        AuditLog::record($request->user()->id, 'directory.city_deleted', 'directory_city', $before['id'], $before);

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'min:2', 'max:80'],
            'name_am' => ['required', 'string', 'min:1', 'max:80'],
            'name_ti' => ['required', 'string', 'min:1', 'max:80'],
            'name_om' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['boolean'],
        ]) + ['sort' => 100, 'active' => true];
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'city';
        $key = $base;
        $n = 2;

        while (DirectoryCity::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$n++;
        }

        return $key;
    }
}
