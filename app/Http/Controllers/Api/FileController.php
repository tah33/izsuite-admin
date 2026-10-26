<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User\WorkspaceFile;
use App\Services\Files\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloading a business's file. The app asks for a link (signed in, for the
 * business picked) and opens it: a private file's link works for a few
 * minutes and needs no sign-in, so a plain browser download can follow it -
 * from DigitalOcean Spaces directly, or from download() here for a file on
 * this server.
 */
class FileController extends Controller
{
    public function __construct(
        protected FileStorage $fileStorage,
    ) {}

    /** A link that downloads one of the business's files. Another business's answers like one that does not exist. */
    public function link(Request $request, int $id): JsonResponse
    {
        try {
            $file = WorkspaceFile::where('workspace_id', $this->currentWorkspace($request)->id)->find($id)
                ?? abort(404, 'File not found.');

            return response()->json([
                'data' => [
                    'url'        => $file->isPublic() ? $this->fileStorage->url($file) : $this->fileStorage->downloadLink($file),
                    'name'       => $file->name,
                    'expires_at' => $file->isPublic() ? null : now()->addMinutes(config('files.link_minutes', 5))->toIso8601String(),
                ],
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A file on this server, through a link link() signed. */
    public function download(WorkspaceFile $file): StreamedResponse
    {
        try {
            return $this->fileStorage->download($file);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
