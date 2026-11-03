<?php

namespace App\Http\Middleware;

use App\Models\BusinessOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Owner routes: a business owner's token, and an owner who has not been banned. */
class EnsureBusinessOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $owner = $request->user();

        if (! $owner instanceof BusinessOwner) {
            return response()->json(['message' => 'Sign in with Telegram first.'], 401);
        }

        if ($owner->isBanned()) {
            $owner->currentAccessToken()?->delete();

            return response()->json(['message' => 'This account cannot manage listings.'], 403);
        }

        return $next($request);
    }
}
