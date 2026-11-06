<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Support\PublicUrls;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Published jobs for the website. A job is a title and a few facts; the
 * full listing lives where it was posted, and "Apply" goes there through
 * apply(), which counts the click.
 */
class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:24'],
            'emirate' => ['nullable', Rule::in(Job::EMIRATES)],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $page = Job::query()->with(['source:id,name', 'photos'])->where('status', 'published')
            ->when($data['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($data['emirate'] ?? null, fn ($q, $emirate) => $q->where('emirate', $emirate))
            ->when(trim((string) ($data['q'] ?? '')), fn ($q, $term) => Search::anyColumn($q, ['title', 'company', 'sub_area'], $term))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Job $job) => self::payload($job))->values(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => [
                'categories' => Job::query()->where('status', 'published')->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
                'emirates' => Job::query()->where('status', 'published')->selectRaw('emirate, count(*) as n')->groupBy('emirate')->pluck('n', 'emirate'),
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }

    public function show(Job $job): JsonResponse
    {
        abort_unless(in_array($job->status, ['published', 'closed'], true), 404);
        $job->loadMissing(['source:id,name', 'business', 'photos']);

        $similar = Job::query()->with(['source:id,name', 'photos'])->where('status', 'published')->whereKeyNot($job->id)
            ->where('category', $job->category)->orderByDesc('published_at')->limit(4)->get();

        return response()->json([
            'job' => self::payload($job, full: true),
            'similar' => $similar->map(fn (Job $j) => self::payload($j))->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /** Count the click and send the person to the original listing. */
    public function apply(Job $job): RedirectResponse|JsonResponse
    {
        $url = (string) $job->applyLink();

        if ($job->status !== 'published' || ! preg_match('#^https?://#i', $url)) {
            $site = PublicUrls::site();

            return $site !== '' ? redirect()->away($site) : response()->json(['message' => 'This job is no longer available.'], 410);
        }

        Job::query()->whereKey($job->id)->toBase()->increment('clicks');

        // One tap to a chat that already says why they are writing.
        if (str_starts_with($url, 'https://wa.me/')) {
            $url .= '?text='.rawurlencode('Hello, I saw your job on HabUAE: '.$job->title);
        }

        return redirect()->away($url);
    }

    public static function payload(Job $job, bool $full = false): array
    {
        $host = $job->url ? preg_replace('/^www\./', '', (string) parse_url($job->url, PHP_URL_HOST)) : null;

        $payload = [
            'id' => $job->id,
            'title' => $job->title,
            'company' => $job->company,
            'category' => $job->category,
            'emirate' => $job->emirate,
            'area' => $job->sub_area,
            'salary' => $job->salaryText(),
            'accommodation' => $job->accommodation === 'yes',
            'visa' => $job->visa === 'yes',
            // Posted by the employer: nothing to credit, and applicants can say so.
            'source' => $job->business_id ? null : ($host && $host !== 't.me' ? $host : $job->source?->name),
            // A flyer nobody has read: the picture is the listing.
            'image_only' => (bool) $job->image_only,
            'photo' => $job->photos->first()?->url(480),
            'direct' => $job->business_id !== null,
            'open' => $job->status === 'published',
            'published_at' => $job->published_at?->toIso8601String(),
            'summary' => ['am' => $job->summary('am'), 'ti' => $job->summary('ti'), 'en' => $job->summary('en'), 'om' => $job->summary('om')],
        ];

        if ($full) {
            $payload['apply_url'] = $job->applyLink() ? PublicUrls::api('api/public/jobs/'.$job->id.'/apply') : null;
            $payload['apply_via'] = match (true) {
                str_starts_with((string) $job->apply_contact, 'https://wa.me/') => 'whatsapp',
                str_starts_with((string) $job->apply_contact, 'https://t.me/') => 'telegram',
                default => null,
            };
            $payload['business'] = $job->business?->status === 'approved'
                ? ['name' => $job->business->name, 'slug' => $job->business->slug, 'verified' => (bool) $job->business->verified]
                : null;
            $payload['photos'] = $job->photos->map(fn (JobPhoto $p) => ['url' => $p->url(), 'preview' => $p->url(960), 'width' => $p->width, 'height' => $p->height])->values()->all();
            $payload['languages'] = $job->languages ?? [];
            $payload['warning'] = $job->scam_score > 0 && ! empty($job->scam_reasons) ? array_values((array) $job->scam_reasons) : [];
        }

        return $payload;
    }
}
