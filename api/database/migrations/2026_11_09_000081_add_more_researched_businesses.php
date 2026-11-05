<?php

use App\Models\Business;
use App\Models\DirectoryCity;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner's second request for businesses across more categories. As in
 * 000080: written directly so nothing posts to the channel, unverified with a
 * note, and a name already present is left alone. Not in the test suite's database.
 */
return new class extends Migration
{
    private const UNCONFIRMED = 'Added by HabUAE from public listings. The business has not confirmed these details, so please call before you go. Is this your business? Claim this listing to keep it right.';

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $cities = DirectoryCity::query()->pluck('id', 'key');

        foreach (require database_path('data/businesses_2026_11_b.php') as $b) {
            $city = $cities[$b['city']] ?? null;

            if ($city === null || Business::query()->where('city_id', $city)->whereRaw('lower(name) = ?', [mb_strtolower($b['name'])])->exists()) {
                continue;
            }

            Business::query()->create([
                'name' => $b['name'],
                'category' => $b['category'],
                'city_id' => $city,
                'area' => $b['area'],
                'phone' => $b['phone'],
                'whatsapp' => $b['whatsapp'],
                'website' => $b['website'],
                'description' => $b['description'],
                'about' => self::UNCONFIRMED,
                'status' => 'approved',
                'verified' => false,
                'reviewed_at' => now(),
            ]);
        }
    }

    /** Only what this added and nobody has since taken over or changed. */
    public function down(): void
    {
        $names = array_column(require database_path('data/businesses_2026_11_b.php'), 'name');
        Business::query()->whereIn('name', $names)->whereNull('owner_id')->where('about', self::UNCONFIRMED)->delete();
    }
};
