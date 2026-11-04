<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A goods post now ends on the record: closed, and how (delivered, failed, unconfirmed,
 * cancelled, or by its member). And members can give a business stars, which is what
 * the directory is ordered by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_posts', function (Blueprint $table) {
            $table->string('outcome', 12)->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            // Kept up to date by BusinessRating::recount, the only writer.
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
        });

        // One member's stars for one business. They may change them; there is no text to moderate.
        Schema::create('business_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            $table->unique(['business_id', 'member_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_ratings');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });

        Schema::table('goods_posts', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'closed_at']);
        });
    }
};
