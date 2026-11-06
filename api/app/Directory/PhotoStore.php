<?php

namespace App\Directory;

use App\Models\Business;
use App\Models\BusinessPhoto;
use Illuminate\Validation\ValidationException;

/**
 * Listing photos: decoded and re-encoded as JPEG (which drops location data
 * and anything else hidden in the file), at most 1600 pixels on the long
 * side, and kept in review until a moderator approves the listing or its
 * changes.
 */
class PhotoStore
{
    public const MAX_UPLOAD_KB = 8192;

    public const MAX_SIDE = 1600;

    public const MIN_WIDTH = 400;

    public const QUALITY = 82;

    /**
     * Decoding needs about 4 bytes a pixel, more with a copy: a small file can claim a huge
     * picture, so the size is read first. 24 MP stays well inside a 256 MB worker.
     */
    public const MAX_PIXELS = 24_000_000;

    public const MAX_DECODE_SIDE = 12_000;

    public function store(Business $business, string $raw, string $kind = 'cover'): BusinessPhoto
    {
        $image = $this->encode($raw);

        // One photo waits for review at a time: a new upload replaces the last one.
        $business->photos()->where('kind', $kind)->where('approved', false)->delete();

        return BusinessPhoto::query()->create([
            'business_id' => $business->id,
            'kind' => $kind,
            'approved' => false,
            'mime' => 'image/jpeg',
            'width' => $image['width'],
            'height' => $image['height'],
            'size' => strlen($image['jpeg']),
            'data' => base64_encode($image['jpeg']),
        ]);
    }

    /**
     * Any picture as a clean JPEG of a sensible size.
     *
     * @return array{jpeg: string, width: int, height: int}
     */
    public function encode(string $raw, int $minWidth = self::MIN_WIDTH): array
    {
        $size = $raw !== '' ? @getimagesizefromstring($raw) : false;
        if ($size === false || ! in_array($size[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['photo' => __('owner.photo_unreadable')]);
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS || max($size[0], $size[1]) > self::MAX_DECODE_SIDE) {
            throw ValidationException::withMessages(['photo' => __('owner.photo_too_large', ['mp' => intdiv(self::MAX_PIXELS, 1_000_000)])]);
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => __('owner.photo_unreadable')]);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < $minWidth || $height < 200) {
            imagedestroy($image);

            throw ValidationException::withMessages(['photo' => __('owner.photo_too_small', ['width' => $minWidth])]);
        }

        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // A transparent PNG gets a white background rather than black.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return ['jpeg' => $jpeg, 'width' => $newWidth, 'height' => $newHeight];
    }

    /** Widths the website may ask for (`?w=`): a card needs far less than the stored 1600. */
    public const THUMB_WIDTHS = [480, 960];

    /** A stored JPEG made narrower for a card; the original when it is already that small. */
    public static function thumbnail(string $jpeg, int $width): string
    {
        $image = @imagecreatefromstring($jpeg);
        if ($image === false || imagesx($image) <= $width) {
            return $jpeg;
        }

        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    /** On approval the newest photo in review becomes the cover and older ones go. */
    public function approvePending(Business $business): bool
    {
        $pending = $business->photos()->where('approved', false)->latest('id')->first();

        if ($pending === null) {
            return false;
        }

        $business->photos()->where('kind', $pending->kind)->whereKeyNot($pending->id)->delete();
        $pending->forceFill(['approved' => true])->save();

        return true;
    }

    public function discardPending(Business $business): void
    {
        $business->photos()->where('approved', false)->delete();
    }
}
