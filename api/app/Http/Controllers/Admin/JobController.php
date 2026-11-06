<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Search;
use App\Jobs\Pipeline\Classification;
use App\Jobs\Pipeline\JobPublisher;
use App\Models\AuditLog;
use App\Models\Job;
use App\Models\JobReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The review queue and every job the pipeline has seen. */
class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Job::query()->with(['source:id,name', 'photos'])->latest('id');

        $status = $request->query('status', 'review');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            Search::anyColumn($query, ['title', 'company'], $search);
        }

        foreach (['category', 'emirate'] as $field) {
            if (is_string($value = $request->query($field)) && $value !== '') {
                $query->where($field, $value);
            }
        }
        // A number, or no filter: Postgres answers "abc" for an integer column with an error (a 500).
        if (($sourceId = $request->integer('source_id')) > 0) {
            $query->where('source_id', $sourceId);
        }

        $page = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        $counts = Job::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return response()->json([
            'data' => $page->getCollection()->map(fn (Job $j) => $this->summary($j))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => $counts,
        ]);
    }

    public function show(Job $job): JsonResponse
    {
        $job->load(['source:id,name,kind', 'raw', 'reports.member:id,display_name']);

        return response()->json([
            'job' => $this->summary($job) + [
                'summary_am' => $job->summary_am,
                'summary_ti' => $job->summary_ti,
                'summary_om' => $job->summary_om,
                'summary_en' => $job->summary_en,
                'languages' => $job->languages ?? [],
                'accommodation' => $job->accommodation,
                'visa' => $job->visa,
                'sub_area' => $job->sub_area,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'scam_reasons' => $job->scam_reasons ?? [],
                'classified_by' => $job->classified_by,
                'duplicate_of' => $job->duplicate_of,
                'raw_text' => $job->raw?->raw_text,
                'business' => $job->business ? ['name' => $job->business->name, 'slug' => $job->business->slug, 'verified' => (bool) $job->business->verified, 'status' => $job->business->status] : null,
                'apply_contact' => $job->apply_contact,
                'reports' => $job->reports->map(fn (JobReport $r) => ['member' => $r->member?->display_name, 'reason' => $r->reason, 'at' => $r->created_at?->toIso8601String()]),
            ],
            'audit' => AuditLog::query()->where('subject_type', 'job')->where('subject_id', $job->id)->latest('created_at')->limit(30)->get(),
            'choices' => ['categories' => Classification::CATEGORIES, 'emirates' => Classification::EMIRATES],
        ]);
    }

    /** Approve, optionally with corrections, and publish. */
    public function approve(Request $request, Job $job, JobPublisher $publisher): JsonResponse
    {
        abort_unless(in_array($job->status, ['review', 'rejected', 'pending'], true), 422, 'Only a job waiting for review or rejected can be approved.');

        $edits = $this->edits($request);
        $before = $job->only([...array_keys($edits), 'status']);

        $job->fill($edits)->save();
        $publisher->publish($job, $request->user()->id);

        AuditLog::record($request->user()->id, 'jobs.approved', 'job', $job->id, $before, $edits + ['status' => 'published']);

        return response()->json(['job' => $this->summary($job->fresh('source'))]);
    }

    /** Correct a published job; the channel card is re-rendered. */
    public function update(Request $request, Job $job, JobPublisher $publisher): JsonResponse
    {
        $edits = $this->edits($request);
        $before = $job->only(array_keys($edits));

        $job->fill($edits)->save();
        if ($job->status === 'published') {
            $publisher->refresh($job);
        }

        AuditLog::record($request->user()->id, 'jobs.edited', 'job', $job->id, $before, $edits);

        return response()->json(['job' => $this->summary($job->fresh('source'))]);
    }

    public function reject(Request $request, Job $job, JobPublisher $publisher): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:250'],
            'block_company' => ['boolean'],
        ]);

        $publisher->reject($job, $request->user()->id, $data['reason'] ?? null, (bool) ($data['block_company'] ?? false));
        AuditLog::record($request->user()->id, 'jobs.rejected', 'job', $job->id, null, $data);

        return response()->json(['job' => $this->summary($job->fresh('source'))]);
    }

    public function close(Request $request, Job $job, JobPublisher $publisher): JsonResponse
    {
        abort_unless($job->status === 'published', 422, 'Only a published job can be closed.');

        $publisher->close($job);
        AuditLog::record($request->user()->id, 'jobs.closed', 'job', $job->id);

        return response()->json(['job' => $this->summary($job->fresh('source'))]);
    }

    private function edits(Request $request): array
    {
        return $request->validate([
            'title' => ['sometimes', 'string', 'min:2', 'max:300'],
            'company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'category' => ['sometimes', Rule::in(Classification::CATEGORIES)],
            'emirate' => ['sometimes', Rule::in(Classification::EMIRATES)],
            'sub_area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999'],
            'accommodation' => ['sometimes', Rule::in(Classification::TRISTATE)],
            'visa' => ['sometimes', Rule::in(Classification::TRISTATE)],
            'summary_am' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'summary_ti' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'summary_om' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'summary_en' => ['sometimes', 'nullable', 'string', 'max:1200'],
        ]);
    }

    private function summary(Job $j): array
    {
        return [
            'id' => $j->id,
            'title' => $j->title,
            'company' => $j->company,
            'category' => $j->category,
            'emirate' => $j->emirate,
            'salary' => $j->salaryText(),
            'status' => $j->status,
            'review_reason' => $j->review_reason,
            'scam_score' => $j->scam_score,
            'reports_count' => $j->reports_count,
            'url' => $j->url,
            'source' => $j->source?->name,
            'source_id' => $j->source_id,
            'on_channel' => (bool) $j->channel_message_id,
            // The flyer is how a moderator recognises a job with no words. Only people can see it once it is up.
            'photo' => in_array($j->status, ['published', 'closed'], true) ? $j->photos->first()?->url(480) : null,
            'created_at' => $j->created_at?->toIso8601String(),
            'published_at' => $j->published_at?->toIso8601String(),
        ];
    }
}
