{{-- A customer payment receipt or a supplier payment voucher (Doc 06
     section 13): who paid or was paid, how much, for what, and what was left
     to pay after it (DocumentRenderer). Its words come with it: $heading,
     $partyLabel, $amountLabel and $signatureLabel. --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    <main class="sheet">
        @include('documents.partials.header', ['label' => $heading])

        <section class="received">
            <div class="label">{{ __($partyLabel, [], $lang) }}</div>
            <div class="customer-name">{{ $customer['name'] ?? '' }}</div>
            @foreach ($customer['billing_address'] ?? [] as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
            <div class="label" style="margin-top: 12px">{{ __($amountLabel, [], $lang) }}</div>
            <div class="received-amount">{{ $amount }}</div>
        </section>

        <table class="rows">
            @foreach ($rows as [$name, $value])
                <tr>
                    <td class="muted">{{ __($name, [], $lang) }}</td>
                    <td class="num"><strong>{{ $value }}</strong></td>
                </tr>
            @endforeach
        </table>

        @if ($signature)
            <div class="signature">
                <img src="{{ $signature }}" alt="">
                <div class="muted">{{ __($signatureLabel, [], $lang) }}</div>
            </div>
        @endif
    </main>
</body>
</html>
