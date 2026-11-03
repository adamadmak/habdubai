<?php

use Illuminate\Support\Facades\Route;

// The API has no pages of its own; the admin and the Mini App live in web/.
Route::get('/', fn () => response()->json(['name' => config('app.name'), 'ok' => true]));
