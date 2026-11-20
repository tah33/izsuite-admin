{{-- A receipt printer's test page (Doc 18 Phase I3), on the receipt's 80 mm
     roll: the business, the register and the printer, and when it was made.
     Read whole, the printer, its paper and its width are right. --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('documents.partials.pos-roll-styles')
</head>
<body>
<main>
    @include('documents.partials.pos-roll-business')

    <div class="center business" style="margin-top: 6px">{{ __('Test Page', [], $lang) }}</div>

    <div class="rule"></div>

    <table>
        <tr><td>{{ __('Register', [], $lang) }}</td><td class="num">{{ $register }}</td></tr>
        <tr><td>{{ __('Printer', [], $lang) }}</td><td class="num">{{ $printer }}</td></tr>
        <tr><td>{{ __('Printed', [], $lang) }}</td><td class="num">{{ $printed }}</td></tr>
    </table>

    <div class="rule"></div>
    <div class="center small">0123456789 ABCDEFGHIJKLMNOPQRSTUVWXYZ</div>
    <div class="center small" style="margin-top: 4px">{{ __('If you can read all of this, the printer works.', [], $lang) }}</div>

    @if ($poweredBy)
        <div class="center small muted" style="margin-top: 8px">{{ __('Powered by izSuite', [], $lang) }}</div>
    @endif
</main>
</body>
</html>
