<?php

namespace App\Http\Controllers\Public;

use App\Directory\PhotoStore;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\BusinessPhoto;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** Photos for the website: listing photos from the database, announcement photos via Telegram. */
class MediaController extends Controller
{
    /**
     * An approved photo of a listed business. The URL carries a version, so it can be cached
     * for good. `?w=480` or `?w=960` gives a narrower copy for cards, made once and kept.
     */
    public function photo(Request $request, BusinessPhoto $photo): Response
    {
        $photo->loadMissing('business');
        abort_unless($photo->approved && $photo->business?->status === 'approved', 404);

        $bytes = $photo->bytes();
        $width = (int) $request->query('w', 0);

        if (in_array($width, PhotoStore::THUMB_WIDTHS, true)) {
            $key = 'public.photo-thumb.'.$photo->id.'.'.($photo->updated_at?->timestamp ?? 0).'.'.$width;
            $thumb = Cache::remember($key, now()->addDays(30), fn () => base64_encode(PhotoStore::thumbnail($bytes, $width)));
            $bytes = base64_decode($thumb, true) ?: $bytes;
        }

        return $this->image($bytes, $photo->mime, 'public, max-age=31536000, immutable');
    }

    /** A photo still in review: only through a signed link, for the owner and the moderators. */
    public function preview(BusinessPhoto $photo): Response
    {
        return $this->image($photo->bytes(), $photo->mime, 'private, max-age=3600');
    }

    public function announcement(Announcement $announcement, TelegramBotService $telegram): Response
    {
        abort_unless($announcement->status === 'published' && $announcement->photo_file_id, 404);

        $key = 'public.announcement-photo.'.$announcement->id;
        $encoded = Cache::get($key);
        if ($encoded === null) {
            // One fetch from Telegram at a time for a photo, and a photo Telegram no longer has
            // is remembered as gone for a while, so a busy page cannot tie up the workers on it.
            try {
                $encoded = Cache::lock($key.'.fetch', 30)->block(8, function () use ($key, $announcement, $telegram): string {
                    $fetched = Cache::get($key);
                    if ($fetched !== null) {
                        return $fetched;
                    }

                    $bytes = $telegram->downloadFile($announcement->photo_file_id);
                    $fetched = $bytes === null ? '' : base64_encode($bytes);
                    Cache::put($key, $fetched, $bytes === null ? now()->addMinutes(10) : now()->addDays(7));

                    return $fetched;
                });
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
                abort(503);
            }
        }
        abort_if($encoded === '', 404);

        $bytes = (string) base64_decode($encoded, true);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
        abort_unless(str_starts_with($mime, 'image/'), 404);

        return $this->image($bytes, $mime, 'public, max-age=86400');
    }

    private function image(string $bytes, string $mime, string $cache): Response
    {
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
