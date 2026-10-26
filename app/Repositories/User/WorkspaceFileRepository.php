<?php

namespace App\Repositories\User;

use App\Enums\FileCollection;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * A business's stored files. Each belongs to a record (an item, a contact,
 * the business itself) and sits in one of its collections.
 */
class WorkspaceFileRepository
{
    /** One of the business's files; another business's is not found. */
    public function findFor(Workspace $workspace, int $id): ?WorkspaceFile
    {
        return WorkspaceFile::where('workspace_id', $workspace->id)->find($id);
    }

    /** Every file the business has, whatever it belongs to. */
    public function allFor(Workspace $workspace): Collection
    {
        return $workspace->businessFiles()->get();
    }

    /** Every business's files - or one business's - by id, read a chunk at a time. */
    public function lazyAll(?string $workspaceId): LazyCollection
    {
        return WorkspaceFile::query()
            ->when($workspaceId, fn ($query, $id) => $query->where('workspace_id', $id))
            ->orderBy('id')
            ->lazyById();
    }

    /** What the business's files take up, in bytes. */
    public function usedBytes(Workspace $workspace): int
    {
        return (int) WorkspaceFile::where('workspace_id', $workspace->id)->sum('size');
    }

    public function inCollection(Model $owner, FileCollection $collection): Collection
    {
        return $owner->filesIn($collection)->get();
    }

    /** $owner's files in $collection, each with who uploaded it. */
    public function inCollectionWithUploader(Model $owner, FileCollection $collection): Collection
    {
        return $owner->filesIn($collection)->with('uploader')->get();
    }

    public function findInCollection(Model $owner, FileCollection $collection, int $id): ?WorkspaceFile
    {
        return $owner->filesIn($collection)->find($id);
    }

    public function createFor(Model $owner, array $data): WorkspaceFile
    {
        return $owner->files()->create($data);
    }

    public function update(WorkspaceFile $file, array $data): void
    {
        $file->update($data);
    }

    public function delete(WorkspaceFile $file): void
    {
        $file->delete();
    }

    /**
     * @param  Collection<int, WorkspaceFile>  $files
     * @return Collection<int, WorkspaceFile>
     */
    public function loadUploader(Collection $files): Collection
    {
        return $files->load('uploader');
    }
}
