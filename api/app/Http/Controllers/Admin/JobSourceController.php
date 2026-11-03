<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchJobSource;
use App\Jobs\Sources\Fetchers;
use App\Models\AuditLog;
use App\Models\JobSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JobSourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = JobSource::query()->orderBy('name')->get();

        $counts = \App\Models\Job::query()->selectRaw('source_id, status, count(*) as n')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('source_id', 'status')->get()
            ->groupBy('source_id');

        return response()->json([
            'data' => $sources->map(fn (JobSource $s) => $this->payload($s) + [
                'last_7_days' => ($counts->get($s->id) ?? collect())->pluck('n', 'status'),
            ])->all(),
            'kinds' => JobSource::KINDS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['key'] = $this->uniqueKey($data['name']);

        $source = JobSource::query()->create($data);
        AuditLog::record($request->user()->id, 'jobs.source_created', 'job_source', $source->id, null, $this->payload($source));

        return response()->json(['source' => $this->payload($source)], 201);
    }

    public function update(Request $request, JobSource $source): JsonResponse
    {
        abort_if($source->key === 'forwarded', 422, 'The forwarded source is built in.');

        $data = $this->validated($request, $source);
        $before = $this->payload($source);

        // Saving the form again is how a paused source is resumed.
        $source->fill($data)->forceFill(['paused_at' => null, 'pause_reason' => null, 'failures_in_row' => 0])->save();
        AuditLog::record($request->user()->id, 'jobs.source_updated', 'job_source', $source->id, $before, $this->payload($source));

        return response()->json(['source' => $this->payload($source)]);
    }

    public function destroy(Request $request, JobSource $source): JsonResponse
    {
        abort_if($source->key === 'forwarded', 422, 'The forwarded source is built in.');

        $before = $this->payload($source);
        $source->delete();
        AuditLog::record($request->user()->id, 'jobs.source_deleted', 'job_source', $before['id'], $before);

        return response()->json(['ok' => true]);
    }

    /** Fetch now, in this request, and report what was found and stored. */
    public function run(Request $request, JobSource $source): JsonResponse
    {
        abort_if($source->kind === 'telegram', 422, 'Telegram sources are pushed by Telegram; there is nothing to run.');

        $result = (new FetchJobSource($source->id))->handle(app(\App\Jobs\Pipeline\JobIngest::class));
        AuditLog::record($request->user()->id, 'jobs.source_run', 'job_source', $source->id, null, $result);

        return response()->json(['result' => $result, 'source' => $this->payload($source->fresh())]);
    }

    /** Try a configuration without saving it: what would this source find right now? */
    public function test(Request $request): JsonResponse
    {
        $kind = (string) $request->input('kind');
        abort_unless(in_array($kind, ['rss', 'html'], true), 422, 'Only RSS and HTML sources can be tested.');

        $request->validate(Fetchers::configRules($kind));

        $source = new JobSource(['kind' => $kind, 'config' => $request->input('config'), 'name' => 'test']);

        try {
            $listings = Fetchers::for($kind)->fetch($source);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage(), 'listings' => []]);
        }

        return response()->json([
            'ok' => true,
            'found' => count($listings),
            'listings' => array_map(fn ($l) => $l->toArray(), array_slice($listings, 0, 10)),
        ]);
    }

    private function validated(Request $request, ?JobSource $source = null): array
    {
        $kind = (string) $request->input('kind', $source?->kind);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(JobSource::KINDS)],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'active' => ['boolean'],
            'config' => ['required', 'array'],
        ] + Fetchers::configRules($kind));

        $allowed = array_map(fn (string $rule) => substr($rule, 7), array_keys(Fetchers::configRules($kind)));
        $data['config'] = array_intersect_key($data['config'], array_flip($allowed));
        $data['interval_minutes'] ??= 60;
        $data['active'] ??= true;

        return $data;
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'source';
        $key = $base;
        $n = 2;

        while (JobSource::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$n++;
        }

        return mb_substr($key, 0, 48);
    }

    private function payload(JobSource $s): array
    {
        return [
            'id' => $s->id,
            'key' => $s->key,
            'name' => $s->name,
            'kind' => $s->kind,
            'config' => $s->config,
            'interval_minutes' => $s->interval_minutes,
            'active' => $s->active,
            'built_in' => $s->key === 'forwarded',
            'status' => ! $s->active ? 'off' : ($s->paused_at ? 'paused' : ($s->failures_in_row > 0 ? 'failing' : 'ok')),
            'paused_at' => $s->paused_at?->toIso8601String(),
            'pause_reason' => $s->pause_reason,
            'last_run_at' => $s->last_run_at?->toIso8601String(),
            'last_success_at' => $s->last_success_at?->toIso8601String(),
            'last_error' => $s->last_error,
            'failures_in_row' => $s->failures_in_row,
            'last_found' => $s->last_found,
            'last_new' => $s->last_new,
        ];
    }
}
