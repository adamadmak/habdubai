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

    /** Decoding needs about 4 bytes a pixel: a small file can claim a huge picture, so the size is read first. */
    public const MAX_PIXELS = 40_000_000;

    public const MAX_DECODE_SIDE = 12_000;

    public function store(Business $business, string $raw, string $kind = 'cover'): BusinessPhoto
    {
        $size = $raw !== '' ? @getimagesizefromstring($raw) : false;
        if ($size === false || ! in_array($size[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['photo' => 'That file is not a photo we can read. Use a JPEG, PNG or WebP image.']);
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS || max($size[0], $size[1]) > self::MAX_DECODE_SIDE) {
            throw ValidationException::withMessages(['photo' => 'The photo is too large. Use one under 40 megapixels.']);
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'That file is not a photo we can read. Use a JPEG, PNG or WebP image.']);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < self::MIN_WIDTH || $height < 200) {
            imagedestroy($image);

            throw ValidationException::withMessages(['photo' => 'The photo is too small. Use one at least '.self::MIN_WIDTH.' pixels wide.']);
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

        // One photo waits for review at a time: a new upload replaces the last one.
        $business->photos()->where('kind', $kind)->where('approved', false)->delete();

        return BusinessPhoto::query()->create([
            'business_id' => $business->id,
            'kind' => $kind,
            'approved' => false,
            'mime' => 'image/jpeg',
            'width' => $newWidth,
            'height' => $newHeight,
            'size' => strlen($jpeg),
            'data' => base64_encode($jpeg),
        ]);
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
