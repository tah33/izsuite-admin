<?php

namespace App\Services\Files;

use App\Enums\FileCollection;
use App\Jobs\DeleteStoredFiles;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\WorkspaceFileRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Where a business's files are stored, and the only way in and out of it.
 *
 * New files go to the storage config/files.php names - this server ("local")
 * or DigitalOcean Spaces ("spaces") - on its public or private disk by their
 * collection. Every file records the disk it went to, so it keeps working from
 * there after the setting changes, until move() takes it across.
 *
 * Public files (item images, the logo) have an address anyone may open.
 * Private ones (attachments, digital files) only download through a link that
 * works for a few minutes: signed by Laravel on this server, presigned by
 * Spaces there.
 */
class FileStorage
{
    public function __construct(
        private StorageUsage $usage,
        private Thumbnails $thumbnails,
        private WorkspaceFileRepository $files,
    ) {}

    /** The disk new public or private files go to. */
    public function diskFor(bool $public, ?string $storage = null): string
    {
        $disks = config('files.disks.'.($storage ?? config('files.storage'))) ?? config('files.disks.local');

        return $disks[$public ? 'public' : 'private'];
    }

    /**
     * Stores $uploads as $owner's files in $collection - all of them or none.
     * The business needs room for them together (Doc 13 section 13.14), or a
     * 422 on $field says so. A collection that holds one file (an image, the
     * logo) gives up the file it had.
     *
     * @param  list<UploadedFile>  $uploads
     * @return Collection<int, WorkspaceFile>
     */
    public function store(Workspace $workspace, Model $owner, FileCollection $collection, array $uploads, string $field): Collection
    {
        $replaced = $collection->isSingle() ? $this->files->inCollection($owner, $collection) : new Collection;

        $this->usage->ensureRoomFor(
            $workspace,
            array_sum(array_map(fn (UploadedFile $upload) => (int) $upload->getSize(), $uploads)),
            (int) $replaced->sum('size'),
            $field,
        );

        // What is written so far, taken back if anything fails.
        $written = [];

        try {
            $rows = array_map(fn (UploadedFile $upload) => $this->put($workspace, $collection, $upload, $written), $uploads);

            return DB::transaction(function () use ($workspace, $owner, $collection, $rows, $replaced) {
                $files = new Collection(array_map(fn (array $row) => $this->files->createFor($owner, [
                    ...$row,
                    'workspace_id' => $workspace->id,
                    'collection'   => $collection,
                    'uploaded_by'  => auth()->id(),
                ]), $rows));

                $this->delete($replaced);

                return $files;
            });
        } catch (\Throwable $e) {
            $this->forget($written);

            throw $e;
        }
    }

    /**
     * Deletes $files: their rows now, their stored copies once that is
     * committed - so a deletion that is rolled back keeps them.
     *
     * @param  iterable<WorkspaceFile>  $files
     */
    public function delete(iterable $files): void
    {
        $objects = [];

        foreach ($files as $file) {
            $objects = [...$objects, ...$this->objectsOf($file)];
            $this->files->delete($file);
        }

        if ($objects) {
            DeleteStoredFiles::dispatch($objects)->afterCommit();
        }
    }

    /** The stored copies of $files, to remove once rows gone another way (a deleted business) are committed. */
    public function forgetLater(iterable $files): void
    {
        $objects = [];

        foreach ($files as $file) {
            $objects = [...$objects, ...$this->objectsOf($file)];
        }

        if ($objects) {
            DeleteStoredFiles::dispatch($objects)->afterCommit();
        }
    }

    /** Where anyone may open a public file - or its thumbnail, when it has one. Null for a private file. */
    public function url(WorkspaceFile $file, bool $thumbnail = false): ?string
    {
        if (! $file->isPublic()) {
            return null;
        }

        return Storage::disk($file->disk)->url($thumbnail && $file->thumbnail_path ? $file->thumbnail_path : $file->path);
    }

    /** A link that downloads $file for the next few minutes, without signing in. */
    public function downloadLink(WorkspaceFile $file): string
    {
        $expires = now()->addMinutes(config('files.link_minutes', 5));

        if ($this->isSpaces($file->disk)) {
            // The plain-ASCII name is for browsers that cannot read the UTF-8 one.
            $fallback = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', Str::ascii($file->name));

            return Storage::disk($file->disk)->temporaryUrl($file->path, $expires, [
                'ResponseContentDisposition' => HeaderUtils::makeDisposition('attachment', $file->name, $fallback ?: 'file'),
            ]);
        }

        return URL::temporarySignedRoute('api.files.download', $expires, ['file' => $file->id]);
    }

    /** $file itself, for a signed link on this server. */
    public function download(WorkspaceFile $file): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404, 'File not found.');

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    /**
     * Moves $file to $storage ("local" or "spaces"), on the disk its
     * visibility asks for there. The copy is checked before the record points
     * at it, and the old one removed only after. False when it is already
     * there.
     */
    public function move(WorkspaceFile $file, string $storage): bool
    {
        $target = $this->diskFor($file->isPublic(), $storage);

        if ($target === $file->disk) {
            return false;
        }

        $old = $this->objectsOf($file);

        foreach (array_filter([$file->path, $file->thumbnail_path]) as $path) {
            $stream = Storage::disk($file->disk)->readStream($path);

            Storage::disk($target)->writeStream($path, $stream, ['visibility' => $file->visibility]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (Storage::disk($target)->size($path) !== Storage::disk($file->disk)->size($path)) {
                Storage::disk($target)->delete($path);

                throw new \RuntimeException("The copy of {$path} on {$target} does not match the original.");
            }
        }

        $this->files->update($file, ['disk' => $target]);

        $this->forget($old);

        return true;
    }

    /**
     * Writes one upload to the disk of its collection and the current storage,
     * under the business's own folder, by a random name - the name it came
     * with is kept on its row. An image gets its thumbnail beside it.
     */
    private function put(Workspace $workspace, FileCollection $collection, UploadedFile $upload, array &$written): array
    {
        $public    = $collection->isPublic();
        $disk      = $this->diskFor($public);
        $directory = "workspaces/{$workspace->id}/{$collection->value}";
        $path      = $upload->store($directory, ['disk' => $disk, 'visibility' => $public ? 'public' : 'private']);

        if (! $path) {
            throw new \RuntimeException("The file {$upload->getClientOriginalName()} could not be stored.");
        }

        $written[] = [$disk, $path];

        $thumbnail = $collection === FileCollection::Image ? $this->thumbnails->make($upload, $disk, $directory, $public) : null;

        if ($thumbnail) {
            $written[] = [$disk, $thumbnail];
        }

        return [
            'disk'           => $disk,
            'path'           => $path,
            'thumbnail_path' => $thumbnail,
            'visibility'     => $public ? WorkspaceFile::PUBLIC : WorkspaceFile::PRIVATE,
            'name'           => $this->cleanName($upload->getClientOriginalName()),
            'mime_type'      => $upload->getMimeType(),
            'size'           => (int) $upload->getSize(),
        ];
    }

    /** @return list<array{0: string, 1: string}> */
    private function objectsOf(WorkspaceFile $file): array
    {
        return array_map(fn (string $path) => [$file->disk, $path], array_values(array_filter([$file->path, $file->thumbnail_path])));
    }

    /** Removes stored copies at once - ones no row points at. */
    private function forget(array $objects): void
    {
        foreach ($objects as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function isSpaces(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    /** The name a file came with, without any folder in it or control characters, at most 255 characters. */
    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', $name))));

        if ($name === '') {
            return 'file';
        }

        if (mb_strlen($name) <= 255) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $keep      = 255 - ($extension === '' ? 0 : mb_strlen($extension) + 1);

        return mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, $keep).($extension === '' ? '' : ".{$extension}");
    }
}
