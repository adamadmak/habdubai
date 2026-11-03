<?php

use App\Models\TelegramUpdate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How often a handler was started for the update, so one that died
        // mid-way (a deploy, a crash) is run again, a few times at most.
        if (! Schema::hasColumn('telegram_updates', 'attempts')) {
            Schema::table('telegram_updates', function (Blueprint $table) {
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->index(['processed_at', 'received_at']);
            });
        }

        // Updates already handled keep only their id and kind; their contact
        // cards held members' phone numbers.
        DB::table('telegram_updates')->whereNotNull('processed_at')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (is_array($payload) && empty($payload['redacted'])) {
                    DB::table('telegram_updates')->where('id', $row->id)->update(['payload' => json_encode(TelegramUpdate::redact($payload))]);
                }
            }
        });

        DB::table('telegram_updates')->where('received_at', '<', now()->subDays(TelegramUpdate::KEEP_DAYS))->delete();
    }

    public function down(): void
    {
        Schema::table('telegram_updates', function (Blueprint $table) {
            $table->dropIndex(['processed_at', 'received_at']);
            $table->dropColumn('attempts');
        });
    }
};
