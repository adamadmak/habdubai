<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Afaan Oromoo, the fourth language. Every place that keeps a text per
 * language gains an `om` column, empty until written or translated; readers
 * fall back to English.
 */
return new class extends Migration
{
    /** @var array<string, array<int, array{0: string, 1: string}>> table → [column, type] */
    private const COLUMNS = [
        'jobs_listings' => [['summary_om', 'text']],
        'faq_entries' => [['q_om', 'text'], ['a_om', 'text']],
        'announcements' => [['text_om', 'text']],
        'announcement_topics' => [['name_om', 'string']],
        'directory_cities' => [['name_om', 'string']],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($table, $columns): void {
                foreach ($columns as [$column, $type]) {
                    if (! Schema::hasColumn($table, $column)) {
                        $type === 'text' ? $t->text($column)->nullable() : $t->string($column, 80)->nullable();
                    }
                }
            });
        }

        $topics = [
            'consulate' => 'Qonsilaa fi embaasii',
            'safety' => 'Akeekkachiisa nageenyaa',
            'platform' => 'Oduu hawaasaa',
            'church' => 'Mana kiristaanaa',
            'mosque' => 'Masjiida',
            'events' => 'Sagantaalee hawaasaa',
        ];
        foreach ($topics as $key => $name) {
            DB::table('announcement_topics')->where('key', $key)->whereNull('name_om')->update(['name_om' => $name]);
        }

        // UAE cities keep their usual Latin names in Afaan Oromoo.
        DB::table('directory_cities')->whereNull('name_om')->update(['name_om' => DB::raw('name_en')]);
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns): void {
                $t->dropColumn(array_column($columns, 0));
            });
        }
    }
};
