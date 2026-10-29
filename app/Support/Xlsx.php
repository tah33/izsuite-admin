<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * An Excel workbook of one sheet (Doc 07 section 4: Export Excel), written
 * from a report's columns and rows: a bold header row that stays in view,
 * money and numbers as numbers, dates as dates and the rest as text - never
 * a formula, so opening one runs nothing. Made with PHP's own zip and XML
 * writer, without a spreadsheet library.
 */
final class Xlsx
{
    /** The styles a cell takes, as styles.xml lists them. */
    private const PLAIN = 0;

    private const BOLD = 1;

    private const DATE = 2;

    private const MONEY = 3;

    private const BOLD_MONEY = 4;

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';

    private const ROOT_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';

    /** Plain, bold, a date (28 Oct 2026), money (1,234.50) and bold money - in that order. */
    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="1"><numFmt numFmtId="164" formatCode="d mmm yyyy"/></numFmts>'
        .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="5">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .'<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';

    /**
     * The workbook's bytes: one sheet named after $title, $columns' labels
     * over $rows - the last of them bold when it holds the $totals.
     *
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public static function make(string $title, array $columns, array $rows, bool $totals = false): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $zip  = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The workbook could not be made.');
        }

        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::ROOT_RELS);
        $zip->addFromString('xl/workbook.xml', self::workbook($title));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet(array_values($columns), array_values($rows), $totals));
        $zip->close();

        $bytes = (string) file_get_contents($path);

        unlink($path);

        return $bytes;
    }

    /** A sheet name may hold none of \ / ? * [ ] : nor start or end with an apostrophe, and has 31 characters at most. */
    private static function workbook(string $title): string
    {
        $name = mb_substr(trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title), " '"), 0, 31) ?: 'Report';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function sheet(array $columns, array $rows, bool $totals): string
    {
        $xml = new XMLWriter;

        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // The header row stays in view as the rows scroll.
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '1');
        $xml->writeAttribute('topLeftCell', 'A2');
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('cols');

        foreach ($columns as $index => $column) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($index + 1));
            $xml->writeAttribute('max', (string) ($index + 1));
            $xml->writeAttribute('width', (string) self::width($column, $rows));
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }

        $xml->endElement();
        $xml->startElement('sheetData');

        self::row($xml, 1, array_map(fn (array $column) => [$column['label'], false, self::BOLD], $columns));

        foreach ($rows as $index => $row) {
            $bold = $totals && $index === count($rows) - 1;

            self::row($xml, $index + 2, array_map(fn (array $column) => self::cell($row[$column['key']] ?? null, $column['type'], $bold), $columns));
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @param  list<array{0: ?string, 1: bool, 2: int}>  $cells  each cell's value, whether it is a number, and its style */
    private static function row(XMLWriter $xml, int $number, array $cells): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);

        foreach ($cells as $index => [$value, $numeric, $style]) {
            if ($value === null || $value === '') {
                continue;
            }

            $xml->startElement('c');
            $xml->writeAttribute('r', self::column($index).$number);

            if ($style !== self::PLAIN) {
                $xml->writeAttribute('s', (string) $style);
            }

            if ($numeric) {
                $xml->writeElement('v', $value);
            } else {
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $xml->text($value);
                $xml->endElement();
                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->endElement();
    }

    /** @return array{0: ?string, 1: bool, 2: int} a report's value as a cell: a number, a day as a spreadsheet counts it, or text */
    private static function cell(mixed $value, string $type, bool $bold): array
    {
        if ($value === null || $value === '') {
            return [null, false, self::PLAIN];
        }

        $text = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;

        return match (true) {
            $type === 'money' && is_numeric($text)                                => [$text, true, $bold ? self::BOLD_MONEY : self::MONEY],
            in_array($type, ['number', 'percent'], true) && is_numeric($text)     => [$text, true, $bold ? self::BOLD : self::PLAIN],
            $type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 => [(string) self::serial($text), true, self::DATE],
            default                                                                => [$text, false, $bold ? self::BOLD : self::PLAIN],
        };
    }

    /** Days since 30 Dec 1899 - how a spreadsheet counts a date. */
    private static function serial(string $date): int
    {
        $utc = new DateTimeZone('UTC');

        return (int) (new DateTimeImmutable('1899-12-30', $utc))->diff(new DateTimeImmutable($date, $utc))->days;
    }

    /** "A" for the first column, "AA" for the 27th. */
    private static function column(int $index): string
    {
        $name = '';

        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $name = chr(65 + ($number - 1) % 26).$name;
        }

        return $name;
    }

    /** Wide enough for the column's label and its longest value, within reason. */
    private static function width(array $column, array $rows): int
    {
        $longest = mb_strlen($column['label']);

        foreach ($rows as $row) {
            $longest = max($longest, mb_strlen((string) ($row[$column['key']] ?? '')));
        }

        return min(max($longest + 2, 8), 50);
    }
}
