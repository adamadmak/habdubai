<?php

namespace App\Http\Controllers\Admin;

use App\Bot\BotMessages;
use App\Bot\Choices;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BotText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BotTextController extends Controller
{
    public function index(): JsonResponse
    {
        $defaults = BotMessages::defaults();
        $overrides = BotText::query()->get()->groupBy('key');

        $rows = [];
        foreach ($defaults as $key => $languages) {
            $rows[] = [
                'key' => $key,
                'is_button' => str_starts_with($key, 'btn_'),
                'default' => $languages,
                'override' => $overrides->get($key)?->pluck('text', 'language')->all() ?? [],
                'placeholders' => array_values(array_unique(array_merge(...array_map(
                    fn (string $t) => preg_match_all('/\{([a-z_]+)\}/', $t, $m) ? $m[1] : [],
                    array_values($languages)
                )))),
            ];
        }

        return response()->json(['data' => $rows, 'languages' => Choices::LANGUAGES]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, BotMessages::defaults()), 404);

        $data = $request->validate([
            'language' => ['required', Rule::in(Choices::LANGUAGES)],
            'text' => ['nullable', 'string', 'max:4000'],
        ]);

        $before = BotText::query()->where('key', $key)->where('language', $data['language'])->value('text');

        if (trim((string) $data['text']) === '' || trim((string) $data['text']) === trim(BotMessages::defaults()[$key][$data['language']] ?? '')) {
            BotText::query()->where('key', $key)->where('language', $data['language'])->delete();
            $after = null;
        } else {
            BotText::query()->updateOrCreate(['key' => $key, 'language' => $data['language']], ['text' => $data['text']]);
            $after = $data['text'];
        }

        BotMessages::flush();
        AuditLog::record($request->user()->id, 'bot_text.updated', 'bot_text', null, ['key' => $key, 'language' => $data['language'], 'text' => $before], ['text' => $after]);

        return response()->json(['ok' => true, 'override' => $after]);
    }
}
