<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Website visits for the dashboard: views per day, and the most-read pages. */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:90']]);
        $days = (int) ($data['days'] ?? 30);

        $from = now('Asia/Dubai')->subDays($days - 1)->toDateString();
        $perDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $perDay[now('Asia/Dubai')->subDays($i)->toDateString()] = 0;
        }

        if (! Schema::hasTable('page_views')) {
            return response()->json(['total' => 0, 'days' => $this->days($perDay), 'top' => []]);
        }

        $counted = DB::table('page_views')->where('day', '>=', $from)
            ->selectRaw('day, sum(views) as views')->groupBy('day')->pluck('views', 'day');
        foreach ($counted as $day => $views) {
            $key = substr((string) $day, 0, 10);
            if (array_key_exists($key, $perDay)) {
                $perDay[$key] = (int) $views;
            }
        }

        $top = DB::table('page_views')->where('day', '>=', $from)
            ->selectRaw('path, sum(views) as views')->groupBy('path')->orderByDesc('views')->limit(10)->get()
            ->map(fn ($row) => ['path' => $row->path, 'views' => (int) $row->views])->values();

        return response()->json(['total' => array_sum($perDay), 'days' => $this->days($perDay), 'top' => $top]);
    }

    /** @param array<string, int> $perDay */
    private function days(array $perDay): array
    {
        return array_map(fn ($day, $views) => ['day' => $day, 'views' => $views], array_keys($perDay), array_values($perDay));
    }
}
