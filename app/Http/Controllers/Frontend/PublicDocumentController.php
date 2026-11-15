<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\AnswerQuotationRequest;
use App\Http\Requests\Frontend\CustomerCommentRequest;
use App\Services\Documents\CustomerResponses;
use App\Services\Documents\PublicDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * What a customer opens from a secure link (Doc 06 section 9) - /d/{token} -
 * without signing in: the document on a page of its own, and its PDF. Kept
 * out of search engines, other sites' frames and referrer headers; a link
 * that no longer works opens a page saying so.
 *
 * On the full Sales app the page also takes the customer's answer to a
 * quotation, and comments (Doc 16 Phase G2): plain forms posting back here,
 * each answered with the page again and what was done.
 */
class PublicDocumentController extends Controller
{
    private const HEADERS = [
        'X-Robots-Tag'            => 'noindex, nofollow',
        'Referrer-Policy'         => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
    ];

    public function __construct(
        protected PublicDocuments $publicDocuments,
        protected CustomerResponses $customerResponses,
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

    public function accept(AnswerQuotationRequest $request, string $token): BaseResponse
    {
        try {
            return $this->answered($token, $this->customerResponses->accept($token, $request->validated('name'), $this->source($request)));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reject(AnswerQuotationRequest $request, string $token): BaseResponse
    {
        try {
            return $this->answered($token, $this->customerResponses->reject($token, $request->validated('name'), $request->validated('reason'), $this->source($request)));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function comment(CustomerCommentRequest $request, string $token): BaseResponse
    {
        try {
            return $this->answered($token, $this->customerResponses->comment($token, $request->validated('name'), $request->validated('body'), $this->source($request)));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Back to the page, saying what was done - or the unavailable page, when the link no longer works. */
    private function answered(string $token, ?string $message): BaseResponse
    {
        return $message === null ? $this->unavailable() : redirect()->route('documents.public', $token)->with('responded', $message);
    }

    /** @return array{ip: ?string, agent: ?string} where the customer did it from */
    private function source(Request $request): array
    {
        return ['ip' => $request->ip(), 'agent' => $request->userAgent()];
    }

    private function unavailable(): Response
    {
        return response(view('documents.unavailable')->render(), 410, [...self::HEADERS, 'Cache-Control' => 'private, no-store']);
    }
}
