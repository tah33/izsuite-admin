{{-- A purchase's or an expense's record (Doc 09 and Doc 10, section 10):
     the business, the supplier or payee and their invoice or receipt, the
     lines, the totals and what it still owes, drawn up for its PDF
     (DocumentRenderer). --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    <main class="sheet">
        @include('documents.partials.header')
        @include('documents.partials.parties')
        @include('documents.partials.lines')
        @include('documents.partials.closing')
    </main>
</body>
</html>
