{{-- A quotation, proforma invoice, invoice or credit note, drawn up for its
     PDF - or, with $pdfUrl, for a screen, as its secure link shows it
     (DocumentRenderer). --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @include('documents.partials.styles')
</head>
<body @class(['is-screen' => $pdfUrl])>
    @if ($pdfUrl)
        <div class="bar">
            <strong>{{ $business['name'] }}</strong>
            <a href="{{ $pdfUrl }}">{{ __('Download PDF', [], $lang) }}</a>
        </div>
    @endif

    <main class="sheet">
        @include('documents.partials.header')

        @if ($proforma)
            <p class="notice">{{ __('This is a proforma invoice, not a tax invoice. It asks for payment before delivery and becomes an invoice once paid.', [], $lang) }}</p>
        @endif

        @include('documents.partials.parties')
        @include('documents.partials.lines')
        @include('documents.partials.closing')

        @if ($pdfUrl)
            <p class="powered">Powered by izSuite</p>
        @endif
    </main>
</body>
</html>
