<?php

use App\Services\TelegramBotService;
use App\Support\JobSettings;
use App\Support\SecretBox;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Secrets saved under APP_KEY move to a key that survives a deploy (StableKey).
 * One that can no longer be read stays as it is: the Launch page says which
 * ones to save again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bare = [TelegramBotService::STORED_TOKEN_KEY, JobSettings::ANTHROPIC_KEY];

        foreach (DB::table('platform_settings')->get(['key', 'value']) as $row) {
            $value = (string) $row->value;
            $prefix = str_starts_with($value, 'enc:') ? 'enc:' : '';

            if ($value === '' || ($prefix === '' && ! in_array($row->key, $bare, true))) {
                continue;
            }

            $sealed = substr($value, strlen($prefix));
            $plain = SecretBox::isCurrent($sealed) ? null : SecretBox::open($sealed);

            if ($plain !== null) {
                DB::table('platform_settings')->where('key', $row->key)->update(['value' => $prefix.SecretBox::seal($plain)]);
            }
        }
    }

    public function down(): void
    {
        // Sealed values stay readable; there is nothing to undo.
    }
};
