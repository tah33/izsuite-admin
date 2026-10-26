<?php

namespace App\Models\Concerns;

use App\Enums\FileCollection;
use App\Models\User\WorkspaceFile;
use App\Services\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A record with files of its own (see WorkspaceFile). Deleting it deletes them:
 * their rows at once, the stored copies once the deletion is committed.
 */
trait HasFiles
{
    protected static function bootHasFiles(): void
    {
        static::deleting(fn (Model $model) => app(FileStorage::class)->delete($model->files()->get()));
    }

    /** Every file of the record, oldest first. */
    public function files(): MorphMany
    {
        return $this->morphMany(WorkspaceFile::class, 'attachable')->orderBy('id');
    }

    /** One collection's files, oldest first. */
    public function filesIn(FileCollection $collection): MorphMany
    {
        return $this->files()->where('collection', $collection->value);
    }

    /** The file of a collection that holds one (an image, a logo). */
    public function fileIn(FileCollection $collection): MorphOne
    {
        return $this->morphOne(WorkspaceFile::class, 'attachable')->where('collection', $collection->value);
    }
}
