<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small WebP copy of an item's image, for lists: the original can be a
 * couple of megabytes, and a page of fifty would be a hundred. Its longest
 * side is 240 pixels; one smaller is kept at its size. Transparency survives.
 */
class Thumbnails
{
    private const LONGEST_SIDE = 240;

    /** The stored thumbnail's path beside the original, or null when the image cannot be read. */
    public function make(UploadedFile $image, string $disk, string $directory, bool $public): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($image->getRealPath()));

        if ($source === false) {
            return null;
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, self::LONGEST_SIDE / max($width, $height));

        $thumbnail = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, imagesx($thumbnail), imagesy($thumbnail), $width, $height);

        ob_start();
        imagewebp($thumbnail, null, 80);
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        $path = "{$directory}/thumbnails/".Str::random(40).'.webp';

        return Storage::disk($disk)->put($path, $bytes, ['visibility' => $public ? 'public' : 'private']) ? $path : null;
    }
}
