{{-- The foot of every PDF page (Doc 06 section 15): the template's footer
     text, the page number and count - Chromium fills in the pageNumber and
     totalPages spans - and "Powered by izSuite", which Billing Starter always
     shows. A footer is its own page to Chromium: it takes no styles from the
     document, and needs its widths and sizes set. Chromium's own flex layout
     of the body is what holds it to the foot of the page, so it is left be. --}}
<!DOCTYPE html>
<html>
<head>
    <style>
        body { margin: 0; }
        .foot { box-sizing: border-box; display: flex; justify-content: space-between; gap: 12px; width: 100%; align-self: stretch; padding: 0 0.55in; font-family: 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 7.5px; color: #9ca3af; }
        .foot > span:first-child { flex: 1; }
        .foot > span:nth-child(2) { white-space: nowrap; }
        .foot > span:last-child { flex: 1; text-align: right; }
    </style>
</head>
<body>
    <div class="foot">
        <span>{{ $template['footer_text'] ?? '' }}</span>
        <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
        <span>Powered by izSuite</span>
    </div>
</body>
</html>
