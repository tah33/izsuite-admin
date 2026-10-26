<?php

namespace App\Services\Api\Files;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Files\FileStorage;

/**
 * Links that download a business's files: a public file's own address, or,
 * for a private file, a link that works for a few minutes and needs no
 * sign-in.
 */
class FileLinkService
{
    public function __construct(
        protected WorkspaceFileRepository $workspaceFileRepository,
        protected FileStorage $fileStorage,
    ) {}

    /** One of the business's files. Another business's answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): WorkspaceFile
    {
        return $this->workspaceFileRepository->findFor($workspace, $id)
            ?? abort(404, 'File not found.');
    }

    /** @return array{url: ?string, name: string} */
    public function link(WorkspaceFile $file): array
    {
        return [
            'url'  => $file->isPublic() ? $this->fileStorage->url($file) : $this->fileStorage->downloadLink($file),
            'name' => $file->name,
        ];
    }
}
