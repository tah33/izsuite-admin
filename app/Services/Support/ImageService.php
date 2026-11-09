<?php

namespace App\Services\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function storePublic(UploadedFile $file, string $directory = 'uploads', ?string $oldPath = null): string
    {
        $this->deletePublic($oldPath);

        return $file->store($directory, 'public');
    }

    public function storePublicWithUrl(UploadedFile $file, string $directory = 'uploads', ?string $oldPath = null): array
    {
        $path = $this->storePublic($file, $directory, $oldPath);

        return [
            'path' => $path,
            'url'  => $this->publicUrl($path),
        ];
    }

    public function deletePublic(?string $path): void
    {
        $normalizedPath = $this->normalizePublicPath($path);

        if (! $normalizedPath) {
            return;
        }

        if (Storage::disk('public')->exists($normalizedPath)) {
            Storage::disk('public')->delete($normalizedPath);
        }
    }

    /** A public image as a data URI - for a PDF, which cannot fetch it - or null when it is not there. */
    public function dataUri(?string $path): ?string
    {
        $normalizedPath = $this->normalizePublicPath($path);
        $disk           = Storage::disk('public');

        if (! $normalizedPath || ! $disk->exists($normalizedPath)) {
            return null;
        }

        return 'data:'.($disk->mimeType($normalizedPath) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($normalizedPath));
    }

    public function publicUrl(?string $path): ?string
    {
        $normalizedPath = $this->normalizePublicPath($path);

        if (! $normalizedPath) {
            return null;
        }

        return Storage::url($normalizedPath);
    }

    public function normalizePublicPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^https?://[^/]+#', '', $path) ?? $path;
        $path = Str::startsWith($path, '/storage/') ? Str::after($path, '/storage/') : ltrim($path, '/');

        return $path !== '' ? $path : null;
    }
}
