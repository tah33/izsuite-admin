<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Files\FileLinkResource;
use App\Models\User\WorkspaceFile;
use App\Services\Api\Files\FileLinkService;
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
        protected FileLinkService $fileLinkService,
        protected FileStorage $fileStorage,
    ) {}

    /** A link that downloads one of the business's files. Another business's answers like one that does not exist. */
    public function link(Request $request, int $id): JsonResponse
    {
        try {
            $file = $this->fileLinkService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => new FileLinkResource($this->fileLinkService->link($file)),
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
