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
    /** Paper sizes in inches, width by height. */
    private const PAPER = [
        'a4'     => ['8.27', '11.69'],
        'letter' => ['8.5', '11'],
    ];

    public function pdf(string $html, string $paper, string $footer): string
    {
        [$width, $height] = self::PAPER[$paper] ?? self::PAPER['a4'];

        try {
            $response = Http::timeout(config('services.gotenberg.timeout', 60))
                ->attach('files', $html, 'index.html')
                ->attach('files', $footer, 'footer.html')
                ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                    'paperWidth'      => $width,
                    'paperHeight'     => $height,
                    'marginTop'       => '0.55',
                    'marginBottom'    => '0.75',
                    'marginLeft'      => '0.55',
                    'marginRight'     => '0.55',
                    'printBackground' => 'true',
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
