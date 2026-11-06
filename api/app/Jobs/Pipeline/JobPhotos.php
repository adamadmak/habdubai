<?php

namespace App\Jobs\Pipeline;

use App\Directory\PhotoStore;
use App\Jobs\Sources\HttpPage;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\JobRaw;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;

/**
 * Saves a job's pictures: photos Telegram holds, or the preview image of a posted link.
 * A picture that cannot be saved is logged and skipped, and never stops the job.
 */
class JobPhotos
{
    /** Screenshots of flyers can be narrower than a listing's cover photo is allowed to be. */
    private const MIN_WIDTH = 200;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly PhotoStore $store,
    ) {}

    /** @return int how many pictures the job now has */
    public function attach(Job $job, JobRaw $raw): int
    {
        foreach ($raw->photo_file_ids ?? [] as $fileId) {
            $this->save($job, $this->telegram->downloadFile((string) $fileId));
        }

        if ($raw->photo_url) {
            try {
                $this->save($job, HttpPage::get($raw->photo_url));
            } catch (\Throwable $e) {
                Log::info('A link preview image could not be fetched.', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            }
        }

        return JobPhoto::query()->where('job_id', $job->id)->count();
    }

    /** The next photo of an album whose first photo carried the words. */
    public function addToAlbum(JobRaw $raw, string $fileId): void
    {
        $job = Job::query()->where('raw_id', $raw->id)->first();

        if ($job !== null) {
            $this->save($job, $this->telegram->downloadFile($fileId));

            return;
        }

        $raw->forceFill(['photo_file_ids' => [...($raw->photo_file_ids ?? []), $fileId]])->save();
    }

    /**
     * Flyers usually arrive as phone screenshots: the status bar, a black band, the flyer, a
     * black band, the buttons. Keeps the tallest stretch between black bands, so a card shows the
     * flyer. A picture with no such bands comes back as it was.
     *
     * @param  array{jpeg: string, width: int, height: int}  $image
     * @return array{jpeg: string, width: int, height: int}
     */
    public static function withoutPhoneBars(array $image): array
    {
        $gd = @imagecreatefromstring($image['jpeg']);

        if ($gd === false) {
            return $image;
        }

        [$width, $height] = [imagesx($gd), imagesy($gd)];
        $step = max(1, intdiv($width, 40));
        [$best, $bestLength, $start] = [0, 0, null];

        for ($y = 0; $y <= $height; $y++) {
            $black = $y === $height;

            if (! $black) {
                $samples = $dark = 0;

                for ($x = 0; $x < $width; $x += $step) {
                    $rgb = imagecolorat($gd, $x, $y);
                    $samples++;
                    $dark += (($rgb >> 16 & 255) + ($rgb >> 8 & 255) + ($rgb & 255)) < 75 ? 1 : 0;
                }

                $black = $dark >= $samples * 0.97;
            }

            if ($black && $start !== null) {
                if ($y - $start > $bestLength) {
                    [$best, $bestLength] = [$start, $y - $start];
                }
                $start = null;
            } elseif (! $black && $start === null) {
                $start = $y;
            }
        }

        // Only a real band: not a dark line inside a flyer, and never most of the picture.
        if ($bestLength < $height * 0.25 || $bestLength > $height * 0.95) {
            imagedestroy($gd);

            return $image;
        }

        $crop = imagecrop($gd, ['x' => 0, 'y' => $best, 'width' => $width, 'height' => $bestLength]);
        imagedestroy($gd);

        if ($crop === false) {
            return $image;
        }

        ob_start();
        imagejpeg($crop, null, PhotoStore::QUALITY);
        imagedestroy($crop);

        return ['jpeg' => (string) ob_get_clean(), 'width' => $width, 'height' => $bestLength];
    }

    private function save(Job $job, ?string $bytes): void
    {
        if ($bytes === null || $bytes === '') {
            return;
        }

        try {
            $image = $this->store->encode($bytes, self::MIN_WIDTH);
        } catch (\Throwable $e) {
            Log::info('A job photo could not be read.', ['job_id' => $job->id, 'error' => $e->getMessage()]);

            return;
        }

        $image = self::withoutPhoneBars($image);

        JobPhoto::query()->create([
            'job_id' => $job->id,
            'position' => JobPhoto::query()->where('job_id', $job->id)->count(),
            'mime' => 'image/jpeg',
            'width' => $image['width'],
            'height' => $image['height'],
            'size' => strlen($image['jpeg']),
            'data' => base64_encode($image['jpeg']),
        ]);
    }
}
