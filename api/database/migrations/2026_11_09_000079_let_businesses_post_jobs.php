<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business with an approved listing can post its own open positions. The
 * raw row carries what the owner stated until the pipeline has run; the job
 * carries the business and the way to apply, which is never in `url` because
 * the deduper treats a repeated URL as the same job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_raw', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->json('posted_by')->nullable();
        });

        Schema::table('jobs_listings', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('apply_contact', 200)->nullable();
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('jobs_listings', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'status']);
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn('apply_contact');
        });

        Schema::table('job_raw', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn('posted_by');
        });
    }
};
