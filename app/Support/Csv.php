<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV files as people bring them and take them away: written in UTF-8 with
 * the byte-order mark Excel looks for, and read whether they come with
 * commas or semicolons (Excel's choice in much of Europe), with or without
 * the mark, in UTF-8 or Windows-1252.
 *
 * A cell a spreadsheet would run as a formula - one starting with =, +, -,
 * @ or a tab - is written with an apostrophe in front, so opening an export
 * never runs anything ("+880 1711-000000" stays a phone number). Reading
 * takes the apostrophe back off.
 */
final class Csv
{
    private const BOM = "\xEF\xBB\xBF";

    /** A download of $rows under $headers, written as it goes - an export of thousands is never held whole. */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            fwrite($out, self::BOM);
            fputcsv($out, array_map(self::cell(...), $headers), ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A value as a cell: text, safe from formulas. */
    public static function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null  => '',
            is_bool($value)  => $value ? 'Yes' : 'No',
            default          => (string) $value,
        };

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'{$text}" : $text;
    }

    /** A cell as it was meant: trimmed, without the apostrophe an export put in front of a formula-like value. */
    public static function value(string $cell): string
    {
        $cell = trim($cell);

        return strlen($cell) > 1 && $cell[0] === "'" && in_array($cell[1], ['=', '+', '-', '@'], true) ? substr($cell, 1) : $cell;
    }

    /**
     * Rewrites the file at $path as UTF-8 without a byte-order mark - from
     * Windows-1252 when it is not UTF-8 already - so reading it later needs no
     * guessing.
     */
    public static function normalise(string $path): void
    {
        $content = (string) file_get_contents($path);

        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, strlen(self::BOM));
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        file_put_contents($path, $content);
    }

    /** The separator of the file at $path: whichever of comma, semicolon and tab its first line has most of. */
    public static function delimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line   = (string) fgets($handle);
        fclose($handle);

        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * The rows of the file at $path, each with the line it starts on: the
     * header row first (line 1), then the data. Rows with nothing in them
     * are left out.
     *
     * @return \Generator<int, array{0: int, 1: list<string>}>
     */
    public static function rows(string $path, string $delimiter): \Generator
    {
        $handle = fopen($path, 'r');
        $line   = 0;

        try {
            while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null] || implode('', array_map(fn ($cell) => trim((string) $cell), $cells)) === '') {
                    continue;
                }

                yield [$line, array_map(fn ($cell) => self::value((string) $cell), $cells)];
            }
        } finally {
            fclose($handle);
        }
    }
}
