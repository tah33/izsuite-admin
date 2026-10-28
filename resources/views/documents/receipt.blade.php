{{-- A customer payment receipt (Doc 06 section 13): who paid, how much, for
     what, and what was left to pay after it (DocumentRenderer). --}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    <main class="sheet">
        @include('documents.partials.header', ['label' => 'Payment Receipt'])

        <section class="received">
            <div class="label">{{ __('Received From', [], $lang) }}</div>
            <div class="customer-name">{{ $customer['name'] ?? '' }}</div>
            @foreach ($customer['billing_address'] ?? [] as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
            <div class="label" style="margin-top: 12px">{{ __('Amount Received', [], $lang) }}</div>
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
                <div class="muted">{{ __('Received By', [], $lang) }}</div>
            </div>
        @endif
    </main>
</body>
</html>
