<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One page seen on the website, sent by the website's server. Counted per
 * day (Dubai) and path; who saw it is never stored.
 */
class PageViewController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:200', 'regex:#^/(am|ti|en|om)(/[A-Za-z0-9_\-/]*)?$#'],
        ]);

        // This host does not migrate before the code goes live; a missing table counts nothing.
        if (! Schema::hasTable('page_views')) {
            return response()->noContent();
        }

        $path = rtrim($data['path'], '/') ?: '/';

        DB::table('page_views')->upsert(
            [['day' => now('Asia/Dubai')->toDateString(), 'path' => $path, 'views' => 1]],
            ['day', 'path'],
            ['views' => DB::raw('page_views.views + 1')],
        );

        return response()->noContent();
    }
}
