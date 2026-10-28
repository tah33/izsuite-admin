<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF maker (Gotenberg) did not answer, or could not make the PDF. Asked
 * for over the API - or from a secure link - the reader is told to try again
 * in a moment; inside an email job, the email fails and says so.
 */
class PdfUnavailable extends RuntimeException
{
    private const MESSAGE = 'PDFs cannot be made right now. Please try again in a moment.';

    public function render(Request $request): Response
    {
        return $request->expectsJson()
            ? response()->json(['message' => self::MESSAGE], 503)
            : response(self::MESSAGE, 503, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
