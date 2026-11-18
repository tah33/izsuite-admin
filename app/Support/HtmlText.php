<?php

namespace App\Support;

/**
 * Markup written in the admin's editor as plain text, for the app to show as
 * text and never as markup (Doc 15 Phase B3): paragraphs and line breaks
 * kept, list items marked, a link's address kept after its words, scripts
 * and styles dropped whole, and every other tag gone.
 */
class HtmlText
{
    public static function of(?string $html): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', (string) $html) ?? '';

        $text = preg_replace_callback('#<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a\s*>#is', function (array $link): string {
            $words   = trim(html_entity_decode(strip_tags($link[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $address = trim(html_entity_decode($link[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $words === '' || $words === $address ? $address : "{$words} ({$address})";
        }, $text) ?? $text;

        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li\b[^>]*>#i', "\n- ", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|ul|ol|blockquote|pre|tr|table)\s*>#i', "\n\n", $text) ?? $text;

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
