{{-- The foot of every PDF page (Doc 06 section 15): the template's footer
     text, the page number and count - Chromium fills in the pageNumber and
     totalPages spans - and "Powered by izSuite", which Billing Starter always
     shows. Laid out as the template says (section 14): split across the page,
     or centred on one line. A footer is its own page to Chromium: it takes no
     styles from the document, and needs its widths and sizes set. Chromium's
     own flex layout of the body is what holds it to the foot of the page, so
     it is left be. Chromium also fills - or empties - elements of some
     classes, "text" among them, so the footer's own classes are prefixed. --}}
@php($text = $template['footer_text'] ?? '')
<!DOCTYPE html>
<html>
<head>
    <style>
        body { margin: 0; }
        .foot { box-sizing: border-box; display: flex; justify-content: space-between; gap: 12px; width: 100%; align-self: stretch; padding: 0 0.55in; font-family: 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 7.5px; color: #9ca3af; }
        .foot.split > span:first-child { flex: 1; }
        .foot.split > span:nth-child(2) { white-space: nowrap; }
        .foot.split > span:last-child { flex: 1; text-align: right; }
        .foot.centered { justify-content: center; align-items: baseline; gap: 6px; }
        .foot.centered > span { white-space: nowrap; }
        .foot.centered > .foot__text { white-space: normal; text-align: center; }
    </style>
</head>
<body>
    @if (($template['footer_layout'] ?? 'split') === 'centered')
        <div class="foot centered">
            @if ($text)
                <span class="foot__text">{{ $text }}</span><span>·</span>
            @endif
            <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span><span>·</span>
            <span>Powered by izSuite</span>
        </div>
    @else
        <div class="foot split">
            <span>{{ $text }}</span>
            <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
            <span>Powered by izSuite</span>
        </div>
    @endif
</body>
</html>
