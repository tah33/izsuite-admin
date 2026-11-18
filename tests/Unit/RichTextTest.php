<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_it_keeps_the_formatting_the_editor_makes(): void
    {
        $html = '<p>Pay <strong>within</strong> <em>30</em> <u>days</u>.<br>Thanks</p><ul><li>One</li></ul><ol><li>Two</li></ol>';

        $this->assertSame($html, RichText::clean($html));
    }

    public function test_it_drops_scripts_attributes_and_other_tags(): void
    {
        $dirty = '<p onclick="x()">Hi <a href="javascript:alert(1)">there</a><script>alert(1)</script><img src=x onerror=alert(1)><style>p{}</style></p>';

        $this->assertSame('<p>Hi there</p>', RichText::clean($dirty));
    }

    public function test_an_empty_editor_saves_nothing(): void
    {
        $this->assertNull(RichText::clean('<p></p>'));
        $this->assertNull(RichText::clean('  '));
        $this->assertNull(RichText::clean(null));
        $this->assertSame('<ul><li><p>One</p></li></ul>', RichText::clean('<ul><li><p>One</p></li></ul><p></p>'));
    }

    public function test_plain_text_from_before_shows_as_escaped_paragraphs(): void
    {
        $this->assertSame(
            '<p>Bank: ABC<br>Acc: 123</p><p>No &lt;script&gt; runs</p>',
            RichText::html("Bank: ABC\nAcc: 123\n\nNo <script> runs"),
        );
    }

    public function test_plain_text_is_saved_escaped_once(): void
    {
        $this->assertSame('<p>a &lt; b &amp; c</p>', RichText::clean('a < b & c'));
        $this->assertSame('<p>a &lt; b &amp; c</p>', RichText::html(RichText::clean('a < b & c')));
    }

    public function test_it_reads_as_plain_text_where_text_prints(): void
    {
        $this->assertSame(
            "Bank: ABC\nQuote the invoice number\n• Before 5 & on time\n• Monday",
            RichText::plain('<p><strong>Bank:</strong> ABC<br>Quote the invoice number</p><ul><li><p>Before 5 &amp; on time</p></li><li>Monday</li></ul>'),
        );
        $this->assertSame("Steps:\n1. Transfer\n2. Email", RichText::plain('<p>Steps:</p><ol><li><p>Transfer</p></li><li><p>Email</p></li></ol>'));
        $this->assertSame('Old text', RichText::plain('Old text'));
        $this->assertNull(RichText::plain(null));
    }

    public function test_it_keeps_unicode(): void
    {
        $this->assertSame('<p>বাংলা — Ünïcode</p>', RichText::clean('<p>বাংলা — Ünïcode</p>'));
    }
}
