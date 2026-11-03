<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cities are data, not code: the directory starts with the UAE and an
        // admin adds Riyadh or Doha the day the community gets there.
        Schema::create('directory_cities', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_en', 80);
            $table->string('name_am', 80);
            $table->string('name_ti', 80);
            $table->string('country', 2)->default('AE');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('directory_cities')->restrictOnDelete();
            $table->string('category', 24);
            $table->string('name', 120);
            // The location: the area or street people know, and a pin when we have one.
            $table->string('area', 160);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('telegram', 40)->nullable();
            $table->string('description', 300)->nullable();
            // pending → approved | rejected; approved → hidden (reports) → approved
            $table->string('status', 12)->default('pending')->index();
            $table->boolean('verified')->default(false);
            $table->foreignId('submitted_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 250)->nullable();
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();

            $table->index(['city_id', 'status', 'category']);
            $table->index('name');
        });

        Schema::create('business_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 24)->default('wrong');
            $table->timestamp('created_at');

            $table->unique(['member_id', 'business_id']);
        });

        $now = now();
        DB::table('directory_cities')->insert(array_map(fn (array $c) => $c + ['country' => 'AE', 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['key' => 'dubai', 'name_en' => 'Dubai', 'name_am' => 'ዱባይ', 'name_ti' => 'ዱባይ', 'sort' => 10],
            ['key' => 'sharjah', 'name_en' => 'Sharjah', 'name_am' => 'ሻርጃ', 'name_ti' => 'ሻርጃ', 'sort' => 20],
            ['key' => 'abu-dhabi', 'name_en' => 'Abu Dhabi', 'name_am' => 'አቡ ዳቢ', 'name_ti' => 'ኣቡ ዳቢ', 'sort' => 30],
            ['key' => 'ajman', 'name_en' => 'Ajman', 'name_am' => 'አጅማን', 'name_ti' => 'ዓጅማን', 'sort' => 40],
            ['key' => 'al-ain', 'name_en' => 'Al Ain', 'name_am' => 'አል ዓይን', 'name_ti' => 'ኣል ዓይን', 'sort' => 50],
            ['key' => 'ras-al-khaimah', 'name_en' => 'Ras Al Khaimah', 'name_am' => 'ራስ አል ኻይማ', 'name_ti' => 'ራስ ኣል ኻይማ', 'sort' => 60],
            ['key' => 'fujairah', 'name_en' => 'Fujairah', 'name_am' => 'ፉጃይራ', 'name_ti' => 'ፉጃይራ', 'sort' => 70],
            ['key' => 'umm-al-quwain', 'name_en' => 'Umm Al Quwain', 'name_am' => 'ኡም አል ቁዌን', 'name_ti' => 'ኡም ኣል ቁዌን', 'sort' => 80],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reports');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('directory_cities');
    }
};
