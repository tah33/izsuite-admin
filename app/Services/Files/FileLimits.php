<?php

namespace App\Services\Files;

use App\Enums\FileCollection;
use App\Repositories\Admin\SettingRepository;

/**
 * The file types and sizes a business may upload (Doc 14 section 14.12). An
 * attachment's are set in the admin (Admin > Settings > Files). They can only
 * narrow what izSuite itself takes (FileCollection::extensions) and go up to
 * 50 MB a file. Every other collection keeps its own, set in code.
 */
class FileLimits
{
    public const MAX_ATTACHMENT_MB = 50;

    public function __construct(
        protected SettingRepository $settingRepository,
    ) {}

    /** @return list<string>|null the extensions $collection takes - null for any */
    public function extensions(FileCollection $collection): ?array
    {
        $own = $collection->extensions();

        if ($collection !== FileCollection::Attachments) {
            return $own;
        }

        $chosen = array_values(array_intersect($own, self::listed((string) $this->settingRepository->get('files_attachment_types', ''))));

        return $chosen ?: $own;
    }

    public function maxKilobytes(FileCollection $collection): int
    {
        if ($collection !== FileCollection::Attachments) {
            return $collection->maxKilobytes();
        }

        $megabytes = (int) $this->settingRepository->get('files_attachment_max_mb', 0);

        return $megabytes > 0 ? min($megabytes, self::MAX_ATTACHMENT_MB) * 1024 : $collection->maxKilobytes();
    }

    /** @return array{extensions: list<string>|null, max_bytes: int} what a business may attach, for its pages */
    public function attachments(): array
    {
        return [
            'extensions' => $this->extensions(FileCollection::Attachments),
            'max_bytes'  => $this->maxKilobytes(FileCollection::Attachments) * 1024,
        ];
    }

    /** The admin's choice of types for $collection when it leaves some of izSuite's out, or null. */
    public function narrowed(FileCollection $collection): ?array
    {
        $extensions = $this->extensions($collection);

        return $extensions !== null && $extensions !== $collection->extensions() ? $extensions : null;
    }

    /** ["pdf", "jpg", "png"] as people read it: "PDF, JPG or PNG". */
    public static function named(array $extensions): string
    {
        $words = array_map('strtoupper', $extensions);
        $last  = array_pop($words);

        return $words ? implode(', ', $words).' or '.$last : (string) $last;
    }

    /** "pdf, JPG ,png" as the extensions it lists: lowercase, trimmed, no repeats. */
    public static function listed(string $text): array
    {
        return array_values(array_unique(array_filter(array_map(fn (string $extension) => strtolower(trim($extension, " .\t\n\r")), explode(',', $text)))));
    }
}
