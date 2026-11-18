<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The website's rich-text fields - a sales document's notes, terms and
 * payment instructions: bold, italic, underline, paragraphs and lists,
 * nothing else.
 *
 * html() turns what it is given into that HTML: rich text is cleaned, and
 * plain text - written before these fields took formatting, or sent by a form
 * that has no editor - is escaped into paragraphs. Only the tags below
 * survive, with no attributes, so the result is safe to print as HTML - on
 * the website and in the PDF. clean() is the same, named for saving.
 */
final class RichText
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

    /** The HTML to keep, or null when there is no text in it. */
    public static function clean(?string $value): ?string
    {
        return self::html($value);
    }

    /**
     * $value as plain text, for what prints as text - a payment account's
     * instructions among its details: a line for each paragraph and break,
     * "1. " before a numbered list's items and "• " before any other's.
     */
    public static function plain(?string $value): ?string
    {
        $html = self::html($value);

        if ($html === null) {
            return null;
        }

        // A numbered list's items are numbered; any other item takes a bullet.
        $html = preg_replace_callback('#<ol>(.*?)</ol>#is', function (array $list) {
            $number = 0;

            return preg_replace_callback('#<li>\s*#i', function () use (&$number) {
                return (++$number).'. ';
            }, $list[1])."\n";
        }, $html);

        $text = preg_replace(['#<li>\s*#i', '#<br>#i', '#</(p|li|ul|ol)>#i'], ['• ', "\n", "\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{2,}/", "\n", $text));
    }

    /** $value as HTML to show: rich text cleaned, plain text escaped into paragraphs. */
    public static function html(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/<\/?('.implode('|', self::TAGS).')\b/i', $value)) {
            return self::sanitize($value);
        }

        // Plain text: a blank line starts a paragraph, a line break stays one.
        $paragraphs = preg_split('/\R{2,}/', trim($value));

        return implode('', array_map(
            fn (string $paragraph) => '<p>'.preg_replace('/\R/', '<br>', e($paragraph)).'</p>',
            $paragraphs,
        ));
    }

    private static function sanitize(string $value): ?string
    {
        $document = new DOMDocument;
        // The wrapper keeps loadHTML() from adding <html><body>, and the
        // declaration makes it read the text as UTF-8.
        @$document->loadHTML('<?xml encoding="UTF-8"><div>'.$value.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return null;
        }

        self::strip($root);

        $html = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        // The editor leaves an empty paragraph after a list; it shows as a gap.
        $html = trim(preg_replace('#<p>(\s|<br>)*</p>#i', '', $html));

        return trim(strip_tags($html)) === '' ? null : $html;
    }

    /** Keeps $node's allowed elements, without attributes; others give up only their text. */
    private static function strip(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            // A script's or style's content is not text anyone wrote.
            if (in_array(strtolower($child->tagName), ['script', 'style', 'template'], true)) {
                $node->removeChild($child);

                continue;
            }

            self::strip($child);

            if (! in_array(strtolower($child->tagName), self::TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }
        }
    }
}
