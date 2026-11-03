<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LaunchReadiness;
use Illuminate\Http\JsonResponse;

/** The launch checklist for the admin's Launch page: a score, ready or not, and every item with its fix. */
class ReadinessController extends Controller
{
    public function __invoke(LaunchReadiness $readiness): JsonResponse
    {
        return response()->json($readiness->report())->header('Cache-Control', 'no-store');
    }
}
