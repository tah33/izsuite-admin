{{-- A document's look (Doc 06 sections 14 and 15): its template's colour and
     font; table headings repeated on every page; rows never split across
     pages. On a screen - a secure link - it sits on a page of its own. --}}
<style>
    :root { --accent: {{ $template['color'] }}; }
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
        margin: 0;
        font-family: {!! $template['font'] === 'serif' ? "'Noto Serif', 'Liberation Serif', Georgia, serif" : "'Noto Sans', 'Liberation Sans', Arial, sans-serif" !!};
        font-size: 10pt;
        line-height: 1.45;
        color: #1f2937;
    }
    .sheet { position: relative; }
    .muted { color: #6b7280; }
    .num { text-align: right; white-space: nowrap; }
    .label { margin-bottom: 4px; font-size: 7.5pt; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }

    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 14px; border-bottom: 3px solid var(--accent); }
    .head--logo_right { flex-direction: row-reverse; }
    .head--centered { flex-direction: column; align-items: center; text-align: center; }
    .logo { display: block; max-width: 200px; max-height: 64px; margin-bottom: 8px; }
    .head--centered .logo { margin: 0 auto 8px; }
    .business-name { font-size: 13pt; font-weight: 700; }
    .title { text-align: right; }
    .head--logo_right .title { text-align: left; }
    .head--centered .title { text-align: center; }
    .title h1 { margin: 0; font-size: 19pt; letter-spacing: .03em; text-transform: uppercase; color: var(--accent); }
    .number { margin-top: 2px; font-size: 11pt; font-weight: 600; }

    /* Across the middle of the page - fixed, so it is on every printed page. */
    .stamp { position: fixed; top: 45%; left: 50%; padding: 4px 28px; border: 6px solid; border-radius: 10px; transform: translate(-50%, -50%) rotate(-22deg); font-size: 60pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; white-space: nowrap; opacity: .13; pointer-events: none; }
    .is-screen .stamp { position: absolute; top: 40%; }
    .stamp--paid { color: #15803d; }
    .stamp--void, .stamp--cancelled { color: #b91c1c; }
    .stamp--draft, .stamp--reversed { color: #6b7280; }
    .notice { margin: 12px 0 0; padding: 6px 10px; border-left: 3px solid var(--accent); background: #f9fafb; font-size: 9pt; }

    .parties { display: flex; justify-content: space-between; gap: 24px; margin: 18px 0; }
    .customer-name { font-size: 11pt; font-weight: 700; }
    .facts { border-collapse: collapse; }
    .facts td { padding: 1px 0 1px 18px; }

    table.lines { width: 100%; margin-top: 6px; border-collapse: collapse; }
    table.lines thead { display: table-header-group; }
    table.lines tr { page-break-inside: avoid; }
    table.lines th { padding: 6px 8px; background: var(--accent); color: #fff; font-size: 8pt; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
    table.lines th.num { text-align: right; }
    table.lines td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .line-name { font-weight: 600; }
    .line-note { color: #6b7280; font-size: 8.5pt; white-space: pre-line; }

    .totals { width: 48%; margin: 12px 0 0 auto; border-collapse: collapse; page-break-inside: avoid; }
    .totals td { padding: 4px 8px; }
    .totals .strong td { border-top: 1px solid #d1d5db; font-size: 11pt; font-weight: 700; }
    .texts { margin-top: 18px; }
    .text-block { margin-bottom: 12px; white-space: pre-line; page-break-inside: avoid; }
    .signature { margin-top: 24px; text-align: right; page-break-inside: avoid; }
    .signature img { max-width: 220px; max-height: 70px; }

    .received { margin: 18px 0; padding: 14px 16px; border-radius: 6px; background: #f9fafb; }
    .received-amount { font-size: 20pt; font-weight: 800; color: var(--accent); }
    .rows { width: 100%; border-collapse: collapse; }
    .rows td { padding: 6px 0; border-bottom: 1px solid #e5e7eb; }

    .powered { margin-top: 28px; font-size: 8pt; text-align: center; color: #9ca3af; }
    .is-screen { padding: 0 12px 32px; background: #eef0f3; }
    .is-screen .sheet { max-width: 820px; margin: 0 auto; padding: 40px; border-radius: 8px; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
    .bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; max-width: 820px; margin: 0 auto; padding: 14px 0; }
    .bar a { padding: 8px 14px; border-radius: 6px; background: var(--accent); color: #fff; font-weight: 600; text-decoration: none; }
    @media (max-width: 640px) {
        .is-screen .sheet { padding: 20px; }
        .parties, .head { flex-direction: column; }
        .title, .head--logo_right .title { text-align: left; }
        .totals { width: 100%; }
    }
</style>
