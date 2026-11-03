<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_posts', function (Blueprint $table) {
            // A parcel is either sent by its owner ("send") or handed to the traveller at the
            // other end by family or a shop, for the member who asked ("request"). Trips: null.
            if (! Schema::hasColumn('goods_posts', 'mode')) {
                $table->string('mode', 8)->nullable()->after('kind');
            }
            // The carry fee members agree and pay each other directly: a traveller's price per
            // kilo (0 = free), or a sender's offer for the whole parcel. `price` already exists.
            if (! Schema::hasColumn('goods_posts', 'currency')) {
                $table->string('currency', 3)->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_posts', function (Blueprint $table) {
            $table->dropColumn(['mode', 'currency']);
        });
    }
};
