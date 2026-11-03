<?php

namespace App\Http\Controllers\Owner;

use App\Directory\OwnerLogin;
use App\Http\Controllers\Controller;
use App\Support\ClientIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Continue with Telegram" for business owners. See App\Directory\OwnerLogin. */
class AuthController extends Controller
{
    public function start(Request $request, OwnerLogin $login): JsonResponse
    {
        $started = $login->start(ClientIp::of($request), ClientIp::agent($request));

        if ($started['link'] === null) {
            return response()->json(['message' => 'Sign-in is not available yet: the bot is not set up.'], 503);
        }

        return response()->json($started);
    }

    public function poll(Request $request, OwnerLogin $login): JsonResponse
    {
        $data = $request->validate([
            'ref' => ['required', 'string', 'max:32'],
            'secret' => ['required', 'string', 'max:64'],
        ]);

        return response()->json($login->poll($data['ref'], $data['secret']));
    }

    public function verify(Request $request, OwnerLogin $login): JsonResponse
    {
        $data = $request->validate([
            'ref' => ['required', 'string', 'max:32'],
            'secret' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        return response()->json($login->verify($data['ref'], $data['secret'], $data['code']));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['owner' => $request->user()->payload()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
