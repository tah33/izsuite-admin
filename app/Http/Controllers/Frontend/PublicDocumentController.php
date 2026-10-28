<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Documents\PublicDocuments;
use Illuminate\Http\Response;

/**
 * What a customer opens from a secure link (Doc 06 section 9) - /d/{token} -
 * without signing in: the document on a page of its own, and its PDF. Kept
 * out of search engines, other sites' frames and referrer headers; a link
 * that no longer works opens a page saying so.
 */
class PublicDocumentController extends Controller
{
    private const HEADERS = [
        'X-Robots-Tag'            => 'noindex, nofollow',
        'Referrer-Policy'         => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
    ];

    public function __construct(
        protected PublicDocuments $publicDocuments,
    ) {}

    public function show(string $token): Response
    {
        try {
            $page = $this->publicDocuments->page($token, route('documents.public.pdf', $token));

            return $page
                ? response($page->html, 200, [...self::HEADERS, 'Cache-Control' => 'private, no-store'])
                : $this->unavailable();

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function pdf(string $token): Response
    {
        try {
            $pdf = $this->publicDocuments->pdf($token);

            return $pdf ? $this->pdfResponse($pdf[0], $pdf[1], true)->withHeaders(self::HEADERS) : $this->unavailable();

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function unavailable(): Response
    {
        return response(view('documents.unavailable')->render(), 410, [...self::HEADERS, 'Cache-Control' => 'private, no-store']);
    }
}
