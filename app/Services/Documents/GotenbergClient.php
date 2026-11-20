<?php

namespace App\Services\Documents;

use App\Exceptions\PdfUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Gotenberg (see docker-compose.yml): HTML in, a PDF out, made by headless
 * Chromium - so a document's PDF looks like its HTML, in any script its fonts
 * cover (Doc 06 section 15: Unicode and translated text). Every page gets the
 * footer, with its page number and the page count.
 *
 * Everything a document shows travels inside its HTML; Gotenberg has no way
 * out to fetch anything.
 */
class GotenbergClient
{
    /** Paper sizes in inches, width by height - a wide report turned on its side, and a POS receipt's 80 mm roll (Doc 18 Phase C4). */
    private const PAPER = [
        'a4'               => ['8.27', '11.69'],
        'letter'           => ['8.5', '11'],
        'a4_landscape'     => ['11.69', '8.27'],
        'letter_landscape' => ['11', '8.5'],
        'receipt'          => ['3.15', '11'],
    ];

    /** Margins in inches - top, bottom, left, right - where a paper needs its own: a receipt's roll runs to its edges. */
    private const MARGINS = [
        'receipt' => ['0.15', '0.2', '0.12', '0.12'],
    ];

    /** Papers printed as one page as long as what is on it: a receipt's roll is cut where it ends. */
    private const ONE_PAGE = ['receipt'];

    public function pdf(string $html, string $paper, string $footer): string
    {
        [$width, $height]             = self::PAPER[$paper] ?? self::PAPER['a4'];
        [$top, $bottom, $left, $right] = self::MARGINS[$paper] ?? ['0.55', '0.75', '0.55', '0.55'];

        try {
            $response = Http::timeout(config('services.gotenberg.timeout', 60))
                ->attach('files', $html, 'index.html')
                ->attach('files', $footer, 'footer.html')
                ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                    'paperWidth'      => $width,
                    'paperHeight'     => $height,
                    'marginTop'       => $top,
                    'marginBottom'    => $bottom,
                    'marginLeft'      => $left,
                    'marginRight'     => $right,
                    'printBackground' => 'true',
                    ...(in_array($paper, self::ONE_PAGE, true) ? ['singlePage' => 'true'] : []),
                ]);
        } catch (ConnectionException $e) {
            throw new PdfUnavailable('Gotenberg could not be reached.', previous: $e);
        }

        if (! $response->successful()) {
            throw new PdfUnavailable("Gotenberg answered {$response->status()}.");
        }

        return $response->body();
    }
}
