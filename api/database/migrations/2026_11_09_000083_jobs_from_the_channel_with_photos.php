<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job a channel admin posts on our own channel reaches the website by itself, often as
 * just a flyer. `website_only` means no channel card (it is already on the channel, or has
 * nothing to say there); `image_only` means nobody has read the flyer, so the picture is the
 * listing. Photos live in the database because the container is replaced on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_raw', function (Blueprint $table) {
            $table->json('photo_file_ids')->nullable();
            $table->string('photo_url', 1000)->nullable();
            $table->string('media_group', 64)->nullable()->index();
            $table->boolean('website_only')->default(false);
        });

        Schema::table('jobs_listings', function (Blueprint $table) {
            $table->boolean('website_only')->default(false);
            $table->boolean('image_only')->default(false);
        });

        Schema::create('job_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_listings')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('mime', 32)->default('image/jpeg');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->unsignedInteger('size')->default(0);
            $table->longText('data');
            $table->timestamps();
            $table->index(['job_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_photos');

        Schema::table('jobs_listings', function (Blueprint $table) {
            $table->dropColumn(['website_only', 'image_only']);
        });

        Schema::table('job_raw', function (Blueprint $table) {
            $table->dropIndex(['media_group']);
            $table->dropColumn(['photo_file_ids', 'photo_url', 'media_group', 'website_only']);
        });
    }
};
