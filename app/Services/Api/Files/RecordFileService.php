<?php

namespace App\Services\Api\Files;

use App\Enums\FileCollection;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The files of one record's collection - a contact's attachments, an item's
 * image or digital files: listing, adding and removing them, each logged by
 * name against the record. $record is how the log names it: 'contact "Uddin
 * Traders"'.
 */
class RecordFileService
{
    public function __construct(
        protected FileStorage $fileStorage,
        protected WorkspaceFileRepository $workspaceFileRepository,
    ) {}

    /** @return Collection<int, WorkspaceFile> */
    public function list(Model $owner, FileCollection $collection): Collection
    {
        return $this->workspaceFileRepository->inCollectionWithUploader($owner, $collection);
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @return Collection<int, WorkspaceFile>
     */
    public function add(Workspace $workspace, Model $owner, FileCollection $collection, array $uploads, string $field, string $record): Collection
    {
        $files = $this->fileStorage->store($workspace, $owner, $collection, $uploads, $field);

        // Logged here, by name, so LogApiWriteActivity does not add its own
        // vaguer entry for the same request.
        foreach ($files as $file) {
            ActivityLogService::record('created', match ($collection) {
                FileCollection::Attachments => "Attached {$this->quoted($file->name)} to {$record}",
                FileCollection::Image       => "Added the image of {$record}",
                default                     => "Added {$collection->noun()} {$this->quoted($file->name)} to {$record}",
            }, $owner, ['source' => 'api', 'file' => $file->name, 'size' => $file->size]);
        }

        return $this->workspaceFileRepository->loadUploader($files);
    }

    /** One of $owner's files of $collection; another record's - or business's - answers like one that does not exist. */
    public function find(Model $owner, FileCollection $collection, int $id): WorkspaceFile
    {
        return $this->workspaceFileRepository->findInCollection($owner, $collection, $id)
            ?? abort(404, 'File not found.');
    }

    public function remove(Model $owner, FileCollection $collection, WorkspaceFile $file, string $record): void
    {
        $this->fileStorage->delete([$file]);

        ActivityLogService::record('deleted', match ($collection) {
            FileCollection::Attachments => "Removed attachment {$this->quoted($file->name)} from {$record}",
            FileCollection::Image       => "Removed the image of {$record}",
            default                     => "Removed {$collection->noun()} {$this->quoted($file->name)} from {$record}",
        }, $owner, ['source' => 'api', 'file' => $file->name]);
    }

    /** A name, in quotes and cut down, so the log entry fits its 255-character column. */
    public function quoted(string $name): string
    {
        return '"'.Str::limit($name, 80).'"';
    }
}
