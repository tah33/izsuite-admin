<?php

namespace App\Http\Controllers;

use App\Models\User\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

abstract class Controller
{
    /**
     * The business the request is for, as the `workspace` middleware resolved
     * it. Only for routes behind workspace:required, where there always is one.
     */
    protected function currentWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    /**
     * A PDF the browser shows - or saves, with $download - named $filename,
     * and in plain ASCII too for browsers that cannot read the UTF-8 name.
     * Never cached: a draft's PDF changes as it is edited.
     */
    protected function pdfResponse(string $filename, string $bytes, bool $download = false): Response
    {
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%"]/', '_', Str::ascii($filename));

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $filename, $fallback ?: 'document.pdf'),
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * Unified success response for admin form submissions.
     *
     * For AJAX requests, returns JSON consumed by the global admin handler.
     * For non-AJAX requests, keeps standard Laravel redirects + flash messages.
     */
    protected function adminSuccess(
        Request $request,
        string $message,
        ?string $redirectUrl = null,
        array $data = [],
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => $message,
                'redirect' => $redirectUrl,
                'data'     => $data,
            ]);
        }

        return $redirectUrl
            ? redirect($redirectUrl)->with('success', $message)
            : back()->with('success', $message);
    }

    /**
     * Unified failure response for admin form submissions.
     */
    protected function adminFailure(
        Request $request,
        string $message,
        int $status = 500,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], $status);
        }

        return back()->withInput()->with('error', $message);
    }
}
